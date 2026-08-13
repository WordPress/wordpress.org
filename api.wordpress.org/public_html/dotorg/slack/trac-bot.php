<?php

namespace {
	require dirname( dirname( __DIR__ ) ) . '/wp-init.php';

	require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

	require dirname( dirname( __DIR__ ) ) . '/includes/class-trac.php';
}

namespace Dotorg\Slack\Trac {
	/*
	 * Slack authenticates itself with the shared `URL_SECRET__TRAC_BOT` secret passed in the
	 * outgoing webhook URL, verified below; nonces don't exist in server-to-server webhooks.
	 *
	 * phpcs:disable WordPress.Security.NonceVerification
	 */

	// Verify it came from Slack.
	if ( ! is_string( $_GET['token'] ?? null ) || ! hash_equals( URL_SECRET__TRAC_BOT, wp_unslash( $_GET['token'] ) ) ) {
		return;
	}

	/*
	 * The Slack channel name, user name, and message timestamp are interpolated into the Trac
	 * comment and the message permalink below.
	 */
	$channel_name  = sanitize_text_field( wp_unslash( $_POST['channel_name'] ?? '' ) );
	$user_name     = sanitize_text_field( wp_unslash( $_POST['user_name'] ?? '' ) );
	$msg_timestamp = sanitize_text_field( wp_unslash( $_POST['timestamp'] ?? '' ) );

	// Prevent recursion.
	if ( 'slackbot' === $user_name ) {
		return;
	}

	$parser = new Bot( $_POST );
	$parsed = $parser->parse();
	$parser->avoid_redundancy();
	# $parser->set_redundancy( 'slack', 'core', 'ticket', 12345 );

	$comment_template = 'This ticket was mentioned in [https://make.wordpress.org/chat/ Slack] in #%1$s by %2$s. [https://wordpress.slack.com/archives/%1$s/p%3$s View the logs].';
	$comment_template = "''$comment_template''"; // Italics.

	$ticket_class = '\Dotorg\Slack\Trac\Ticket';
	$commit_class = '\Dotorg\Slack\Trac\Commit';

	// Loop through all results, grouped by Trac and then by type (commit versus ticket).
	foreach ( $parsed as $trac => $results ) {
		$trac_obj = Trac::get( $trac );
		$slack = new \Dotorg\Slack\Send( \Dotorg\Slack\Send\WEBHOOK );
		$slack->set_user( $trac_obj );

		$parsed_objects = array(
			'ticket' => array(),
			'commit' => array(),
		);

		foreach ( $results as $type => $values ) {
			// Loop through all tickets and commits for this Trac.
			foreach ( $values as $value ) {
				$id = is_array( $value ) ? $value['id'] : $value;

				$class = 'commit' === $type ? $commit_class : $ticket_class;
				// Get the Ticket or Commit object for this Trac + ID.
				$obj = call_user_func( array( $class, 'get' ), $trac_obj, $id );

				// Keep a reference to this object for later.
				$parsed_objects[ $type ][ $id ] = $obj;

				// Check if we should be posting this to Slack so quickly.
				if ( $since = $parser->is_redundant( 'slack', $trac, $type, $id ) ) {
					// If we should not be posting the whole thing, see if it's been long enough to post a link (only if we did not parse a link).
					if ( ( ( $since + $parser::slack_repost_link ) < time() ) && empty( $value['url'] ) ) {
						$slack->add_attachment( array(
							'text'     => $obj->get_url(),
							'fallback' => $obj->get_url(),
						) );
						// Reset redundancy time since we just posted a link.
						$parser->set_redundancy( 'slack', $trac, $type, $id );
					}
					// We were redundant, skip the rest.
					continue;
				}

				$attachment = $obj->get_attachment();

				if ( $attachment ) {
					$parser->set_redundancy( 'slack', $trac, $type, $id );
					$slack->add_attachment( $attachment );
				} else {
					// We don't have an attachment when the Trac is private or if we experienced an error.
					// Don't set redundancy times on errors.
					if ( ! $trac_obj->is_public() ) {
						$parser->set_redundancy( 'slack', $trac, $type, $id );
					}

					// Provide a URL link only if we did not parse a link.
					if ( empty( $value['url'] ) ) {
						$slack->add_attachment( array(
							'text'     => $obj->get_url(),
							'fallback' => $obj->get_url(),
						) );
					}
				}
			}
		}

		// It's possible that all of our tickets/commits were redundant and thus skipped.
		if ( ! $slack->get_attachments() ) {
			continue;
		}

		$slack->send( $parser->get_channel(), $parser->get_thread() );

		if ( 'test' === $channel_name ) {
			// Don't post to Trac if we're coming from #test.
			continue;
		}

		// If there's no tickets referenced (ie. just commits) then there's no need to flag the reference on Trac.
		if ( empty( $results['ticket'] ) ) {
			continue;
		}

		$trac_xmlrpc = new \Trac( 'slackbot', SLACKBOT_WPORG_PASSWORD, "https://$trac.trac.wordpress.org/login/xmlrpc" );

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- local to this webhook script.
		$comment = sprintf( $comment_template, $channel_name, $user_name, str_replace( '.', '', $msg_timestamp ) );
		foreach ( $results['ticket'] as $ticket ) {
			$ticket_id = is_array( $ticket ) ? $ticket['id'] : $ticket;

			// If the ticket is closed and hasn't been modified in over 2 years, don't post a reference to it.
			if ( ! empty( $parsed_objects[ 'ticket' ][ $ticket_id ] ) ) {
				$ticket_object = $parsed_objects[ 'ticket' ][ $ticket_id ];
				$ticket_object->fetch();

				$is_closed         = ( 'closed' === $ticket_object->status );
				$last_modified     = strtotime( $ticket_object->modified );
				$has_recent_change = ( ! $last_modified || $last_modified > ( time() - 2 * YEAR_IN_SECONDS ) );

				if ( $is_closed && ! $has_recent_change ) {
					continue;
				}
			}

			if ( $parser->is_redundant( 'trac', $trac, 'ticket', $ticket_id ) ) {
				continue;
			}

			$parser->set_redundancy( 'trac', $trac, 'ticket', $ticket_id );

			$trac_xmlrpc->ticket_update( $ticket_id, $comment );
		}
	}
}

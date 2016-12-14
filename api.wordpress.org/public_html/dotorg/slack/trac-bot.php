<?php

namespace {
	require dirname( dirname( __DIR__ ) ) . '/includes/hyperdb/bb-10-hyper-db.php';
	require dirname( dirname( __DIR__ ) ) . '/includes/object-cache.php';

	require dirname( dirname( __DIR__ ) ) . '/includes/slack-config.php';

	require dirname( dirname( __DIR__ ) ) . '/includes/class-IXR.php';
	require dirname( dirname( __DIR__ ) ) . '/includes/class-trac.php';
}

namespace Dotorg\Slack\Trac {

	// Verify it came from Slack.
	if ( ! isset( $_GET['token'] ) || $_GET['token'] !== URL_SECRET__TRAC_BOT ) {
		return;
	}

	// Prevent recursion.
	if ( $_POST['user_name'] === 'slackbot' ) {
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

		foreach ( $results as $type => $values ) {
			// Loop through all tickets and commits for this Trac.
			foreach ( $values as $value ) {
				$id = is_array( $value ) ? $value['id'] : $value;

				$class = 'commit' === $type ? $commit_class : $ticket_class;
				// Get the Ticket or Commit object for this Trac + ID.
				$obj = call_user_func( array( $class, 'get' ), $trac_obj, $id );

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
					if ( ! $this->trac->is_public() ) {
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

		$slack->send( $parser->get_channel() );

		if ( $_POST['channel_name'] === 'test' ) {
			// Don't post to Trac if we're coming from #test.
			continue;
		}

		$trac_xmlrpc = new \Trac( 'slackbot', SLACKBOT_WPORG_PASSWORD, "$trac.trac.wordpress.org", '/login/xmlrpc', 443, true );
		$comment = sprintf( $comment_template, $_POST['channel_name'], $_POST['user_name'], str_replace( '.', '', $_POST['timestamp'] ) );
		foreach ( $results['ticket'] as $ticket ) {
			if ( is_array( $ticket ) ) {
				$ticket = $ticket['id'];
			}

			if ( $parser->is_redundant( 'trac', $trac, 'ticket', $ticket ) ) {	
				continue;
			}
			$parser->set_redundancy( 'trac', $trac, 'ticket', $ticket );

			$trac_xmlrpc->ticket_update( $ticket, $comment );
		}
	}
}

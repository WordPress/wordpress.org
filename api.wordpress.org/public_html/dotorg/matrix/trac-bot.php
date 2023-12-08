<?php

namespace {
	require dirname( dirname( __DIR__ ) ) . '/wp-init.php';

	require dirname( dirname( __DIR__ ) ) . '/includes/class-trac.php';

	require dirname( dirname( __DIR__ ) ) . '/includes/matrix/config.php';

	// Load Dotorg\Slack autoloader since Trac's Ticket and Commit class are defined under \Dotorg\Slack\ namespace.
	require dirname( dirname( __DIR__ ) ) . '/includes/slack/autoload.php';
}

namespace Dotorg\Matrix\Trac {

	define( 'REPOST_MATRIX_LINK_TIME', 300 ); // 5 mins
	define( 'REPOST_TRAC_COMMENT_TIME', 7200 ); // 2 hrs

	// Simulate a webhook request.
	// $_POST['secret'] = URL_SECRET__TRAC_BOT;
	// $_POST['text'] = 'Hello #40007 and #1232-bbpress'; // Or 'Hello #1232-bbpress'
	// $_POST['user_id'] = '@admin:matrix.test';
	// $_POST['room_id'] = '!XXXXXXXX:matrix.test';
	// $_POST['room_alias'] = '#matrix-testing:matrix.test';
	// $_POST['event_id'] = '$HHHHHHHHHHHHH';

	// Verify it came from Maubot tracbot plugin.
	if ( ! isset( $_POST['secret'] ) || $_POST['secret'] !== URL_SECRET__TRAC_BOT ) {
		http_response_code( 403 );
		return;
	}

	// room_name and room_alias can be empty for rooms not published in room directory
	if ( empty( $_POST['room_alias'] ) ) {
		http_response_code( 204 );
		return;
	}

	$room_alias_local_part = ltrim( explode( ':', $_POST['room_alias'] )[0], '#' );

	define( 'IS_MATRIX_TESTING_ROOM', $room_alias_local_part === 'matrix-testing' );

	// Prevent frequent posting to both Matrix & Trac using Redundancy Manager.
	$redundancy_manager = new Redundancy_Manager(
		$room_alias_local_part,
		REPOST_MATRIX_LINK_TIME,
		REPOST_TRAC_COMMENT_TIME,
	);
	$redundancy_manager->avoid_redundancy();

	// Note: echos are prevented and won't be received, so no need to handle them.

	// Data adjustment since trac bot is written for Slack.
	$post_data                 = $_POST;
	$post_data['channel_name'] = $room_alias_local_part;

	// Following code is copied from api/public_html/dotorg/slack/trac-bot.php and then modified.
	$parser = new \Dotorg\Slack\Trac\Bot( $post_data );
	$parsed = $parser->parse();

	$ticket_class = '\Dotorg\Slack\Trac\Ticket';
	$commit_class = '\Dotorg\Slack\Trac\Commit';

	// Matrix messages that would be posted as a response to this webhook request being handled.
	$matrix_messages = array();

	// Loop through all results, grouped by Trac and then by type (commit versus ticket).
	foreach ( $parsed as $trac => $results ) {
		$trac_obj = \Dotorg\Slack\Trac\Trac::get( $trac );

		$parsed_objects = array(
			'ticket' => array(),
			'commit' => array(),
		);

		//
		// What needs to be posted on Matrix?
		//

		foreach ( $results as $type => $values ) {
			// Loop through all tickets and commits for this Trac.
			foreach ( $values as $value ) {
				$id = is_array( $value ) ? $value['id'] : $value;

				$class = 'commit' === $type ? $commit_class : $ticket_class;
				// Get the Ticket or Commit object for this Trac + ID.
				$obj = call_user_func( array( $class, 'get' ), $trac_obj, $id );

				// Keep a reference to this object for later.
				$parsed_objects[ $type ][ $id ] = $obj;

				// skip if we have recently posted to Matrix.
				if ( $redundancy_manager->is_redundant( 'matrix', $trac, $type, $id ) ) {
					continue;
				}

				$matrix_message = '';
				$attachment     = $obj->get_attachment();
				if ( $attachment ) {
					$matrix_message = '[' . $attachment['title'] . '](' . $attachment['title_link'] . ')';
				} else {
					$url = $obj->get_url();
					if ( $url ) {
						$matrix_message = $url;
					}
				}

				if ( $matrix_message ) {
					$matrix_messages[] = $matrix_message;
					$redundancy_manager->set( 'matrix', $trac, $type, $id );
				}
			}
		}

		// It's possible that all of our tickets/commits were redundant and thus skipped.
		if ( count( $matrix_messages ) === 0 ) {
			continue;
		}

		//
		// What needs to be posted on Trac?
		//

		// If there's no tickets referenced (ie. just commits) then there's no need to flag the reference on Trac.
		if ( empty( $results['ticket'] ) ) {
			continue;
		}

		if ( ! IS_MATRIX_TESTING_ROOM ) {
			if ( defined( TRACBOT_WPORG_PASSWORD ) && ! empty( TRACBOT_WPORG_PASSWORD ) ) {
				$trac_xmlrpc = new \Trac( 'matrixbot', TRACBOT_WPORG_PASSWORD, "https://$trac.trac.wordpress.org/login/xmlrpc" );
			}
		}

		$comment_template = 'This ticket was mentioned in [Matrix](https://matrix.wordpress.net/) in %1$s by %2$s. [View the logs](https://matrix.to/#/%3$s/%4$s?via=trac).';
		$trac_comment     = sprintf( $comment_template, $post_data['room_alias'], $post_data['user_id'], $post_data['room_id'], $post_data['event_id'] );

		foreach ( $results['ticket'] as $ticket ) {
			$ticket_id = is_array( $ticket ) ? $ticket['id'] : $ticket;

			// If the ticket is closed and hasn't been modified in over 2 years, don't post a reference to it.
			if ( ! empty( $parsed_objects['ticket'][ $ticket_id ] ) ) {
				$ticket_object = $parsed_objects['ticket'][ $ticket_id ];
				$ticket_object->fetch();

				$is_closed         = ( 'closed' === $ticket_object->status );
				$last_modified     = strtotime( $ticket_object->modified );
				$has_recent_change = ( ! $last_modified || $last_modified > ( time() - 2 * YEAR_IN_SECONDS ) );

				if ( $is_closed && ! $has_recent_change ) {
					continue;
				}
			}

			if ( $redundancy_manager->is_redundant( 'trac', $trac, 'ticket', $ticket_id ) ) {
				continue;
			}

			$redundancy_manager->set( 'trac', $trac, 'ticket', $ticket_id );

			if ( ! IS_MATRIX_TESTING_ROOM ) {
				isset( $trac_xmlrpc ) && $trac_xmlrpc->ticket_update( $ticket_id, $trac_comment );
			} else {
				$matrix_messages[] = "[$trac] Trac comment: " . $trac_comment;
			}
		}
	}

	// Bail if it's too early to post matrix links again, as it would be too early to post a link on trac ticket as well.
	if ( count( $matrix_messages ) === 0 ) {
		http_response_code( 204 );
		exit;
	}

	// Return JSON, so that matrix messages can get posted.
	header( 'Content-Type: application/json' );
	$response = array(
		'messages'       => $matrix_messages,
		'post_in_thread' => false,
	);
	echo json_encode( $response );
	die();

	/**
	 * Redundancy_Manager class
	 */
	class Redundancy_Manager {

		private $cache_group = 'matrixtrac';
		private array $redundant_time;
		private bool $avoid_redundancy = false;
		private string $room;

		public function __construct( $room, $matrix_redundant_time, $trac_redundant_time ) {
			$this->room           = $room;
			$this->redundant_time = array(
				'matrix' => $matrix_redundant_time,
				'trac'   => $trac_redundant_time,
			);
		}

		public function avoid_redundancy() {
			$this->avoid_redundancy = true;
			wp_cache_init();
		}

		public function is_redundant( $realm, $trac, $type, $id ): bool {
			if ( ! $this->avoid_redundancy ) {
				return false;
			}

			$since = wp_cache_get(
				$this->key( $realm, $trac, $type, $id ),
				$this->cache_group
			);

			if ( empty( $since ) ) {
				return false;
			}

			$now                    = time();
			$redundancy_expiring_at = $since + $this->redundant_time[ $realm ];

			if ( $redundancy_expiring_at < $now ) {
				return false;
			}

			return true;
		}

		public function set( $realm, $trac, $type, $id ) {
			if ( ! $this->avoid_redundancy ) {
				return;
			}
			wp_cache_set(
				$this->key( $realm, $trac, $type, $id ),
				time(),
				$this->cache_group,
				$this->redundant_time[ $realm ]
			);
		}

		private function key( $realm, $trac, $type, $id ) {
			return sprintf( 'room:%s:%s:%s:%s:%d', $this->room, $realm, $trac, $type, $id );
		}
	}
}

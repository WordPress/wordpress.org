<?php namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Attendee\Attendee;

register_block_type(
	'wporg-translate-events-2024/event-host-list',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['id'] ) ) {
				return '';
			}
			$event_id = $attributes['id'];
			$event = Translation_Events::get_event_repository()->get_event( $event_id );

			$attendees             = Translation_Events::get_attendee_repository()->get_attendees( $event_id );
			$hosts = array_filter(
				$attendees,
				function ( Attendee $attendee ) {
					return $attendee->is_host();
				}
			);

			$has_hosts = count( $hosts ) > 0;

			if ( ! $has_hosts ) {
				$hosts = array( new Attendee( $event->id(), $event->author_id(), true ) );
			}
			$hosts_list = array_map(
				function ( $host ) {
					$url  = get_author_posts_url( $host->user_id() );
					$name = get_the_author_meta( 'display_name', $host->user_id() );
					return '<a href="' . esc_attr( $url ) . '">' . esc_html( $name ) . '</a>';
				},
				$hosts
			);

			if ( ! $has_hosts ) {
				/* translators: %s: Display name of the user who created the event. */
				$hosts_string = __( 'Created by: %s', 'gp-translation-events' );
			} else {
				/* translators: %s is a comma-separated list of event hosts (=usernames) */
				$hosts_string = _n( 'Host: %s', 'Hosts: %s', count( $hosts ), 'gp-translation-events' );
			}
			return wp_kses(
				sprintf( $hosts_string, implode( ', ', $hosts_list ) ),
				array( 'a' => array( 'href' => array() ) )
			);
		},
	)
);

<?php

namespace Wporg\TranslationEvents;

class Urls {
	public static function events_home(): string {
		return gp_url( '/events' );
	}

	public static function events_trashed(): string {
		return gp_url( '/events/trashed' );
	}

	public static function event_details( int $event_id ): string {
		// Drafts don't yet have a slug, so we need to generate a sample permalink.
		if ( 'draft' === get_post_status( $event_id ) ) {
			// get_sample_permalink is only available in the admin, so we need to include the file in case we are elsewhere.
			require_once ABSPATH . '/wp-admin/includes/post.php';
			list( $permalink, $post_name ) = get_sample_permalink( $event_id );
			$permalink                     = str_replace( '%pagename%', $post_name, $permalink );
		} else {
			$permalink = get_permalink( $event_id );
		}

		return gp_url( wp_make_link_relative( $permalink ) );
	}

	public static function event_details_absolute( int $event_id ): string {
		return site_url( self::event_details( $event_id ) );
	}

	public static function event_translations( int $event_id, string $locale, string $status = '' ): string {
		return gp_url_join( self::event_details( $event_id ), 'translations', $locale, $status );
	}

	public static function event_edit( int $event_id ): string {
		return gp_url( '/events/edit/' . $event_id );
	}

	public static function event_trash( int $event_id ): string {
		return gp_url( '/events/trash/' . $event_id );
	}

	public static function event_delete( int $event_id ): string {
		return gp_url( '/events/delete/' . $event_id );
	}

	public static function event_create(): string {
		return gp_url( '/events/new/' );
	}

	/**
	 * Returns the absolute URL to the image for the event.
	 *
	 * @param int $event_id The event ID.
	 *
	 * @return string
	 */
	public static function event_image( int $event_id ): string {
		return home_url( gp_url( "events/image/$event_id" ) );
	}

	/**
	 * Returns the absolute URL to the default event image.
	 *
	 * @return string
	 */
	public static function event_default_image(): string {
		return self::event_image( 0 );
	}

	public static function event_toggle_attendee( int $event_id ): string {
		return gp_url( "/events/attend/$event_id" );
	}

	public static function event_toggle_host( int $event_id, int $user_id ): string {
		return gp_url( "/events/host/$event_id/$user_id" );
	}

	public static function my_events(): string {
		return gp_url( '/events/my-events/' );
	}

	public static function event_attendees( int $event_id ): string {
		return self::event_details( $event_id ) . 'attendees/';
	}

	public static function event_remove_attendee( int $event_id, int $user_id ): string {
		return gp_url( "/events/$event_id/attendees/remove/$user_id" );
	}
}

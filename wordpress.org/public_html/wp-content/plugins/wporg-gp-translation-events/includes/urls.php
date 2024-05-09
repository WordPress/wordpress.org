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
		return gp_url( wp_make_link_relative( get_the_permalink( $event_id ) ) );
	}

	public static function event_details_absolute( int $event_id ): string {
		list( $permalink, $post_name ) = get_sample_permalink( $event_id );
		$permalink                     = str_replace( '%pagename%', $post_name, $permalink );

		return get_site_url() . gp_url( wp_make_link_relative( $permalink ) );
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
}

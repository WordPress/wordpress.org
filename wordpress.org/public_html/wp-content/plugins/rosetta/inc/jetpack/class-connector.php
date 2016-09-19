<?php

namespace WordPressdotorg\Rosetta\Jetpack;

use Jetpack_Options;
use Jetpack_Network;

class Connector {

	/**
	 * The name of the connect event.
	 */
	const EVENT_NAME = 'wporg_connect_new_rosetta_site';

	/**
	 * Delay of the connect event in minutes.
	 */
	const EVENT_DELAY = 3;

	/**
	 * User ID of the connection owner.
	 */
	const CONNECTION_OWNER = 5911429; // User: wordpressdotorg

	/**
	 * Schedules an attempt to connect a site to Jetpack.
	 *
	 * @param int $blog_id The blog ID to connect.
	 */
	public function schedule_connect_event( $blog_id ) {
		wp_schedule_single_event(
			time() + self::EVENT_DELAY * MINUTE_IN_SECONDS,
			self::EVENT_NAME,
			[ $blog_id ]
		);
	}

	/**
	 * Returns the name of the connect event.
	 *
	 * @return string Connect event name.
	 */
	public function get_connect_event_name() {
		return self::EVENT_NAME;
	}

	/**
	 * Connects a site to Jetpack.
	 *
	 * @param int $blog_id The blog ID to connect.
	 */
	public function connect_site( $blog_id ) {
		if ( ! class_exists( 'Jetpack_Network' ) || ! class_exists( 'Jetpack_Options' ) ) {
			return;
		}

		// Jetpack can get confused about the parent site id.
		// Force it to the WP.com id of global.wordpress.org.
		switch_to_blog( $blog_id );
		Jetpack_Options::update_option( 'id', 25117111 );
		restore_current_blog();

		$network         = Jetpack_Network::init();
		$current_user_id = get_current_user_id();

		wp_set_current_user( self::CONNECTION_OWNER );
		$network->do_subsiteregister( $blog_id );
		wp_set_current_user( $current_user_id );
	}
}

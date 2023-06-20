<?php

namespace Official_WordPress_Events\Online_Events;

add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts', 11 );
add_action( 'wp_footer', __NAMESPACE__ . '\render_online_templates' );

add_shortcode( 'official_wordpress_events_online', __NAMESPACE__ . '\render_online_shortcode' );

/**
 * Get events from the local database, filtering only for online events.
 *
 * @return array
 */
function get_synced_online_events() {
	global $wpdb;

	$table = \Official_WordPress_Events::EVENTS_TABLE;

	// Include yesterday's events because server timezone may be ahead of user's timezone.
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$raw_events = $wpdb->get_results(
		"SELECT title, url, date_utc, date_utc_offset, type
		FROM `{$table}`
		WHERE
			date_utc >= SUBDATE( CURRENT_DATE(), 1 ) AND
			status    = 'scheduled' AND
			(
				location = 'online' OR
				title LIKE '%[ONLINE]%'
			)
		ORDER BY date_utc ASC
		LIMIT 300"
	);
	// phpcs:enable

	$cached_events = array();
	foreach ( $raw_events as $event ) {
		// The `date_utc` is not actually a UTC timestamp, it's the local time as Y-m-d H:i:s.
		// We need to convert it back to UTC by subtracting the UTC offset.
		$timestamp       = strtotime( $event->date_utc ) - $event->date_utc_offset;
		$cached_events[] = array(
			'title'           => $event->title,
			'url'             => $event->url,
			'start_timestamp' => $timestamp,
			'type'            => $event->type,
		);
	}

	// As the original data was sorted by local time (`date_utc`),
	// we need to reorder the events after it's converted back to UTC.
	usort(
		$cached_events,
		function( $a, $b ) {
			return $a['start_timestamp'] <=> $b['start_timestamp'];
		}
	);

	return $cached_events;
}

/**
 * Enqueue scripts and styles
 */
function enqueue_scripts() {
	global $post;

	wp_register_script(
		'official-events-online',
		plugins_url( 'official-events-online.js', __FILE__ ),
		array( 'jquery', 'wp-date', 'wp-util' ),
		\Official_WordPress_Events::CACHEBUSTER,
		true
	);

	if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'official_wordpress_events_online' ) ) {
		wp_enqueue_style( 'official-wordpress-events' );
		wp_enqueue_script( 'official-events-online' );

		$events = get_synced_online_events();
		wp_add_inline_script(
			'official-events-online',
			sprintf(
				'var OfficialWordPressEvents = JSON.parse( decodeURIComponent( \'%s\' ) );',
				rawurlencode( wp_json_encode( $events ) )
			),
			'before'
		);
	}
}

/**
 * Inject JS templates into page.
 */
function render_online_templates() {
	require_once __DIR__ . '/template-events-online.php';
}

/**
 * Output the container div, which is filled in by JS.
 */
function render_online_shortcode() {
	return '<div id="official-online-events" class="ofe-events">Loading events…</div>';
}

<?php

/**
 * wcpt_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
*/
function wcpt_head () {
	do_action( 'wcpt_head' );
}
add_action( 'wp_head', 'wcpt_head' );

/**
 * wcpt_head ()
 *
 * Add our custom head action to wp_head
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 */
function wcpt_footer () {
	do_action( 'wcpt_footer' );
}
add_action( 'wp_footer', 'wcpt_footer' );

/**
 * wcpt_has_access()
 *
 * Make sure user can perform special tasks
 *
 * @package WordCamp Post Type
 * @subpackage Functions
 * @since WordCamp Post Type (0.1)
 *
 * @uses is_super_admin ()
 * @uses apply_filters
 *
 * @return bool $has_access
 */
function wcpt_has_access () {
	if ( is_super_admin () )
		$has_access = true;
	else
		$has_access = false;

	return apply_filters( 'wcpt_has_access', $has_access );
}

/**
 * wcpt_number_format ( $number, $decimals optional )
 *
 * Specific method of formatting numeric values
 *
 * @package WordCamp Post Type
 * @subpackage Functions
 * @since WordCamp Post Type (0.1)
 *
 * @param string $number Number to format
 * @param string $decimals optional Display decimals
 * @return string Formatted string
 */
function wcpt_number_format( $number, $decimals = false ) {
	// If empty, set $number to '0'
	if ( empty( $number ) || !is_numeric( $number ) )
		$number = '0';

	return apply_filters( 'wcpt_number_format', number_format( $number, $decimals ), $number, $decimals );
}

/**
 * wcpt_key_to_str ( $key = '' )
 *
 * Turn meta key into lower case string and transform spaces into underscores
 *
 * @param string $key
 * @return string
 */
function wcpt_key_to_str( $key, $prefix = '' ) {
	return $prefix . str_replace( array( ' ', '.' ), '_', strtolower( $key ) );
}

/**
 * Render the Log metabox
 *
 * @param WP_Post $post
 */
function wcpt_log_metabox( $post ) {
	$entries = wcpt_get_log_entries( $post->ID );

	require_once( __DIR__ . '/views/common/metabox-log.php' );
}

/**
 * Get all the the entries
 *
 * @param int $wordcamp_id
 *
 * @return array
 */
function wcpt_get_log_entries( $wordcamp_id ) {
	$entries        = array();
	$notes          = get_post_meta( $wordcamp_id, '_note'          );
	$status_changes = get_post_meta( $wordcamp_id, '_status_change' );

	foreach ( array( 'note' => $notes, 'status_change' => $status_changes ) as $entry_type => $raw_entries ) {
		foreach ( $raw_entries as $entry ) {
			$user = get_user_by( 'id', $entry['user_id'] );

			$entry['type']              = $entry_type;
			$entry['user_display_name'] = $user->display_name;

			$entries[] = $entry;
		}
	}

	usort( $entries, 'wcpt_sort_log_entries' );

	return $entries;
}

/**
 * Sort the log entries in reverse-chronological order
 *
 * @param array $a
 * @param array $b
 *
 * @return int
 */
function wcpt_sort_log_entries( $a, $b ) {
	// If a status change and a note occur at the same time, show the change before the note
	if ( $a['timestamp'] == $b['timestamp'] ) {
		return ( 'status_change' == $a['type'] ) ? 1 : -1;
	}

	return ( $a['timestamp'] > $b['timestamp'] ) ? -1 : 1;
}

/**
 * Render the Notes metabox
 *
 * @param WP_Post $post
 */
function wcpt_add_note_metabox( $post ) {
	wp_nonce_field( 'wcpt_notes', 'wcpt_notes_nonce' );

	require_once( __DIR__ . '/views/common/metabox-notes.php' );
}

<?php
/**
 * Moderation tool: video posts whose content is not just one [wpvideo] shortcode.
 *
 * Clean content is exactly one [wpvideo ...] shortcode and nothing else, not
 * even a line break. VideoPress blocks are not handled: posts with a block
 * are listed too. Not cached or paginated: every visit runs the query again.
 *
 * @package WordPressTV
 */

namespace WordPressTV\ModTools\PostContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Address of the production site, where the listed posts are fixed. */
const PRODUCTION_URL = 'https://wordpress.tv';

/**
 * Returns every post whose content is not exactly one [wpvideo] shortcode.
 *
 * Clean content starts with "[wpvideo ", ends with "]" and contains a single
 * "[" and a single "]". Trashed posts and auto-drafts are skipped.
 *
 * @return object[] Rows with ID, post_status, post_title and post_content, newest first.
 */
function find() {
	global $wpdb;

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off admin check, never cached on purpose.
	return $wpdb->get_results(
		"SELECT ID, post_status, post_title, post_content FROM {$wpdb->posts}
		WHERE post_type = 'post' AND post_status NOT IN ( 'trash', 'auto-draft' )
		AND NOT (
			post_content LIKE '[wpvideo %]'
			AND LENGTH( post_content ) - LENGTH( REPLACE( post_content, '[', '' ) ) = 1
			AND LENGTH( post_content ) - LENGTH( REPLACE( post_content, ']', '' ) ) = 1
		)
		ORDER BY post_date DESC, ID DESC"
	);
}

/**
 * Returns why a listed post content is not clean.
 *
 * @param string $content Post content.
 * @return string
 */
function reason( $content ) {
	$shortcodes = substr_count( strtolower( $content ), '[wpvideo' );
	if ( 0 === $shortcodes ) {
		return __( 'No [wpvideo] shortcode', 'wptv' );
	}
	if ( $shortcodes > 1 ) {
		return __( 'More than one [wpvideo] shortcode', 'wptv' );
	}

	$rest    = preg_replace( '/\[wpvideo [^\]]*\]/i', '', $content );
	$visible = trim( str_replace( array( '&nbsp;', "\xc2\xa0" ), '', wp_strip_all_tags( $rest ) ) );
	if ( '' === $visible ) {
		return __( 'Line breaks, spaces or empty markup', 'wptv' );
	}
	return __( 'Extra content', 'wptv' );
}

/**
 * Returns the content besides the shortcode, shortened, to show what is there.
 *
 * @param string $content Post content.
 * @return string
 */
function leftover( $content ) {
	$rest = trim( preg_replace( '/\[wpvideo [^\]]*\]/i', '', $content ) );
	return wp_html_excerpt( $rest, 100, '…' );
}

/**
 * Prints the total, the count per reason and every listed post.
 */
function render() {
	$rows = find();

	$reasons = array();
	foreach ( $rows as $row ) {
		$row->reason = reason( $row->post_content );
		if ( ! isset( $reasons[ $row->reason ] ) ) {
			$reasons[ $row->reason ] = 0;
		}
		++$reasons[ $row->reason ];
	}

	echo '<p><strong>' . esc_html(
		sprintf(
			/* translators: %s: number of posts. */
			_n( '%s post to check.', '%s posts to check.', count( $rows ), 'wptv' ),
			number_format_i18n( count( $rows ) )
		)
	) . '</strong></p>';

	if ( ! $rows ) {
		return;
	}

	echo '<ul>';
	foreach ( $reasons as $reason => $count ) {
		echo '<li>' . esc_html( $reason ) . ': ' . esc_html( number_format_i18n( $count ) ) . '</li>';
	}
	echo '</ul>';

	_prime_post_caches( wp_list_pluck( $rows, 'ID' ), false, false );
	$home = untrailingslashit( home_url() );

	// A plain table, so it can be copied into a spreadsheet as cells.
	echo '<table class="widefat striped"><thead><tr>';
	echo '<th>' . esc_html__( 'Address', 'wptv' ) . '</th>';
	echo '<th>' . esc_html__( 'Title', 'wptv' ) . '</th>';
	echo '<th>' . esc_html__( 'Status', 'wptv' ) . '</th>';
	echo '<th>' . esc_html__( 'Reason', 'wptv' ) . '</th>';
	echo '<th>' . esc_html__( 'Content besides the shortcode', 'wptv' ) . '</th>';
	echo '</tr></thead><tbody>';
	foreach ( $rows as $row ) {
		// Unpublished posts have no WordPress.tv address, only their title.
		$url = 'publish' === $row->post_status ? PRODUCTION_URL . substr( get_permalink( $row->ID ), strlen( $home ) ) : '';
		echo '<tr>';
		echo '<td>' . ( $url ? '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>' : '' ) . '</td>';
		echo '<td>' . esc_html( $row->post_title ) . '</td>';
		echo '<td>' . esc_html( $row->post_status ) . '</td>';
		echo '<td>' . esc_html( $row->reason ) . '</td>';
		echo '<td>' . esc_html( leftover( $row->post_content ) ) . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

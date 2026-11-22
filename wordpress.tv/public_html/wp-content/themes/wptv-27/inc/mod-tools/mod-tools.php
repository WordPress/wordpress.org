<?php
/**
 * Moderation tools: read-only work lists under Tools > Moderation tools.
 *
 * Each tool lists videos that need a human correction. Nothing is changed
 * from these screens: moderators open each post and fix it in the editor,
 * and the post then leaves the list. Administrators only (edit_theme_options),
 * as decided in docs/decisions.md.
 *
 * To add a tool, add a file to this folder and an entry to tools().
 *
 * @package WordPressTV
 */

namespace WordPressTV\ModTools;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/post-content.php';

/** Capability required to see the moderation tools. */
const CAPABILITY = 'edit_theme_options';

/** Admin page slug. */
const PAGE = 'wptv-mod-tools';

/**
 * Returns the available tools, keyed by slug.
 *
 * Each tool has a title, a description and a render callback that prints
 * its list.
 *
 * @return array[]
 */
function tools() {
	return array(
		'post-content' => array(
			'title'       => __( 'Post content to clean', 'wptv' ),
			'description' => __( 'Video posts whose content is not exactly one [wpvideo] shortcode, with the reason and their address on WordPress.tv (unpublished posts show their title). Video post content must hold only the video before moving to the new theme.', 'wptv' ),
			'render'      => __NAMESPACE__ . '\PostContent\render',
		),
	);
}

/**
 * Adds the page to the Tools menu.
 */
function add_page() {
	add_management_page(
		__( 'Moderation tools', 'wptv' ),
		__( 'Moderation tools', 'wptv' ),
		CAPABILITY,
		PAGE,
		__NAMESPACE__ . '\render_page'
	);
}
add_action( 'admin_menu', __NAMESPACE__ . '\add_page' );

/**
 * Returns the URL of the tools index, or of one tool.
 *
 * @param string $tool Tool slug, or empty for the index.
 * @return string
 */
function page_url( $tool = '' ) {
	$args = array( 'page' => PAGE );
	if ( $tool ) {
		$args['tool'] = $tool;
	}
	return add_query_arg( $args, admin_url( 'tools.php' ) );
}

/**
 * Renders the tools index or the requested tool.
 */
function render_page() {
	if ( ! current_user_can( CAPABILITY ) ) {
		return;
	}

	$tools = tools();
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen selection.
	$slug = isset( $_GET['tool'] ) ? sanitize_key( wp_unslash( $_GET['tool'] ) ) : '';

	echo '<div class="wrap">';
	if ( isset( $tools[ $slug ] ) ) {
		$tool = $tools[ $slug ];
		echo '<h1>' . esc_html( $tool['title'] ) . '</h1>';
		echo '<p><a href="' . esc_url( page_url() ) . '">&larr; ' . esc_html__( 'All moderation tools', 'wptv' ) . '</a></p>';
		echo '<p>' . esc_html( $tool['description'] ) . '</p>';
		call_user_func( $tool['render'] );
	} else {
		echo '<h1>' . esc_html__( 'Moderation tools', 'wptv' ) . '</h1>';
		echo '<p>' . esc_html__( 'Read-only work lists of videos that need a manual correction. Open each video, fix it in the editor, and it leaves the list.', 'wptv' ) . '</p>';
		echo '<table class="widefat striped"><tbody>';
		foreach ( $tools as $tool_slug => $tool ) {
			echo '<tr><td><strong><a href="' . esc_url( page_url( $tool_slug ) ) . '">' . esc_html( $tool['title'] ) . '</a></strong><br>' . esc_html( $tool['description'] ) . '</td></tr>';
		}
		echo '</tbody></table>';
	}
	echo '</div>';
}

<?php
/**
 * Plugin Name: Plugin Import Notice
 * Description: Shows an admin notice when editing an approved plugin that has no content.
 */

add_action( 'admin_notices', function () {
	$screen = get_current_screen();
	if ( ! $screen || 'plugin' !== $screen->id ) {
		return;
	}

	$post = get_post();
	if ( ! $post || 'approved' !== $post->post_status ) {
		return;
	}

	printf(
		'<div class="notice notice-info"><p>This plugin has not been imported yet. Run <code>npm run plugins:import %s</code> to import it.</p></div>',
		esc_html( $post->post_name )
	);
} );

<?php
/**
 * Set up custom block bindings.
 */

namespace WordPressdotorg\Plugin_Directory\Theme\Block_Bindings;

// Actions and filters.
add_action( 'init', __NAMESPACE__ . '\register_block_bindings' );

/**
 * Register block bindings.
 *
 * This registers some sources which can be used to dynamically inject content
 * into block text or attributes.
 */
function register_block_bindings() {
	register_block_bindings_source(
		'wporg-plugins/meta',
		array(
			'label' => 'Plugin meta',
			'uses_context' => [ 'postId' ],
			'get_value_callback' => __NAMESPACE__ . '\get_meta_block_value',
		)
	);
}

/**
 * Callback to provide the binding value.
 */
function get_meta_block_value( $args, $block ) {
	if ( ! isset( $args['key'] ) ) {
		return '';
	}

	$plugin_post = get_post( $block->context['postId'] );
	if ( ! $plugin_post ) {
		return '';
	}

	switch ( $args['key'] ) {
		case 'ratings-link':
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( 'https://wordpress.org/support/plugin/' . $plugin_post->post_name . '/reviews/' ),
				__( 'See all<span class="screen-reader-text"> reviews</span>', 'wporg-themes' )
			);
		case 'submit-review-link':
			return sprintf(
				'<a href="%s">%s</a>',
				esc_url( 'https://wordpress.org/support/plugin/' . $plugin_post->post_name . '/reviews/#new-post' ),
				__( 'Add my review', 'wporg-themes' )
			);
	}
}

<?php
/**
 * Set up custom block bindings.
 */

namespace WordPressdotorg\Plugin_Directory\Theme\Block_Bindings;

use WordPressdotorg\Plugin_Directory\Template;

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
		case 'plugin-banner-url':
			$raw_banners = Template::get_plugin_banner( $post, 'raw_with_rtl' );
			if ( is_rtl() ) {
				if ( ! empty( $raw_banners['banner_2x_rtl'] ) ) {
					return $raw_banners['banner_2x_rtl'];
				}
				if ( ! empty( $raw_banners['banner_rtl'] ) ) {
					return $raw_banners['banner_rtl'];
				}
			}
			if ( ! empty( $raw_banners['banner_2x'] ) ) {
				return $raw_banners['banner_2x'];
			}
			if ( ! empty( $raw_banners['banner'] ) ) {
				return $raw_banners['banner'];
			}
			return '';
		case 'plugin-icon-url':
			$raw_icons = Template::get_plugin_icon( $plugin_post, 'raw' );
			if ( ! empty( $raw_icons['svg'] ) ) {
				return $raw_icons['svg'];
			}
			if ( ! empty( $raw_icons['icon_2x'] ) ) {
				return $raw_icons['icon_2x'];
			}
			if ( ! empty( $raw_icons['icon'] ) ) {
				return $raw_icons['icon'];
			}
			return '';
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

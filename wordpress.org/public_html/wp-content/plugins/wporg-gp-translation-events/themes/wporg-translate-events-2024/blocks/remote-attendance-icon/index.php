<?php
namespace Wporg\TranslationEvents\Theme_2024;

register_block_type(
	'wporg-translate-events-2024/remote-attendance-icon',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( $attributes ) {
			$css_class = isset( $attributes['css_class'] ) ? esc_attr( $attributes['css_class'] ) : '';

			return sprintf( '<span class="dashicons dashicons-video-alt2 %s"></span>', $css_class );
		},
	)
);

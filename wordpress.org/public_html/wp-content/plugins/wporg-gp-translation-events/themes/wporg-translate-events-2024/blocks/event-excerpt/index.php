<?php namespace Wporg\TranslationEvents\Theme_2024;
use Wporg\TranslationEvents\Translation_Events;

register_block_type(
	'wporg-translate-events-2024/event-excerpt',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			return '<p>' . esc_html( get_the_excerpt( $attributes['id'] ) ) . '</p>';
		},
	)
);

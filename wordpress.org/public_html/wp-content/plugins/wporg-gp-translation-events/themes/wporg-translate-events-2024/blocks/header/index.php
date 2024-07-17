<?php namespace Wporg\TranslationEvents\Theme_2024;

register_block_type(
	'wporg-translate-events-2024/header',
	array(
		'attributes'      => array(
			'title' => array(
				'type' => 'string',
			),
		),
		// The $attributes argument cannot be removed despite not being used in this function,
		// because otherwise it won't be available in render.php.
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			// The site header must be rendered before the call to wp_head() in render.php, so that styles and
			// scripts of the referenced blocks are registered.
			ob_start();
			require __DIR__ . '/site-header.php';
			$site_header = do_blocks( ob_get_clean() );

			ob_start();
			require __DIR__ . '/render.php';
			return ob_get_clean();
		},
	)
);

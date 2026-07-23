<?php
namespace Wporg\TranslationEvents\Theme_2024;

register_block_type(
	'wporg-translate-events-2024/page-events-home',
	array(
		'render_callback' => function ( array $attributes ) {
			render_page(
				__DIR__ . '/render.php',
				__( 'Translation Events', 'wporg-translate-events-2024' ),
				$attributes
			);
		},
	)
);

<?php
namespace Wporg\TranslationEvents\Theme_2024;

register_block_type(
	'wporg-translate-events-2024/page-events-event-edit',
	array(
		'render_callback' => function ( array $attributes ) {
			render_page(
				__DIR__ . '/render.php',
				__( 'Edit event', 'wporg-translate-events-2024' ),
				$attributes
			);
		},
	)
);

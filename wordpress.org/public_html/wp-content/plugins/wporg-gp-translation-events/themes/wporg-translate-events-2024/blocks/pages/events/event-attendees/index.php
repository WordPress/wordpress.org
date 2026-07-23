<?php
namespace Wporg\TranslationEvents\Theme_2024;

register_block_type(
	'wporg-translate-events-2024/page-events-event-attendees',
	array(
		'render_callback' => function ( array $attributes ) {
			add_filter(
				'wporg_block_site_breadcrumbs',
				function ( $breadcrumbs ): array {
					return array_merge(
						$breadcrumbs,
						array(
							array(
								'title' => __( 'Manage Attendees', 'wporg-translate-events-2024' ),
								'url'   => null,
							),
						)
					);
				}
			);

			render_page(
				__DIR__ . '/render.php',
				__( 'Manage Attendees', 'wporg-translate-events-2024' ),
				$attributes
			);
		},
	)
);

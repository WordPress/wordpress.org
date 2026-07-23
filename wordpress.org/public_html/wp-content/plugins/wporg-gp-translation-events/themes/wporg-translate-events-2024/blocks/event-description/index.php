<?php namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Translation_Events;


register_block_type(
	'wporg-translate-events-2024/event-description',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['id'] ) ) {
				return '';
			}
			$event_id = $attributes['id'];
			$event = Translation_Events::get_event_repository()->get_event( $event_id );
			if ( ! $event ) {
				return '';
			}
			ob_start();
			?>
			<!-- wp:paragraph -->
			<?php echo wp_kses_post( wpautop( make_clickable( $event->description() ) ) ); ?>
			<!-- /wp:paragraph -->
			<?php
				return ob_get_clean();
		},
	)
);

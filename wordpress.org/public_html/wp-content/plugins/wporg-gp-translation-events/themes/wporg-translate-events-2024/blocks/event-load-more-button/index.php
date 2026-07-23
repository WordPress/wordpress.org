<?php
/**
 * Registers the event-load-more-button block, which renders a button that loads more events for a given filter.
 *
 * @package wporg-translate-events-2024
 */

namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Translation_Events;

register_block_type(
	'wporg-translate-events-2024/event-load-more-button',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( $attributes ) {
			$event_filter = $attributes['filter'] ?? '';
			$next_page = $attributes['next_page'] ?? 1;

			if ( ! $event_filter || ! $next_page ) {
				return;
			}
			ob_start();
			?>
			<!-- wp:button {"className":"is-style-outline"} -->
			<div class="wp-block-button is-style-outline">
				<button class="wp-block-button__link wp-element-button load-more-events-btn" data-event-type="<?php echo esc_attr( $event_filter ); ?>" data-event-next-page="<?php echo esc_attr( $next_page ); ?>" ><?php esc_html_e( 'Load more', 'gp-translation-events' ); ?></button>
			</div>
			<!-- /wp:button -->
			<?php
			return ob_get_clean();
		},
	)
);

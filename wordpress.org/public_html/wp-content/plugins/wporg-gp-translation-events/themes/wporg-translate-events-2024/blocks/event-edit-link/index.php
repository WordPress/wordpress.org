<?php
namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

register_block_type(
	'wporg-translate-events-2024/event-edit-link',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes, $content, $block ) {
			if ( ! isset( $block->context['postId'] ) ) {
				return '';
			}
			$event_id = $block->context['postId'];
			$event = Translation_Events::get_event_repository()->get_event( $event_id );
			if ( ! $event ) {
				return '';
			}

			ob_start();
			if ( ! current_user_can( 'edit_translation_event', $event->id() ) ) {
				return '';
			}
			?>
				<a href="<?php echo esc_url( Urls::event_edit( $event->id() ) ); ?>"
					class="event-list-item-button"
					title="<?php echo esc_attr__( 'Edit', 'gp-translation-events' ); ?>">
					<span class="dashicons dashicons-edit"></span>
				</a>
			<?php
			return ob_get_clean();
		},
	)
);

<?php
namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

register_block_type(
	'wporg-translate-events-2024/event-trash-link',
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
			if ( ! current_user_can( 'trash_translation_event', $event->id() ) ) {
				return '';
			}
			if ( $event->is_trashed() ) :
				?>
					<a href="<?php echo esc_url( Urls::event_trash( $event->id() ) ); ?>"
						class="button is-small"
						title="<?php echo esc_attr__( 'Restore', 'gp-translation-events' ); ?>">
						<?php echo esc_attr__( 'Restore', 'gp-translation-events' ); ?>
					</a>
				<?php else : ?>
					<a href="<?php echo esc_url( Urls::event_trash( $event->id() ) ); ?>"
						class="event-list-item-button is-destructive"
						title="<?php echo esc_attr__( 'Move to trash', 'gp-translation-events' ); ?>">
						<span class="dashicons dashicons-trash"></span>
					</a>
					<?php
			endif;
				return ob_get_clean();
		},
	)
);

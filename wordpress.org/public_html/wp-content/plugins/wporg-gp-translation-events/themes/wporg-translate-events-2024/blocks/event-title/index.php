<?php namespace Wporg\TranslationEvents\Theme_2024;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;


register_block_type(
	'wporg-translate-events-2024/event-title',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes, $content, $block ) {
			if ( ! isset( $block->context['postId'] ) ) {
				return '';
			}
			$event_id = get_the_ID();
			ob_start();
			$event = Translation_Events::get_event_repository()->get_event( $event_id );
			if ( ! $event ) {
				return '';
			}
			$url = Urls::event_details( $event->id() );
			?>
			<h3 class="wporg-marker-list-item__title">
					<a href="<?php echo esc_url( $url ); ?>">
						<?php echo esc_html( $event->title() ); ?>
					</a>
				</h3>
			<?php
			return ob_get_clean();
		},
	)
);

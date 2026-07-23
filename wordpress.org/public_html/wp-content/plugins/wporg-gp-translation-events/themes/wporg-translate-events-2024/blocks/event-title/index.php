<?php
/**
 * Registers the event-title block, which renders an event's title heading.
 *
 * @package wporg-translate-events-2024
 */

namespace Wporg\TranslationEvents\Theme_2024;

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
			<?php
			if ( 'remote' === $event->attendance_mode() ) {
				// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				echo do_blocks( sprintf( '<!-- wp:wporg-translate-events-2024/remote-attendance-icon %s  /-->', wp_json_encode( array( 'css_class' => 'video-icon-on-title' ) ) ) );
			}
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

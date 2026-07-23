<?php
namespace Wporg\TranslationEvents\Theme_2024;
use Wporg\TranslationEvents\Translation_Events;

register_block_type(
	'wporg-translate-events-2024/event-flag',
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
			$current_user_attendee = Translation_Events::get_attendee_repository()->is_user_attending( $event_id, get_current_user_id() );
			$event_flag = '';

			if ( ! $current_user_attendee ) {
				return '';
			}
			if ( $event->is_past() ) {
				$event_flag = $current_user_attendee->is_host() ? __( 'Hosted', 'wporg-translate-events-2024' ) : __( 'Attended', 'wporg-translate-events-2024' );
			} else {
				$event_flag = $current_user_attendee->is_host() ? __( 'Hosting', 'wporg-translate-events-2024' ) : __( 'Attending', 'wporg-translate-events-2024' );
			}

			if ( ! $event_flag ) {
				return '';
			}

			ob_start();
			?>
			<span class="my-event-flag"><?php echo esc_html( $event_flag ); ?></span>
			<?php
			return ob_get_clean();
		},
	)
);

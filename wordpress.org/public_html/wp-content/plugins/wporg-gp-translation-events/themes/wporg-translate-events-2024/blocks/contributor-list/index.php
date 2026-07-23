<?php
/**
 * Registers the contributor-list block, which renders the contributing attendees of an event.
 *
 * @package wporg-translate-events-2024
 */

namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Attendee\Attendee;

register_block_type(
	'wporg-translate-events-2024/contributor-list',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['id'] ) ) {
				return '';
			}
			$event_id     = $attributes['id'];
			$attendees    = Translation_Events::get_attendee_repository()->get_attendees( $event_id );
			$contributors = array_filter(
				$attendees,
				function ( Attendee $attendee ) {
					return $attendee->is_contributor();
				}
			);
			if ( empty( $contributors ) ) {
				return '';
			}

			ob_start();
			?>
			<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
			<h4 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700">
				<?php
					// translators: %d: number of contributors .
					echo esc_html( sprintf( __( 'Contributors (%d)', 'wporg-translate-events-2024' ), number_format_i18n( count( $contributors ) ) ) );
				?>
			</h4>
			<!-- /wp:heading -->

			<!-- wp:group {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
			<div class="wp-block-group">
				<?php
				foreach ( $contributors as $contributor ) :
					?>
					<!-- wp:group -->
					<div class="wp-block-group">
						<!-- wp:wporg-translate-events-2024/attendee-avatar-name
						<?php
						echo wp_json_encode(
							array(
								'user_id'            => $contributor->user_id(),
								'is_new_contributor' => $contributor->is_new_contributor(),
							)
						);
						?>
						/-->
						<?php if ( $contributor->is_remote() ) : ?>
							<!-- wp:wporg-translate-events-2024/remote-attendance-icon <?php echo wp_json_encode( array( 'css_class' => 'video-icon-on-gravatar' ) ); ?> /-->
						<?php endif; ?>
					</div>
					<!-- /wp:group -->
					<?php
				endforeach;
				?>
			</div>
			<!-- /wp:group -->
			<?php
			return ob_get_clean();
		},
	)
);

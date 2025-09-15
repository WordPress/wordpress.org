<?php
namespace Wporg\TranslationEvents\Theme_2024;
use Wporg\TranslationEvents\Translation_Events;


register_block_type(
	'wporg-translate-events-2024/event-list',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			$event_ids = $attributes['event_ids'] ?? array();

			if ( empty( $event_ids ) ) {
				return;
			}

			ob_start();
			?>
			<div class="wp-block-wporg-event-list">
			<ul class="wporg-marker-list__container">
				<?php
				foreach ( $event_ids as $event_id ) {
					?>
					<li class="wporg-marker-list-item">
						<!-- wp:wporg-translate-events-2024/event-template <?php echo wp_json_encode( array( 'id' => $event_id ) ); ?> -->
						<div>
							<!-- wp:wporg-translate-events-2024/event-title /-->
							<!-- wp:wporg-translate-events-2024/event-flag /-->
						</div>
						<!-- wp:wporg-translate-events-2024/event-attendance-mode /-->
						<!-- wp:wporg-translate-events-2024/event-start /-->
						<!-- /wp:wporg-translate-events-2024/event-list-->

						<!-- /wp:wporg-translate-events-2024/event-template -->
					</li>
					<?php
				}
				?>
			</ul>
			</div>
			<?php
			return ob_get_clean();
		},
	)
);

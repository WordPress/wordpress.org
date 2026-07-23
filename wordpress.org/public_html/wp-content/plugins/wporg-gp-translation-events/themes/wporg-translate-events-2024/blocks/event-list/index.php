<?php
namespace Wporg\TranslationEvents\Theme_2024;
use Wporg\TranslationEvents\Translation_Events;


register_block_type(
	'wporg-translate-events-2024/event-list',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			$event_ids = $attributes['event_ids'] ?? array();
			$event_filter = $attributes['filter_by'] ?? '';
			if ( empty( $event_ids ) ) {
				return get_no_result_view();
			}
			$show_flag = ! empty( $attributes['show_flag'] ) && true === $attributes['show_flag'];
			$next_page = ! empty( $attributes['next_page'] ) ? $attributes['next_page'] : 0;
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
							<?php
							if ( $show_flag ) :
								?>
							<!-- wp:wporg-translate-events-2024/event-flag /-->
								<?php
							endif;
							?>
						<!-- wp:wporg-translate-events-2024/event-edit-link /-->
						<!-- wp:wporg-translate-events-2024/event-trash-link /-->
						</div>
						<!-- wp:wporg-translate-events-2024/event-attendance-mode /-->
						<!-- wp:wporg-translate-events-2024/event-start /-->
						<!-- /wp:wporg-translate-events-2024/event-template -->
					</li>
					<?php
				}
				?>
			</ul>
			</div>
			<!-- wp:wporg-translate-events-2024/event-load-more-button 
				<?php
				echo wp_json_encode(
					array(
						'filter'    => $event_filter,
						'next_page' => $next_page,
					)
				);
				?>
			/-->
			<?php
			return ob_get_clean();
		},
	)
);

/**
 * Returns a block driven view when no results are found.
 *
 * @return string
 */
function get_no_result_view() {
	$content  = '<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10"}}},"layout":{"type":"constrained"}} -->';
	$content .= '<div class="wp-block-group" style="padding-top:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10)">';
	$content .= sprintf(
		'<!-- wp:paragraph {"align":"center"} --><p class="has-text-align-center">%s</p><!-- /wp:paragraph -->',
		esc_attr__( 'No events found in this category.', 'wporg-translate-events-2024' )
	);
	$content .= '</div><!-- /wp:group -->';

	return do_blocks( $content );
}


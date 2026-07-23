<?php namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Stats\Stats_Calculator;
use Wporg\TranslationEvents\Urls;



register_block_type(
	'wporg-translate-events-2024/event-stats',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['id'] ) ) {
				return '';
			}
			$event_id = $attributes['id'];
			$event_stats = ( new Stats_Calculator() )->for_event( $event_id );
			ob_start();
			?>
			<?php if ( ! empty( $event_stats->rows() ) ) : ?>

			<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
			<h4 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700"><?php echo esc_html( __( 'Stats', 'wporg-translate-events-2024' ) ); ?></h4>
			<!-- /wp:heading -->

			<!-- wp:table -->
				<figure class="wp-block-table">
					<table class="event-stats">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Translations', 'wporg-translate-events-2024' ); ?></th>
								<th><?php esc_html_e( 'Created', 'wporg-translate-events-2024' ); ?></th>
								<th><?php esc_html_e( 'Reviewed', 'wporg-translate-events-2024' ); ?></th>
								<th><?php esc_html_e( 'Contributors', 'wporg-translate-events-2024' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $event_stats->rows() as $_locale => $row ) : ?>
							<tr>
								<td title="<?php echo esc_html( $_locale ); ?> "><a href="<?php echo esc_url( gp_url_join( gp_url( '/languages' ), $row->language->slug ) ); ?>"><?php echo esc_html( $row->language->english_name ); ?></a></td>
								<td><a href="<?php echo esc_url( Urls::event_translations( $event_id, $row->language->slug ) ); ?>"><?php echo esc_html( $row->created ); ?></a></td>
								<td><?php echo esc_html( $row->reviewed ); ?></td>
								<td><?php echo esc_html( $row->users ); ?></td>
							</tr>
							<?php endforeach; ?>
							<tr class="event-details-stats-totals">
								<td><?php esc_html_e( 'Total', 'wporg-translate-events-2024' ); ?></td>
								<td><?php echo esc_html( $event_stats->totals()->created ); ?></td>
								<td><?php echo esc_html( $event_stats->totals()->reviewed ); ?></td>
								<td><?php echo esc_html( $event_stats->totals()->users ); ?></td>
							</tr>
						</tbody>
					</table>
				</figure>
			<!-- /wp:table -->
				<?php
				endif;
				return ob_get_clean();
		},
	)
);

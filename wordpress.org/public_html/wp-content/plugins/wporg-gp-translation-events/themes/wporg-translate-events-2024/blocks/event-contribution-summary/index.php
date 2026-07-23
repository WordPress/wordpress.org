<?php namespace Wporg\TranslationEvents\Theme_2024;

use WP_User;
use Wporg\TranslationEvents\Stats\Stats_Calculator;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Translation_Events;


register_block_type(
	'wporg-translate-events-2024/event-contribution-summary',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['id'] ) ) {
				return '';
			}
			$event_id = $attributes['id'];
			$event = Translation_Events::get_event_repository()->get_event( $event_id );
			if ( ! $event ) {
				return '';
			}
			$event_stats = ( new Stats_Calculator() )->for_event( $event_id );
			if ( empty( $event_stats->rows() ) ) {
				return '';
			}

			$attendees             = Translation_Events::get_attendee_repository()->get_attendees( $event_id );
			$contributors = array_filter(
				$attendees,
				function ( Attendee $attendee ) {
					return $attendee->is_contributor();
				}
			);
			$new_contributor_ids = array_filter(
				$contributors,
				function ( Attendee $contributor ) {
					return $contributor->is_new_contributor();
				}
			);
			ob_start();
			?>
			<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
			<h4 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700"><?php echo esc_html( __( 'Summary', 'wporg-translate-events-2024' ) ); ?></h4>
			<!-- /wp:heading -->
				<p class="event-stats-text">
					<?php
					$new_contributors_text = '';
					if ( ! empty( $new_contributor_ids ) ) {
						$new_contributors_text = sprintf(
							// translators: %d is the number of new contributors.
							_n( '(%d new contributor 🎉)', '(%d new contributors 🎉)', count( $new_contributor_ids ), 'wporg-translate-events-2024' ),
							count( $new_contributor_ids )
						);
					}

					echo wp_kses(
						wp_sprintf(
							// translators: %1$s: Event title, %2$d: Number of contributors, %3$s: is a parenthesis with potential text "x new contributors", %4$d: Number of languages, %5$l: List of languages, %6$d: Number of strings translated, %7$d: Number of strings reviewed.
							__( 'At the <strong>%1$s</strong> event, we had %2$d people %3$s who contributed in %4$d languages (%5$l), translated %6$d strings and reviewed %7$d strings.', 'wporg-translate-events-2024' ),
							esc_html( $event->title() ),
							esc_html( $event_stats->totals()->users ),
							$new_contributors_text,
							count( $event_stats->rows() ),
							array_map(
								function ( $row ) {
									return $row->language->english_name;
								},
								$event_stats->rows()
							),
							esc_html( $event_stats->totals()->created ),
							esc_html( $event_stats->totals()->reviewed )
						),
						array(
							'strong' => array(),
						)
					);
					?>
					<?php
					echo wp_kses(
						wp_sprintf(
							// translators: %s List of contributors.
							_n(
								'Contributor was %l.',
								'Contributors were %l.',
								count( $contributors ),
								'wporg-translate-events-2024'
							),
							array_map(
								function ( $contributor ) {
									$append_tada = '';
									if ( $contributor->is_new_contributor() ) {
											$append_tada = ' <span class="new-contributor" title="' . esc_html__( 'New Translation Contributor', 'wporg-translate-events-2024' ) . '">🎉</span>';
									}
									return '@' . ( new WP_User( $contributor->user_id() ) )->user_login . $append_tada;
								},
								$contributors
							)
						),
						array(
							'span' => array(
								'class' => array(),
								'title' => array(),
							),
						)
					);
					?>
			</p>
			<?php
				return ob_get_clean();
		},
	)
);

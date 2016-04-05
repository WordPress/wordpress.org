<?php
/**
 * Sidebar for schedules page template.
 */
?>

		<div id="primary" class="wc-planned" role="complementary">
			<h3>Planned WordCamps</h3>

			<?php echo wptexturize( wpautop( "These WordCamps are in the early stages of planning, but don't have a date yet. When their dates are confirmed, they'll be added to the schedule of approved WordCamps." ) );
			?>

			<?php
				// Get the upcoming approved (published) WordCamps *with dates*
				$args = array(
					'posts_per_page' => -1,
					'post_status' => WordCamp_Loader::get_pre_planning_post_statuses(),
					'meta_key'       => 'Start Date (YYYY-mm-dd)',
					'orderby'        => 'meta_value',
					'order'          => 'ASC',
					'meta_query'     => array( array(
						'key'        => 'Start Date (YYYY-mm-dd)',
						'value'      => 1,
						'compare'    => '>' // Only with dates
					) )
				);
			?>

			<?php if ( function_exists( 'wcpt_has_wordcamps' ) ) : ?>

			<ul class="xoxo">

				<?php wcpt_has_wordcamps( $args ); ?>
				<?php while ( wcpt_wordcamps() ) : wcpt_the_wordcamp(); ?>

					<li>
						<strong>
							<?php if ( wcpt_get_wordcamp_url() ) : ?>
								<a href="<?php echo esc_url( wcpt_get_wordcamp_url() ); ?>"><?php wcpt_wordcamp_title(); ?></a>
							<?php else : ?>
								<?php wcpt_wordcamp_title(); ?>
							<?php endif; ?>
						</strong><br />
						<?php if ( wcpt_get_wordcamp_start_date( 0, 'F, Y' ) ) : ?>
						<?php wcpt_wordcamp_start_date( 0, 'F, Y' ); ?><br />
						<?php endif; ?>
						<?php wcpt_wordcamp_location(); ?>
					</li>

				<?php endwhile; // wcpt_wordcamps ?>

				<?php
					// Change the query args, this time get the ones without dates
					// and run the query again
					$args['meta_query'][0]['compare'] = '<';
					$args['orderby'] = 'date';
					wcpt_has_wordcamps( $args );
				?>

				<?php while ( wcpt_wordcamps() ) : wcpt_the_wordcamp(); ?>

					<li>
						<strong>
							<?php if ( wcpt_get_wordcamp_url() ) : ?>
								<a href="<?php echo esc_url( wcpt_get_wordcamp_url() ); ?>">
									<?php wcpt_wordcamp_title(); ?>
								</a>
							<?php else : ?>
								<?php wcpt_wordcamp_title(); ?>
							<?php endif; ?>
						</strong><br />
						<?php wcpt_wordcamp_location(); ?>
					</li>

				<?php endwhile; // wcpt_wordcamps ?>

				<li>
					<?php echo wptexturize(
						wpautop( 'Don&#8217;t see your city on the list, but yearning for a local WordCamp? Check out what it takes to <a href="/become-an-organizer/">become an organizer</a>!')
					); ?>
				</li>

			</ul>

			<?php endif; // wcpt_has_wordcamps / function_exists ?>



		</div><!-- #primary .widget-area -->

 <?php
/**
 * Template Name: Past WordCamps
 *
 * A custom page template for the Past WordCamps list.
 *
 */

get_header(); ?>

		<div id="container" class="wc-schedule">
			<div id="content" role="main">

				<?php if ( have_posts() ) : the_post(); ?>

				<div id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
					<h1 class="entry-title"><?php the_title(); ?></h1>
					<div class="entry-content">
						<?php the_content(); ?>
					</div><!-- .entry-content -->
				</div><!-- #post-## -->

				<?php endif; // end of the loop. ?>

					<?php // Get the upcoming approved (published) WordCamps
					if ( function_exists( 'wcpt_has_wordcamps' ) &&
						wcpt_has_wordcamps( array(
							'post_status' => array(
								'wcpt-needs-debrief',
								'wcpt-debrief-schedul',
								'wcpt-closed',

								// back-compat
								'publish',
							),
							'posts_per_page' => -1,
							'meta_key'       => 'Start Date (YYYY-mm-dd)',
							'orderby'        => 'meta_value',
							'order'          => 'DESC',
							'meta_query'     => array( array(
								'key'        => 'Start Date (YYYY-mm-dd)',
								'value'      => strtotime( '-2 days' ),
								'compare'    => '<'
							) )
						) )
					) :
						global $wcpt_template;
						$wordcamps = WordCamp_Central_Theme::group_wordcamps_by_year( $wcpt_template->posts );
					?>

					<?php foreach ( $wordcamps as $year => $posts ) : ?>
						<h3 class="wc-schedule-year"><?php echo esc_html( $year ); ?></h3>

						<ul class="wc-schedule-list">
							<?php foreach ( $posts as $post ) : setup_postdata( $post ); ?>

								<li>
									<a href="<?php echo esc_url( WordCamp_Central_Theme::get_best_wordcamp_url( $post->ID ) ); ?>">
										<?php if ( has_post_thumbnail() ) : ?>
											<?php the_post_thumbnail( 'wccentral-thumbnail-past', array( 'class' => 'wc-image' ) ); ?>
										<?php else : ?>
											<div class="wc-image wp-post-image past-wordcamp-placeholder-thumb" title="<?php the_title(); ?>"></div>
										<?php endif; ?>

										<h2 class="wc-title"><?php wcpt_wordcamp_title(); ?></h2>
										<span class="wc-country"><?php wcpt_wordcamp_location( $post->ID ); ?></span>

										<span class="wc-date">
											<?php WordCamp_Central_Theme::the_wordcamp_date( $post->ID ); ?>,
											<?php wcpt_wordcamp_start_date( $post->ID, 'Y' ); ?>
										</span>
									</a>
								</li>

							<?php endforeach; ?>
						</ul>
					<?php wp_reset_postdata(); endforeach; ?>

				<a href="<?php echo home_url( '/schedule/' ); ?>" class="wc-schedule-more"><span class="arrow">&larr;</span> Upcoming WordCamps</a>

				<?php endif; // wcpt_has_wordcamps ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php
	/*get_sidebar( 'schedule' ); */
	get_footer();
?>

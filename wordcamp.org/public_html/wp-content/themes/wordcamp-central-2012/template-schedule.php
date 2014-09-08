<?php
/**
 * Template Name: WordCamp Schedule
 *
 * A custom page template for the Upcoming WordCamp schedule.
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
							'posts_per_page' => -1,
							'meta_key'       => 'Start Date (YYYY-mm-dd)',
							'orderby'        => 'meta_value',
							'order'          => 'ASC',
							'meta_query'     => array( array(
								'key'        => 'Start Date (YYYY-mm-dd)',
								'value'      => strtotime( '-2 days' ),
								'compare'    => '>'
							) )
						) ) 
					) :
					?>

					<ul class="wc-schedule-list">
					<?php while ( wcpt_wordcamps() ) : wcpt_the_wordcamp(); ?>
						
						<li>
							<a href="<?php wcpt_wordcamp_permalink(); ?>">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'wccentral-thumbnail-small', array( 'class' => 'wc-image' ) ); ?>
								<?php else : ?>
									<div class="wc-image wp-post-image wordcamp-placeholder-thumb" title="<?php the_title(); ?>"></div>
								<?php endif; ?>
								
								<h2 class="wc-title"><?php wcpt_wordcamp_title(); ?></h2>
								<span class="wc-country"><?php wcpt_wordcamp_location(); ?></span>
								<span class="wc-date">
									<?php WordCamp_Central_Theme::the_wordcamp_date(); ?>
								</span>
							</a>
						</li>

					<?php endwhile; // wcpt_wordcamps ?>
				</ul>
				<a href="<?php echo home_url( '/schedule/past-wordcamps/' ); ?>" class="wc-schedule-more">Past WordCamps &rarr;</a>

				<?php endif; // wcpt_has_wordcamps ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php 
	get_sidebar( 'schedule' ); 
	get_footer();
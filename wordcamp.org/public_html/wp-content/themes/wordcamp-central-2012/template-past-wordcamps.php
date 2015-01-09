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
					?>

					<ul class="wc-schedule-list">
					<?php while ( wcpt_wordcamps() ) : wcpt_the_wordcamp(); ?>
						
						<li>
							<a href="<?php echo esc_url( wcpt_get_wordcamp_url() ); ?>">
								<?php if ( has_post_thumbnail() ) : ?>
									<?php the_post_thumbnail( 'wccentral-thumbnail-past', array( 'class' => 'wc-image' ) ); ?>
								<?php else : ?> 
									<div class="wc-image wp-post-image past-wordcamp-placeholder-thumb" title="<?php the_title(); ?>"></div>
								<?php endif; ?>
								
								
								<h2 class="wc-title"><?php wcpt_wordcamp_title(); ?></h2>
								<span class="wc-country"><?php wcpt_wordcamp_location(); ?></span>
								<span class="wc-date">
								
								<?php WordCamp_Central_Theme::the_wordcamp_date(); ?>,
								<?php wcpt_wordcamp_start_date( 0, 'Y' ); ?>
									
								</span>
							</a>
						</li>

					<?php endwhile; // wcpt_wordcamps ?>
				</ul>
				<a href="<?php echo home_url( '/schedule/' ); ?>" class="wc-schedule-more"><span class="arrow">&larr;</span> Upcoming WordCamps</a>

				<?php endif; // wcpt_has_wordcamps ?>

			</div><!-- #content -->
		</div><!-- #container -->

<?php 
	/*get_sidebar( 'schedule' ); */
	get_footer(); 
?>

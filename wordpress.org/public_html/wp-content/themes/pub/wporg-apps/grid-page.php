<?php
/**
 * Template Name: Grid Page
 *
 * @package wpmobileapps
 */

get_header(); ?>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php while ( have_posts() ) : the_post(); ?>

				<?php get_template_part( 'content', 'intro' ); ?>

			<?php endwhile; // end of the loop. ?>

			<div class="features-header">
					<h1><?php _e( 'Features' , 'wpmobileapps'); ?></h1>
					<h3><?php _e( 'The power of publishing in your&nbsp;pocket.' , 'wpmobileapps'); ?></h3>
			</div>

			<div class="child-pages grid">
				<?php
					$child_pages = new WP_Query( array(
						'post_type'      => 'page',
						'orderby'        => 'menu_order',
						'order'          => 'ASC',
						'post_parent'    => $post->ID,
						'posts_per_page' => 999,
						'no_found_rows'  => true,
					) );

					while ( $child_pages->have_posts() ) : $child_pages->the_post();
						get_template_part( 'content', 'grid' );
					endwhile;
					wp_reset_postdata();
				?>
				</div><!-- .grid-row -->
			</div><!-- .child-pages .grid -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>

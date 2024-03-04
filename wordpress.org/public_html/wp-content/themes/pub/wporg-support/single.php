<?php
/**
 * The template for displaying all single post or CPT entry.
 *
 * @package WPBBP
 */

get_header(); ?>

<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

	<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow clear">

		<?php get_sidebar( 'helphub' ); ?>

		<div id="main-content">
			<?php
			while ( have_posts() ) :
				the_post();

				get_template_part( 'template-parts/content', 'single' );

				if ( comments_open() ) :
					comments_template();
				endif;
			endwhile; // End of the loop.
			?>
		</div>

	</div>

</main><!-- #main -->

<?php
get_footer();

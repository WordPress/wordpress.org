<?php
/**
 * The template for displaying the under construction page.
 *
 * Template Name: Under Construction
 *
 * @package bporg-developer
 * @since 1.0.0
 */

get_header(); ?>

	<div id="primary" class="content-area">

		<div id="content-area">
			<?php breadcrumb_trail(); ?>
		</div>

		<main id="main" class="site-main" role="main">
			<div class="reference-landing">
                <div class="section clear">

                    <?php while ( have_posts() ) : the_post(); ?>

                        <?php if ( get_the_content() ) : 
                            get_template_part( 'content', 'page' );
                            
                        else: ?>
                            <h2><?php esc_html_e( 'Under construction', 'bporg-developer' ); ?></h2>
                        <?php endif; ?>

                        <?php
                            // If comments are open or we have at least one comment, load up the comment template
                            if ( comments_open() || '0' != get_comments_number() ) :
                                comments_template();
                            endif;
                        ?>

                    <?php endwhile; // end of the loop. ?>

                </div><!-- /section.clear -->

			</div><!-- /reference-landing -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>

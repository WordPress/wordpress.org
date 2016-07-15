<?php
/**
 * Single handbook template.
 *
 * @package p2-breathe
 */

get_header(); ?>

<div class="handbook-name"><span><a href="<?php echo esc_url( get_post_type_archive_link( wporg_get_current_handbook() ) ); ?>"><?php echo esc_html( wporg_get_current_handbook_name() ); ?></a></span></div>

<?php get_sidebar( 'handbook' ); ?> 

<div id="primary" class="content-area">

	<div class="site-content" role="main">

		<?php while ( have_posts() ) : the_post(); ?>

			<header class="handbook-header">
				<h1 class="handbook-page-title"><?php the_title(); ?></h1>
			</header><!-- .handbook-header -->

			<?php the_content(); ?>

			<?php \WPorg_Handbook_Navigation::show_nav_links(); ?>

		<?php endwhile; // end of the loop. ?>

	</div> <!-- .site-content -->

</div> <!-- #primary -->

<?php get_footer(); ?>


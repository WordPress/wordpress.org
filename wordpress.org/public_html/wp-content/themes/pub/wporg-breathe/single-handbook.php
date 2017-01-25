<?php
/**
 * Single handbook template.
 *
 * @package p2-breathe
 */

get_header(); ?>

<?php $is_landing_page = wporg_is_handbook_landing_page(); ?>

<?php get_sidebar( 'handbook' ); ?>

<?php if ( ! $is_landing_page && 'handbook' !== wporg_get_current_handbook() ) { ?>
<div class="handbook-name-container">
	<div class="handbook-name"><span>
		<a href="<?php echo esc_url( wporg_get_current_handbook_home_url() ); ?>"><?php echo esc_html( wporg_get_current_handbook_name() ); ?></a>
	</span></div>
</div>
<?php } ?>

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


<?php
/**
 * Single handbook template.
 *
 * @package p2-breathe
 */

get_header(); ?>

<?php $is_landing_page = wporg_is_handbook_landing_page(); ?>

<?php get_sidebar( 'handbook' ); ?>

<?php if ( 'handbook' !== wporg_get_current_handbook() ) { ?>
<div class="handbook-name-container">
	<div class="handbook-name"><span>
		<a href="<?php echo esc_url( wporg_get_current_handbook_home_url() ); ?>"><?php echo esc_html( wporg_get_current_handbook_name() ); ?></a>
	</span></div>
</div>
<?php } ?>

<?php do_action( 'handbook_breadcrumbs' ); ?>

<div id="primary" class="content-area">
	
	<!-- A fake o2 content area -->
	<div style="display: none;"><div id="content"></div></div>

	<div class="site-content" role="main">

		<?php while ( have_posts() ) : the_post(); ?>

			<header class="handbook-header">
				<h1 class="handbook-page-title"><?php the_title(); ?></h1>
			</header><!-- .handbook-header -->

			<?php
			the_content();

			printf(
				/* translators: %s: Date of last update. */
				'<p class="handbook-last-updated">' . __( 'Last updated: %s', 'wporg' ) . '</p>',
				sprintf(
					'<time datetime="%s">%s</time>',
					esc_attr( get_the_modified_date( DATE_W3C ) ),
					esc_html( get_the_modified_date() )
				)
			);

			\WPorg_Handbook_Navigation::show_nav_links();
			?>

		<?php endwhile; // end of the loop. ?>

	</div> <!-- .site-content -->

</div> <!-- #primary -->

<?php get_footer(); ?>


<?php
/**
 * The template for displaying Search Results pages.
 *
 * @package wporg-breathe
 */

get_header(); ?>

<?php $is_handbook = function_exists( 'wporg_is_handbook' ) && wporg_is_handbook(); ?>

<?php if ( $is_handbook ) { get_sidebar( 'handbook' ); } ?>

	<section id="primary" class="content-area">
		<div id="<?php echo $is_handbook ? 'handbook-content' : 'content'; ?>" class="site-content" role="main">

		<?php if ( have_posts() ) : ?>

			<header class="page-header">
				<h1 class="page-title">
					<?php if ( $is_handbook ) {
						printf( __( 'Handbook Search Results for: %s', 'wporg' ), '<span>' . get_search_query() . '</span>' );
					} else {
						printf( __( 'Search Results for: %s', 'wporg' ), '<span>' . get_search_query() . '</span>' );
					} ?>

					<span class="controls">
						<?php do_action( 'breathe_view_controls' ); ?>
					</span>
				</h1>
			</header><!-- .page-header -->

			<?php /* Start the Loop */ ?>
			<?php while ( have_posts() ) : the_post(); ?>

				<?php get_template_part( 'content', 'search' ); ?>

			<?php endwhile; ?>

			<?php breathe_content_nav( 'nav-below' ); ?>

		<?php else : ?>

			<?php get_template_part( 'no-results', 'search' ); ?>

		<?php endif; ?>

		</div><!-- #content -->
	</section><!-- #primary -->

<?php if ( ! $is_handbook ) { get_sidebar(); } ?>
<?php if ( $is_handbook ) : ?>
	<!-- A fake o2 content area -->
	<div style="display: none;"><div id="content"></div></div>
<?php endif; ?>
<?php get_footer(); ?>

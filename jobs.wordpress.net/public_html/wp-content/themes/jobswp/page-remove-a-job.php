<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package jobswp
 */

get_header(); ?>

<?php get_sidebar(); ?>

	<div id="primary" class="content-area grid_9">
		<main id="main" class="site-main" role="main">

	<?php if ( have_posts() ) : ?>
		<?php /* Start the Loop */ ?>
		<?php while ( have_posts() ) : the_post(); ?>

		<div class="entry-article">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<?php if ( isset( $_POST['errors'] ) ) : ?>
			<div class="entry-content">
				<div class="notice notice-error">
					<?php if ( is_string( $_POST['errors'] ) ) {
						echo sprintf( __( '<strong>ERROR:</strong> %s', 'jobswp' ), esc_html( $_POST['errors'] ) );
					} else {
						_e( '<strong>ERROR:</strong> One or more required fields are missing a value.', 'jobswp' );
					} ?>
					<?php do_action( 'jobswp_notice', 'error' ); ?>
				</div>
			</div>
			<?php elseif ( isset( $_GET['removedjob'] ) && '1' === $_GET['removedjob'] ) : ?>
			<div class="entry-content">
				<div class="notice notice-success">
					<strong><?php _e( 'Your job posting has been successfully removed.', 'jobswp' ); ?></strong>
				</div>
			</div>
			<?php endif; ?>

			<div class="entry-content">

				<?php the_content(); ?>

				<div>
					<form class="post-job" method="post" action="">

						<?php jobswp_text_field( 'job_token', __( 'Job Token:', 'jobswp' ) ); ?>

						<input type="hidden" name="removejob" value="1" />

						<?php wp_nonce_field( 'jobswpremovejob' ); ?>

						<?php do_action( 'jobswp_remove_job_form' ); ?>

						<input class="submit-job" type="submit" name="submitjob" value="<?php _e( 'Remove job', 'jobswp' ); ?>" />

					</form>
				</div>

				<?php edit_post_link( __( 'Edit', 'jobswp' ), '<footer class="entry-meta grid_9"><span class="edit-link">', '</span></footer>' ); ?>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

		</div>

		<?php endwhile; ?>

	<?php else : ?>

		<?php get_template_part( 'no-results', 'index' ); ?>

	<?php endif; ?>

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>
<?php
/**
 * The template used for displaying page content in page.php
 *
 * @package jobswp
 */

get_header(); ?>

	<main>

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
						/* translators: %s: Error message. */
						printf( wp_kses_post( __( '<strong>ERROR:</strong> %s', 'jobswp' ) ), esc_html( wp_unslash( $_POST['errors'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Display-only form redisplay; the value is escaped with esc_html() at output.
					} else {
						echo wp_kses_post( __( '<strong>ERROR:</strong> One or more required fields are missing a value.', 'jobswp' ) );
					} ?>
					<?php do_action( 'jobswp_notice', 'error' ); ?>
				</div>
			</div>
			<?php elseif ( isset( $_GET['removedjob'] ) && '1' === $_GET['removedjob'] ) : ?>
			<div class="entry-content">
				<div class="notice notice-success">
					<strong><?php esc_html_e( 'Your job posting has been successfully removed.', 'jobswp' ); ?></strong>
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

						<input class="btn btn-primary submit-job" type="submit" name="submitjob" value="<?php esc_attr_e( 'Remove job', 'jobswp' ); ?>" />

					</form>
				</div>

				<?php edit_post_link( __( 'Edit', 'jobswp' ), '<footer class="entry-meta"><span class="edit-link">', '</span></footer>' ); ?>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

		</div>

		<?php endwhile; ?>

	<?php else : ?>

		<?php get_template_part( 'no-results', 'index' ); ?>

	<?php endif; ?>

	</main>

<?php get_footer(); ?>
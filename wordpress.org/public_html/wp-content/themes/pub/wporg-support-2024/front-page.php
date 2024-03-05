<?php

/**
 * The front page of the site.
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="site-main" role="main">

		<?php if ( ! is_active_sidebar( 'front-page-blocks' ) ) : ?>
			<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>
		<?php else : ?>
			<div class="three-up helphub-front-page">
				<?php dynamic_sidebar( 'front-page-blocks' ); ?>
			</div>

			<hr>

			<div id="helphub-forum-link" class="text-center">
				<h3><?php esc_html_e( 'Support Forums', 'wporg-forums' ); ?></h3>

				<p>
					<span>
						<?php esc_html_e( 'Can\'t find what you\'re looking for? Find out if others share your experience.', 'wporg-forums' ); ?>
					</span>

					<br>

					<a href="<?php echo esc_url( site_url( '/forums/' ) ); ?>"><?php esc_html_e( 'Check out our support forums', 'wporg-forums' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

	</main>

<?php
get_footer();

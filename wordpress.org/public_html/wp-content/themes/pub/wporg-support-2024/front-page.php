<?php

/**
 * The front page of the site.
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow entry-content">

			<?php if ( is_active_sidebar( 'front-page-blocks' ) ) : ?>

				<div>

					<section class="helphub-front-page">
						<?php echo do_blocks(
							sprintf(
								'<!-- wp:heading -->
								<h2 class="wp-block-heading screen-reader-text">%s</h2>
								<!-- /wp:heading -->
								<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%%"},"fontSize":"small"} -->
								<div class="wp-block-group is-style-cards-grid has-small-font-size">%s</div>
								<!-- /wp:group -->',
								__( 'Documentation', 'wporg-forums' ),
								wporg_support_get_front_page_blocks(),
							)
						); ?>
					</section>

					<section id="helphub-forum-link">
						<h2><?php esc_html_e( 'Support Forums', 'wporg-forums' ); ?></h2>

						<p>
							<?php esc_html_e( 'Can\'t find what you\'re looking for? Find out if others share your experience.', 'wporg-forums' ); ?> <a href="<?php echo esc_url( site_url( '/forums/' ) ); ?>"><?php esc_html_e( 'Check out our support forums', 'wporg-forums' ); ?></a>.
						</p>
					</section>

				</div>

			<?php else : ?>

				<section id="forum-welcome">

					<?php echo do_blocks( '<!-- wp:pattern {"slug":"wporg-support/welcome-cards"} /-->' ); ?>

				</section>

				<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>

			<?php endif; ?>

		</div>

	</main>

<?php
get_footer();

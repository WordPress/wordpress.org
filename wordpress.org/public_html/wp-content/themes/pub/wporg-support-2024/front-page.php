<?php

/**
 * The front page of the site.
 *
 * @package WPBBP
 */

get_header(); ?>

	<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">

			<?php if ( is_active_sidebar( 'front-page-blocks' ) ) : ?>

				<div class="three-up helphub-front-page">
					<?php dynamic_sidebar( 'front-page-blocks' ); ?>
				</div>

				<div id="helphub-forum-link">
					<h2><?php esc_html_e( 'Support Forums', 'wporg-forums' ); ?></h2>

					<p>
						<?php esc_html_e( 'Can\'t find what you\'re looking for? Find out if others share your experience.', 'wporg-forums' ); ?> <a href="<?php echo esc_url( site_url( '/forums/' ) ); ?>"><?php esc_html_e( 'Check out our support forums', 'wporg-forums' ); ?></a>.
					</p>
				</div>

			<?php else : ?>

				<section>

					<?php echo do_blocks(
						'<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%"}} -->
						<div class="wp-block-group is-style-cards-grid">

							<!-- wp:group {"layout":{"type":"constrained"}} -->
							<div class="wp-block-group">

								<!-- wp:heading -->
								<h2 class="wp-block-heading has-heading-5-font-size">' . __( 'Welcome to Support', 'wporg-forums' ) . '</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>' . __( 'Our community-based Support Forums are a great place to learn, share, and troubleshoot.', 'wporg-forums' ) . '</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>' . __( '<a href="https://wordpress.org/support/welcome/">Get started</a>', 'wporg-forums' ) . '</p>
								<!-- /wp:paragraph -->

							</div>
							<!-- /wp:group -->

							<!-- wp:group {"layout":{"type":"constrained"}} -->
							<div class="wp-block-group">

								<!-- wp:heading -->
								<h2 class="wp-block-heading has-heading-5-font-size">' . __( 'Documentation', 'wporg-forums' ) . '</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>' . __( 'Your first stop where you\'ll find information on everything from installing to creating plugins.', 'wporg-forums' ) . '</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>' . __( '<a href="https://wordpress.org/documentation/">Explore documentation</a>', 'wporg-forums' ) . '</p>
								<!-- /wp:paragraph -->

							</div>
							<!-- /wp:group -->

							<!-- wp:group {"layout":{"type":"constrained"}} -->
							<div class="wp-block-group">

								<!-- wp:heading -->
								<h2 class="wp-block-heading has-heading-5-font-size">' . __( 'Get Involved', 'wporg-forums' ) . '</h2>
								<!-- /wp:heading -->

								<!-- wp:paragraph -->
								<p>' . __( 'The Support Handbook is great for tips, tricks, and advice regarding giving the best support possible.', 'wporg-forums' ) . '</p>
								<!-- /wp:paragraph -->

								<!-- wp:paragraph -->
								<p>' . __( '<a href="https://make.wordpress.org/support/handbook/">Explore the Handbook</a>', 'wporg-forums' ) . '</p>
								<!-- /wp:paragraph -->

							</div>
							<!-- /wp:group -->

						</div>
						<!-- /wp:group -->'
					); ?>

				</section>

				<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>

			<?php endif; ?>

		</div>

	</main>

<?php
get_footer();

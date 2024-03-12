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

				<section class="three-up" id="forum-welcome">
					<div>
						<div class="info-box">
							<span class="dashicons <?php
								/* translators: dashicon class name for 'Welcome to Support' section. Do not translate into your own language. */
								esc_attr_e( 'dashicons-sos', 'wporg-forums' );
							?>"></span>
							<h3><?php _e( 'Welcome to Support', 'wporg-forums' ); ?></h3>
							<p><?php _e( 'Our community-based Support Forums are a great place to learn, share, and troubleshoot.', 'wporg-forums' ); ?></p>
							<p><?php _e( '<a href="https://wordpress.org/support/welcome/">Get started</a>', 'wporg-forums' ); ?></p>
						</div>
					</div>
					<div>
						<div class="info-box">
							<span class="dashicons <?php
								/* translators: dashicon class name for 'Documentation' section. Do not translate into your own language. */
								esc_attr_e( 'dashicons-portfolio', 'wporg-forums' );
							?>"></span>
							<h3><?php _e( 'Documentation', 'wporg-forums' ); ?></h3>
							<p><?php _e( 'Your first stop where you\'ll find information on everything from installing to creating plugins.', 'wporg-forums' ); ?></p>
							<p><?php _e( '<a href="https://wordpress.org/support/">Explore documentation</a>', 'wporg-forums' ); ?></p>
						</div>
					</div>
					<div>
						<div class="info-box">
							<span class="dashicons <?php
								/* translators: dashicon class name for 'Get Involved' section. Do not translate into your own language. */
								esc_attr_e( 'dashicons-hammer', 'wporg-forums' );
							?>"></span>
							<h3><?php _e( 'Get Involved', 'wporg-forums' ); ?></h3>
							<p><?php _e( 'The Support Handbook is great for tips, tricks, and advice regarding giving the best support possible.', 'wporg-forums' ); ?></p>
							<p><?php _e( '<a href="https://make.wordpress.org/support/handbook/">Explore the Handbook</a>', 'wporg-forums' ); ?></p>
						</div>
					</div>
				</section>

				<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>

			<?php endif; ?>

		</div>

	</main>

<?php
get_footer();

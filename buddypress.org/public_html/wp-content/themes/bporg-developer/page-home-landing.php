<?php
/**
 * The template for displaying the Code Reference landing page.
 *
 * Template Name: Home
 *
 * @package bporg-developer
 * @since 1.0.0
 */

get_header(); ?>

	<div id="primary" class="content-area">

		<div class="home-landing">

			<div class="inner-wrap section">

				<div class="box box-code-ref">
					<h3 class="widget-title"><div class="dashicons dashicons-editor-code"></div><?php esc_html_e( 'Code Reference', 'bporg-developer' ); ?></h3>
					<p class="widget-description"><?php esc_html_e( 'Looking for documentation for the BuddyPress codebase?', 'bporg-developer' ); ?></p>
					<a href="<?php echo esc_url( home_url( '/reference' ) ); ?>"><?php esc_html_e( 'Visit the reference', 'bporg-developer' ); ?></a>
				</div>

				<div class="box box-rest-api">
					<h3 class="widget-title"><div class="dashicons dashicons-rest-api"></div><?php esc_html_e( 'BP REST API', 'bporg-developer' ); ?></h3>
					<p class="widget-description"><?php esc_html_e( 'Getting started on making BuddyPress applications?', 'bporg-developer' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'bp-rest-api-handbook' ) ); ?>"><?php esc_html_e( 'Make Applications ', 'bporg-developer' ); ?></a>
                </div>

                <div class="box box-contribute">
					<h3 class="widget-title"><div class="dashicons dashicons-buddicons-buddypress-logo"></div><?php esc_html_e( 'Contribute', 'bporg-developer' ); ?></h3>
					<p class="widget-description"><?php esc_html_e( 'Help Make BuddyPress', 'bporg-developer' ); ?></p>
					<a href="https://buddypress.trac.wordpress.org"><?php esc_html_e( 'Visit BuddyPress Trac', 'bporg-developer' ); ?></a>
				</div>

			</div>

			<div class="search-guide inner-wrap section">

				<?php if ( is_active_sidebar( 'landing-footer-1') ) { ?>
					<?php dynamic_sidebar( 'landing-footer-1' ); ?>
				<?php } else { ?>
					<div class=" box"></div>
				<?php } ?>

				<div class="box">
					<h3 class="widget-title"><?php esc_html_e( 'Developer news', 'bporg-developer' ); ?></h3>
					<div class="widget-text">
						<p>
							<a href="https://bpdevel.wordpress.com/"><?php esc_html_e( 'Follow the development team’s blog.', 'bporg-developer' ); ?></a>
						</p>
					</div>
				</div>

				<?php if ( is_active_sidebar( 'landing-footer-2') ) { ?>
					<?php dynamic_sidebar( 'landing-footer-2' ); ?>
				<?php } else { ?>
					<div class=" box"></div>
				<?php } ?>

			</div>

		</div><!-- /home-landing -->
	</div><!-- #primary -->

<?php get_footer(); ?>


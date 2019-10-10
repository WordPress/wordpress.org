<?php
/**
 * The template for displaying the Code Reference landing page.
 *
 * Template Name: Home
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="primary" class="content-area">

		<div class="home-landing">

			<div class="inner-wrap section">

				<div class="box box-code-ref">
					<h3 class="widget-title"><div class="dashicons dashicons-editor-code"></div><?php _e( 'Code Reference', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Looking for documentation for the codebase?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( home_url( '/reference' ) ); ?>" class="go"><?php _e( 'Visit the reference', 'wporg' ); ?></a>
				</div>

				<div class="box box-coding-standards">
					<h3 class="widget-title"><div class="dashicons dashicons-code-standards"></div><?php _e( 'Coding Standards', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Looking to ensure your code meets the standards?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'wpcs-handbook' ) ); ?>" class="go"><?php _e( 'Follow Standards ', 'wporg' ); ?></a>
				</div>

				<div class="box box-block-editor">
					<h3 class="widget-title"><div class="dashicons dashicons-edit"></div><?php _e( 'Block Editor', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Creating the building blocks of WordPress?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'blocks-handbook' ) ); ?>" class="go"><?php _e( 'Build Blocks ', 'wporg' ); ?></a>
				</div>

				<div class="box box-apis">
					<h3 class="widget-title"><div class="dashicons dashicons-admin-site-alt3"></div><?php _e( 'Common APIs', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Interested in interacting with various APIs?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'apis-handbook' ) ); ?>" class="go"><?php _e( 'Utilize APIs', 'wporg' ); ?></a>
				</div>

				<div class="box box-themes">
					<h3 class="widget-title"><div class="dashicons dashicons-admin-appearance"></div><?php _e( 'Themes', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Want to learn how to start theming WordPress?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'theme-handbook' ) ); ?>" class="go"><?php _e( 'Develop Themes ', 'wporg' ); ?></a>
				</div>

				<div class="box box-plugins">
					<h3 class="widget-title"><div class="dashicons dashicons-admin-plugins"></div><?php _e( 'Plugins', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Ready to dive deep into the world of plugin authoring?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'plugin-handbook' ) ); ?>" class="go"><?php _e( 'Develop Plugins ', 'wporg' ); ?></a>
				</div>

				<div class="box box-rest-api">
					<h3 class="widget-title"><div class="dashicons dashicons-rest-api"></div><?php _e( 'REST API', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Getting started on making WordPress applications?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'rest-api-handbook' ) ); ?>" class="go"><?php _e( 'Make Applications ', 'wporg' ); ?></a>
				</div>

				<div class="box box-wp-cli">
					<h3 class="widget-title"><div class="dashicons dashicons-arrow-right-alt2"></div><?php _e( 'WP-CLI', 'wporg' ); ?></h3>
					<p class="widget-description"><?php _e( 'Want to accelerate your workflow managing WordPress?', 'wporg' ); ?></p>
					<a href="<?php echo esc_url( get_post_type_archive_link( 'command' ) ); ?>" class="go"><?php _e( 'Run Commands ', 'wporg' ); ?></a>
				</div>

			</div>

			<div class="search-guide inner-wrap section">

				<?php if ( is_active_sidebar( 'landing-footer-1') ) { ?>
					<?php dynamic_sidebar( 'landing-footer-1' ); ?>
				<?php } else { ?>
					<div class=" box"></div>
				<?php } ?>

				<div class="box">
					<h3 class="widget-title"><?php _e( 'Contribute', 'wporg' ); ?></h3>
					<ul class="unordered-list no-bullets">
						<li><a href="https://make.wordpress.org/" class="make-wp-link"><?php _e( 'Help Make WordPress', 'wporg' ); ?></a></li>
					</ul>
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


<?php
/**
 * The template for displaying the Code Reference landing page.
 *
 * Template Name: Reference
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="primary" class="content-area">

		<div id="content-area">
			<?php breadcrumb_trail(); ?>
		</div>

		<main id="main" class="site-main" role="main">
			<div class="reference-landing">
				<div class="search-guide section clear">
					<h4 class="ref-intro"><?php _e( 'Want to know what&#39;s going on inside WordPress? Search the Code Reference for more information about WordPress&#39; functions, classes, methods, and hooks.', 'wporg' ); ?></h4>
					<h3 class="search-intro"><?php _e( 'Try it out:', 'wporg' ); ?></h3>
					<?php get_search_form(); ?>
				</div><!-- /search-guide -->

				<div class="topic-guide section">
					<h4><?php _e( 'Or browse through topics:', 'wporg' ); ?></h4>
					<ul class="unordered-list horizontal-list no-bullets">
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-function' ) ?>"><?php _e( 'Functions', 'wporg' ); ?></a></li>
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-hook' ) ?>"><?php _e( 'Hooks', 'wporg' ); ?></a></li>
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-class' ) ?>"><?php _e( 'Classes', 'wporg' ); ?></a></li>
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-method' ) ?>"><?php _e( 'Methods', 'wporg' ); ?></a></li>
					</ul>
				</div><!-- /topic-guide -->

				<div class="new-in-guide section two-columns clear">
					<div class="widget box gray">
					<?php $version = DevHub\get_current_version_term(); ?>
					<?php if ( $version && ! is_wp_error( $version ) ) : ?>
						<h3 class="widget-title"><?php printf( __( 'New &amp; Updated in WordPress %s:', 'wporg' ), substr( $version->name, 0, -2 ) ); ?></h3>
						<div class="widget-content">
							<ul class="unordered-list no-bullets">
								<?php

								$list = new WP_Query( array(
									'posts_per_page' => 15,
									'post_type'      => DevHub\get_parsed_post_types(),
									'orderby'        => 'title',
									'order'          => 'ASC',
									'tax_query'      => array( array(
										'taxonomy' => 'wp-parser-since',
										'field'    => 'ids',
										'terms'    => $version->term_id,
									) ),
								) );

								while ( $list->have_posts() ) : $list->the_post();
								?>

									<li>
										<a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
											<?php the_title(); ?>
										</a>
									</li>

								<?php endwhile; ?>
								<li class="view-all-new-in"><a href="<?php echo esc_attr( get_term_link( $version, 'wp-parser-since' ) ); ?>"><?php _e( 'View all&hellip;', 'wporg' ); ?></a></li>
							</ul>
						</div>
					<?php endif; ?>
					</div>
					<div class="widget box gray">
						<h3 class="widget-title"><?php _e( 'API', 'wporg' ); ?></h3>
						<div class="widget-content">
						<?php

						if ( has_nav_menu( 'reference-home-api' ) ) :
							wp_nav_menu(
								[
									'theme_location' => 'reference-home-api',
									'menu_class'     => 'unordered-list no-bullets',
								]
							);
						else:
						?>

							<ul class="unordered-list no-bullets">
								<li><a href="https://codex.wordpress.org/Dashboard_Widgets_API"><?php _e( 'Dashboard widgets', 'wporg'); ?></a></li>
								<li><a href="https://codex.wordpress.org/Database_API"><?php _e( 'Database', 'worg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/HTTP_API"><?php _e( 'HTTP API', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Filesystem_API"><?php _e( 'Filesystem', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Global_Variables"><?php _e( 'Global Variables', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Metadata_API"><?php _e( 'Metadata', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Options_API"><?php _e( 'Options', 'wporg' ); ?></a></li>
								<li><a href="https://developer.wordpress.org/plugins/"><?php _e( 'Plugins', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Quicktags_API"><?php _e( 'Quicktags', 'wporg' ); ?></a></li>
								<li><a href="https://developer.wordpress.org/rest-api/"><?php _e( 'REST API', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Rewrite_API"><?php _e( 'Rewrite', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Settings_API"><?php _e( 'Settings', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Shortcode_API"><?php _e( 'Shortcode', 'wporg' ); ?></a></li>
								<li><a href="https://developer.wordpress.org/themes/"><?php _e( 'Theme Modification', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/Transients_API"><?php _e( 'Transients', 'wporg' ); ?></a></li>
								<li><a href="https://codex.wordpress.org/XML-RPC_WordPress_API"><?php _e( 'XML-RPC', 'wporg' ); ?></a></li>
							</ul>
						<?php endif; ?>

						</div>
					</div>
				</div><!-- /new-in-guide -->

			</div><!-- /reference-landing -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>

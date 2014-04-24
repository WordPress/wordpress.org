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
		<main id="main" class="site-main" role="main">

			<div class="reference-landing">
				<div class="search-guide section clear">
					<h4 class="ref-intro"><?php _e( 'Want to know what&#39;s going on inside WordPress? Search the Code Reference for more information about WordPress&#39; functions, classes, methods, hooks, and filters.', 'wporg' ); ?></h4>
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
						<h3 class="widget-title"><?php $version = DevHub\get_current_version(); printf( __( 'New in WordPress %s:', 'wporg' ), $version->name ); ?></h3>
						<div class="widget-content">
							<ul class="unordered-list no-bullets">
								<?php

								$list = new WP_Query( array(
									'posts_per_page' => 10,
									'post_type'      => array( 'wp-parser-function', 'wp-parser-hook', 'wp-parser-class', 'wp-parser-method' ),
									'orderby'        => 'title',
									'order'          => 'ASC',
									'tax_query'      => array( array(
										'taxonomy' => 'wp-parser-since',
										'field'    => 'ids',
										'terms'    => $version->term_id,
									) ),
								) );

								while ( $list->have_posts() ) : $list->the_post();

									echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';

								endwhile;
								?>
							</ul>
						</div>
					</div>
					<div class="widget box gray">
						<h3 class="widget-title"><?php _e( 'API', 'wporg' ); ?></h3>
						<div class="widget-content">
							<ul class="unordered-list no-bullets">
								<li><a href="https://codex.wordpress.org/Dashboard_Widgets_API">Dashboard widgets</a></li>
								<li><a href="https://codex.wordpress.org/Database_API">Database</a></li>
								<li><a href="https://codex.wordpress.org/HTTP_API">HTTP API</a></li>
								<li><a href="https://codex.wordpress.org/Filesystem_API">Filesystem</a></li>
								<li><a href="https://codex.wordpress.org/Metadata_API">Metadata</a></li>
								<li><a href="https://codex.wordpress.org/Options_API">Options</a></li>
								<li><a href="https://codex.wordpress.org/Plugin_API">Plugins</a></li>
								<li><a href="https://codex.wordpress.org/Quicktags_API">Quicktags</a></li>
								<li><a href="https://codex.wordpress.org/Rewrite_API">Rewrite</a></li>
								<li><a href="https://codex.wordpress.org/Rewrite_API">Settings</a></li>
								<li><a href="https://codex.wordpress.org/Shortcode_API">Shortcode</a></li>
								<li><a href=""https://codex.wordpress.org/Shortcode_API"https://codex.wordpress.org/Theme_Modification_API">Theme Modification</a></li>
								<li><a href="https://codex.wordpress.org/Transients_API">Transients</a></li>
								<li><a href="https://codex.wordpress.org/XML-RPC_WordPress_API">XML-RPC</a></li>
								
							</ul>
						</div>
					</div>
				</div><!-- /new-in-guide -->

			</div><!-- /reference-landing -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>

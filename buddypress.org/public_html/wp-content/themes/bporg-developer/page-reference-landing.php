<?php
/**
 * The template for displaying the Code Reference landing page.
 *
 * Template Name: Reference
 *
 * @package bporg-developer
 * @since 1.0.0
 */

get_header(); ?>

	<div id="primary" class="content-area">

		<div id="content-area">
			<?php breadcrumb_trail(); ?>
		</div>

		<main id="main" class="site-main" role="main">
			<div class="reference-landing">
				<div class="search-guide section clear">
					<h4 class="ref-intro"><?php _e( 'Want to know what&#39;s going on inside BuddyPress? Search the Code Reference for more information about BuddyPress&#39; functions, classes, methods, and hooks.', 'bporg-developer' ); ?></h4>
					<h3 class="search-intro"><?php _e( 'Try it out:', 'bporg-developer' ); ?></h3>
					<?php get_search_form(); ?>
				</div><!-- /search-guide -->

				<div class="topic-guide section">
					<h4><?php _e( 'Or browse through topics:', 'bporg-developer' ); ?></h4>
					<ul class="unordered-list horizontal-list no-bullets">
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-function' ) ?>"><?php _e( 'Functions', 'bporg-developer' ); ?></a></li>
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-hook' ) ?>"><?php _e( 'Hooks', 'bporg-developer' ); ?></a></li>
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-class' ) ?>"><?php _e( 'Classes', 'bporg-developer' ); ?></a></li>
						<li><a href="<?php echo get_post_type_archive_link( 'wp-parser-method' ) ?>"><?php _e( 'Methods', 'bporg-developer' ); ?></a></li>
					</ul>
				</div><!-- /topic-guide -->

				<div class="new-in-guide section two-columns clear">
                    <?php $version = DevHub\bporg_developer_get_current_version_term(); ?>
					<?php if ( $version && ! is_wp_error( $version ) ) : ?>
                        <div class="widget box gray">
                            <h3 class="widget-title"><?php printf( __( 'New &amp; Updated in BuddyPress %s:', 'bporg-developer' ), substr( $version->name, 0, -2 ) ); ?></h3>
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

                                    while ( $list->have_posts() ) : $list->the_post(); ?>

                                        <li>
                                            <a href="<?php the_permalink(); ?>" title="<?php the_title_attribute(); ?>">
                                                <?php the_title(); ?>
                                            </a>
                                        </li>

                                    <?php endwhile; ?>
                                    <li class="view-all-new-in"><a href="<?php echo esc_attr( get_term_link( $version, 'wp-parser-since' ) ); ?>"><?php _e( 'View all&hellip;', 'bporg-developer' ); ?></a></li>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    <?php if ( has_nav_menu( 'reference-home-api' ) ) : ?>
                        <div class="widget box gray">
                            <h3 class="widget-title"><?php _e( 'API', 'bporg-developer' ); ?></h3>
                            <div class="widget-content">
                                <?php wp_nav_menu(
                                        [
                                            'theme_location' => 'reference-home-api',
                                            'menu_class'     => 'unordered-list no-bullets',
                                        ]
                                    );
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
				</div><!-- /new-in-guide -->

			</div><!-- /reference-landing -->

		</main><!-- #main -->
	</div><!-- #primary -->

<?php get_footer(); ?>

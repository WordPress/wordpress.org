<?php
/**
 * The template for displaying the Code Reference landing page.
 *
 * Template Name: Home
 *
 * @package wporg-developer
 */

// Temporarily redirect to reference until other section become live, justifying a main landing page.
if ( ! is_user_member_of_blog() ) {
	wp_redirect( get_permalink( get_page_by_path( 'reference' ) ) );
	exit();
}

get_header(); ?>

	<div id="primary" class="content-area">

		<div class="home-landing">

			<div class="handbook-banner section blue clear color">
				<div class="inner-wrap two-columns">
					<div class="widget box box-left transparent">
						<h3 class="widget-title"><div class="dashicons dashicons-welcome-widgets-menus"></div><?php _e( 'Themes', 'wporg' ); ?></h3>
						<p class="widget-description"><?php _e( 'Want to know all there is to know about theming and WordPress?', 'wporg' ); ?></p>
						<a href="<?php esc_attr_e( get_post_type_archive_link( 'theme-handbook' ) ); ?>" class="themes-go get-started go button"><?php _e( 'Develop Themes ', 'wporg' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a>
					</div>
					<div class="widget box box-right transparent">
						<h3 class="widget-title"><div class="dashicons dashicons-admin-plugins"></div><?php _e( 'Plugins', 'wporg' ); ?></h3>
						<p class="widget-description"><?php _e( 'Ready to dive deep into the world of plugin authoring?', 'wporg' ); ?></p>
						<a href="<?php esc_attr_e( get_post_type_archive_link( 'plugin-handbook' ) ); ?>" class="plugins-go get-started go button"><?php _e( 'Develop Plugins ', 'wporg' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a>
					</div>
				</div>
			</div><!-- /topic-guide -->

			<div class="code-reference-banner section gray clear color">
				<div class="inner-wrap">
					<div class="widget transparent">
						<div class="code-ref-left">
							<h3 class="widget-title"><div class="dashicons dashicons-editor-alignleft"></div><?php _e( ' Code Reference', 'wporg' ); ?></h3>
							<p class="widget-description"><?php _e( 'Search the codebase for documentation', 'wporg' ); ?></p>
						</div>
						<div class="code-ref-right">
							<a href="<?php echo home_url( '/reference' ); ?>" class="codex-go go button"><?php _e( 'Visit the Reference ', 'wporg' ); ?><span class="dashicons dashicons-arrow-right-alt2"></span></a>
						</div>
					</div>
				</div>
			</div><!-- /new-in-guide -->

<?php /*
			<main id="main" class="site-main section" role="main">

					<?php while ( have_posts() ) : the_post(); ?>

						<div id="post-<?php the_ID(); ?>" class="home-primary-content">
							<header class="entry-header">
								<h1 class="entry-title"><?php the_title(); ?></h1>
							</header><!-- .entry-header -->

							<div class="entry-content">
								<?php the_content(); ?>
								<?php
									wp_link_pages( array(
										'before' => '<div class="page-links">' . __( 'Pages:', 'wporg' ),
										'after'  => '</div>',
									) );
								?>
							</div><!-- .entry-content -->
							<?php edit_post_link( __( 'Edit', 'wporg' ), '<footer class="entry-meta"><span class="edit-link">', '</span></footer>' ); ?>
						</div><!-- #post-## -->

					<?php endwhile; // end of the loop. ?>

			</main><!-- #main -->
*/ ?>

			<div class="search-guide section light-gray clear">
				<div class="inner-wrap three-columns">
					<div class="widget box">
						<h4 class="widget-title"><?php _e( 'Need Help?', 'devhub' ); ?></h4>
						<ul class="unordered-list no-bullets">
							<li><a href="#">WPHackers Mailing List</a></li>
							<li><a href="#">WordPress Stack Exchange</a></li>
							<li><a href="#">Core Contributor Handbook</a></li>
						</ul>
					</div>
					<div class="widget box">
						<h4 class="widget-title"><?php _e( 'More Resources', 'devhub' ); ?></h4>
						<ul class="unordered-list no-bullets">
							<li><a href="#">Intro To WordPress Development</a></li>
							<li><a href="#">Setting Up Your Development Environment</a></li>
							<li><a href="#">WordPress Coding Standards</a></li>
						</ul>
					</div>
					<div class="widget box">
						<h4 class="widget-title"><a href="#"><?php _e( 'Help Make WordPress ', 'devhub' ); ?><div class="dashicons dashicons-arrow-right-alt2"></div></a></h4>
					</div>
				</div>
			</div><!-- /search-guide -->

		</div><!-- /home-landing -->
	</div><!-- #primary -->

<?php get_footer(); ?>

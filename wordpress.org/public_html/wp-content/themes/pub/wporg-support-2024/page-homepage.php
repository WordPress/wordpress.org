<?php
/**
 * Template Name: bbPress - Homepage
 *
 * @package bbPress
 * @subpackage Theme
 */

/**
 * Adds a custom description meta tag.
 */
add_action( 'wp_head', function() {
	printf( '<meta name="description" content="%s" />' . "\n", esc_attr__( 'Our community support articles are the best place to get the most out of WordPress. Learn how to set up your website, troubleshoot problems, customize your site, and more.', 'wporg-forums' ) );
} );

get_header(); ?>

	<main id="main" class="wp-block-group alignfull site-main is-layout-constrained wp-block-group-is-layout-constrained" role="main">

		<div class="wp-block-group alignwide is-layout-flow wp-block-group-is-layout-flow">

		<?php do_action( 'bbp_before_main_content' ); ?>

		<?php do_action( 'bbp_template_notices' ); ?>

		<section id="forum-welcome">
			<?php echo do_blocks( '<!-- wp:pattern {"slug":"wporg-support/welcome-cards"} /-->' ); ?>
		</section>

		<section>
			<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>
		</section>

		<section class="forums-homepage-topics">
			<h2 class="has-heading-5-font-size"><?php _e( 'Topics', 'wporg-forums' ); ?></h2>

			<?php echo do_blocks( '<!-- wp:pattern {"slug":"wporg-support/forums-views"} /-->' ); ?>
		</section>

		<section class="clear helpful-links">
			<div>
				<h2 class="has-heading-5-font-size"><?php _e( 'Helpful Links', 'wporg-forums' ); ?></h2>
				<ul class="meta-list">
					<li><?php _e( '<a href="https://wordpress.org/support/article/new_to_wordpress_-_where_to_start/">New to WordPress &mdash; Where to Start</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://wordpress.org/support/article/faq-installation/">Frequently Asked Questions about Installing WordPress</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://wordpress.org/support/article/first-steps-with-wordpress-classic/">First Steps with WordPress</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://wordpress.org/support/article/writing-posts/">Writing Posts</a>', 'wporg-forums' ); ?></li>
				</ul>
			</div>
		</section>

		<?php do_action( 'bbp_after_main_content' ); ?>

	</div>

</main>


<?php get_footer();

<?php
/**
 * Template Name: bbPress - Support (Index)
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


	<main id="main" class="site-main" role="main">

		<?php do_action( 'bbp_before_main_content' ); ?>

		<?php do_action( 'bbp_template_notices' ); ?>

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

		<hr />

		<section>
			<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>

			<div id="viewdiv">
				<ul id="views">
					<?php wporg_support_get_views(); ?>
				</ul>
			</div><!-- #viewdiv -->
		</section>

		<hr />

		<section class="clear helpful-links">
			<div>
				<h3><?php _e( 'Helpful Links', 'wporg-forums' ); ?></h3>
				<ul class="meta-list">
					<li><?php _e( '<a href="https://wordpress.org/support/article/new_to_wordpress_-_where_to_start/">New to WordPress &mdash; Where to Start</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://wordpress.org/support/article/faq-installation/">Frequently Asked Questions about Installing WordPress</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://wordpress.org/support/article/first-steps-with-wordpress-classic/">First Steps with WordPress</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://wordpress.org/support/article/writing-posts/">Writing Posts</a>', 'wporg-forums' ); ?></li>
				</ul>
			</div>
		</section>

		<?php do_action( 'bbp_after_main_content' ); ?>

	</main>


<?php get_footer();

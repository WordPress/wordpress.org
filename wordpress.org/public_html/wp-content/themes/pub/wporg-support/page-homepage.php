<?php

/**
 * Template Name: bbPress - Support (Index)
 *
 * @package bbPress
 * @subpackage Theme
 */

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
					<p><?php _e( '<a href="https://make.wordpress.org/support/handbook/forum-welcome/">Get started</a>', 'wporg-forums' ); ?></p>
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
					<p><?php _e( '<a href="https://codex.wordpress.org/">Explore documentation</a>', 'wporg-forums' ); ?></p>
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
				<h3><?php _e( 'Hot Topics', 'wporg-forums' ); ?></h3>
				<p class="frontpageheatmap">
					<?php wp_tag_cloud( array( 'smallest' => 14, 'largest' => 24, 'number' => 22, 'taxonomy' => bbp_get_topic_tag_tax_id() ) ); ?>
				</p>
			</div>
			<div>
				<h3><?php _e( 'Helpful Links', 'wporg-forums' ); ?></h3>
				<ul class="meta-list">
					<li><?php _e( '<a href="https://codex.wordpress.org/New_To_WordPress_-_Where_to_Start">New to WordPress &mdash; Where to Start</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://codex.wordpress.org/FAQ_Installation">Frequently Asked Questions about Installing WordPress</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://codex.wordpress.org/First_Steps_With_WordPress">First Steps with WordPress</a>', 'wporg-forums' ); ?></li>
					<li><?php _e( '<a href="https://codex.wordpress.org/Writing_Posts">Writing Posts</a>', 'wporg-forums' ); ?></li>
				</ul>
			</div>
		</section>

		<?php do_action( 'bbp_after_main_content' ); ?>

	</main>


<?php get_footer();

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
					<span class="dashicons dashicons-sos"></span>
					<h3>Welcome to Support</h3>
					<p>Our community-based Support Forums are a great place to learn, share, and troubleshoot.</p>
					<p><a href="https://codex.wordpress.org/Getting_Started_with_WordPress">Start learning</a></p>
				</div>
			</div>
			<div>
				<div class="info-box">
					<span class="dashicons dashicons-portfolio"></span>
					<h3>Documentation</h3>
					<p>Your first stop where you'll find information on everything from installing to creating plugins.</p>
					<p><a href="https://codex.wordpress.org/">Explore documentation</a></p>
				</div>
			</div>
			<div>
				<div class="info-box">
					<span class="dashicons dashicons-hammer"></span>
					<h3>Get Involved</h3>
					<p>The Support Handbook is great for tips, tricks, and advice regarding giving the best support possible.</p>
					<p><a href="https://make.wordpress.org/support/handbook/">Explore the Handbook</a></p>
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

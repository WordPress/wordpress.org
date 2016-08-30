<?php

/**
 * Template Name: bbPress - Support (Index)
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

<div id="pagebody">
	<div class="wrapper">

		<?php do_action( 'bbp_before_main_content' ); ?>

		<?php do_action( 'bbp_template_notices' ); ?>

		<div class="col-6" id="forum-welcome">

			<p class="intro"><?php _e( 'We&rsquo;ve got a variety of resources to help you get the most out of WordPress. Your first stop should be our <a href="https://codex.wordpress.org">documentation</a>, where you&rsquo;ll find information on everything from installing WordPress for the first time to creating your own themes&nbsp;and&nbsp;plugins.', 'wporg-forums' ); ?></p>
			<h3><?php _e( 'Getting Started Resources', 'wporg-forums' ); ?></h3>
			<p><?php _e( 'If you need help getting started with WordPress, try these articles.', 'wporg-forums' ); ?></p>
			<ul>
				<li><?php _e( '<a href="https://codex.wordpress.org/Forum_Welcome">Welcome to the WordPress Support Forum</a>', 'wporg-forums' ); ?></li>
				<li><?php _e( '<a href="https://codex.wordpress.org/New_To_WordPress_-_Where_to_Start">New to WordPress &mdash; Where to Start</a>', 'wporg-forums' ); ?></li>
				<li><?php _e( '<a href="https://codex.wordpress.org/FAQ_Installation">Frequently Asked Questions about Installing WordPress</a>', 'wporg-forums' ); ?></li>
				<li><?php _e( '<a href="https://codex.wordpress.org/First_Steps_With_WordPress">First Steps with WordPress</a>', 'wporg-forums' ); ?></li>
				<li><?php _e( '<a href="https://codex.wordpress.org/Writing_Posts">Writing Posts</a>', 'wporg-forums' ); ?></li>
				<li><?php _e( '<a href="https://make.wordpress.org/support/handbook/">Support Handbook</a>', 'wporg-forums' ); ?></li>
			</ul>
			<h3><?php _e( 'Search the Support Forums', 'wporg-forums' ); ?></h3>
			<p><?php _e( 'Enter a few words that describe the problem you&rsquo;re having.', 'wporg-forums' ); ?></p>
			<?php bbp_get_template_part( 'form', 'search' ); ?>
			<h3><?php _e( 'Hot Topics', 'wporg-forums' ); ?></h3>
			<p class="frontpageheatmap">
				<?php wp_tag_cloud( array( 'smallest' => 14, 'largest' => 24, 'number' => 22, 'taxonomy' => bbp_get_topic_tag_tax_id() ) ); ?>
			</p>
		</div><!-- #forum-welcome -->
		<div class="col-6">

			<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>

			<div id="viewdiv">
				<ul id="views">
					<?php wporg_support_get_views(); ?>
				</ul>
			</div><!-- #viewdiv -->
		</div><!-- #col-6 -->

		<?php do_action( 'bbp_after_main_content' ); ?>

	</div><!-- #wrapper -->
</div><!-- #pagebody -->

<?php get_footer();

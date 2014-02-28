<?php

/**
 * Template Name: bbPress - Support (Index) page-front-support.php
 *
 * @package WPBBP
 */

get_header(); ?>
<header class="masthead">
	<hgroup class="hentry wrap wporg-support">
		<h5><?php _e('We&rsquo;ve got a variety of resources to help you get the most out of WordPress. Your first stop should be our <a href="http://codex.wordpress.org">documentation</a>, where you&rsquo;ll find information on everything from installing WordPress for the first time to creating your own themes&nbsp;and&nbsp;plugins.', 'wporg'); ?></h5>
		<h5><?php _e('If you need help getting started with WordPress, try these articles.', 'wporg'); ?>
			<ul>
				<li><?php _e('<a href="http://codex.wordpress.org/Forum_Welcome">Welcome to the WordPress Support Forum</a>', 'wporg'); ?></li>
				<li><?php _e('<a href="http://codex.wordpress.org/New_To_WordPress_-_Where_to_Start">New to WordPress &mdash; Where to Start</a>', 'wporg'); ?></li>
				<li><?php _e('<a href="http://codex.wordpress.org/FAQ_Installation">Frequently Asked Questions about Installing WordPress</a>', 'wporg'); ?></li>
				<li><?php _e('<a href="http://codex.wordpress.org/First_Steps_With_WordPress">First Steps with WordPress</a>', 'wporg'); ?></li>
				<li><?php _e('<a href="http://codex.wordpress.org/Writing_Posts">Writing Posts</a>', 'wporg'); ?></li>
			</ul>
		</h5>
	</hgroup><!-- .hentry .wrap .wporg-support -->
</header><!-- .masthead -->
<nav class="subhead">
	<div class="wrapper">
		<form role="search" method="get" action="<?php bbp_search_url(); ?>">
			<fieldset>
				<label class="screen-reader-text hidden" for="bbp_search"><?php _e( 'Search for:', 'wporg' ); ?></label>
				<input type="hidden" name="action" value="bbp-search-request" />
				<label for="forumsearchbox"><?php _e('Search the Support Forums', 'wporg'); ?></label>
				<input type="text" class="text" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" id="forumsearchbox" />
				<button type="submit" class="button button-primary"><?php esc_attr_e( 'Search', 'wporg' ); ?></button>
			</fieldset>
		</form>
	</div>
</nav>
<section id="bbpress-forums" class="wporg-support-forumlist">
	<div class="wrapper">
		<h2 class="section-title"><?php bbp_breadcrumb(); ?></h2>

		<article id="forums-id-tags" class="forums-id-tags">
			<h2><?php _e('Hot Topics', 'wporg'); ?></h2>
			<div class="tag-description">
				<p><?php wp_tag_cloud( array( 'smallest' => 12, 'largest' => 16, 'number' => 16, 'taxonomy' => bbp_get_topic_tag_tax_id() ) ); ?></p>
			</div><!-- . -->
		</article><!-- .forums-id-tags -->
		<article id="forums-id-views" class="forums-id-views">
			<h2><?php _e('Views', 'wporg'); ?></h2>
			<div class="view-description">
				<ul>

					<?php foreach ( array_keys( bbp_get_views() ) as $view ) : ?>

						<li>
							<p><a href="<?php bbp_view_url( $view ); ?>">- <?php bbp_view_title( $view ); ?> </a></p>
						</li>

					<?php endforeach; ?>

				</ul>
			</div><!-- . -->
		</article><!-- .forums-id-views -->
		<?php do_action( 'wporg_support_before_forumlist' ); ?>

		<?php if ( bbp_has_forums() ) : ?>

			<?php while ( bbp_forums() ) : bbp_the_forum(); ?>

				<article id="forums-list-<?php bbp_forum_id(); ?>" class="forums-id-<?php bbp_forum_id(); ?> bbp-forums">

					<h2 id="bbp-forum-<?php bbp_forum_id(); ?>"><a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>"><?php bbp_forum_title(); ?></a></h2>

					<div class="count-description">
						<p>
							<span><?php _e( 'Topics: ', 'wporg' ); ?><?php bbp_forum_topic_count(); ?></span>
							<span><?php _e( 'Posts: ', 'wporg' ); ?><?php bbp_forum_post_count(); ?></span>
						</p>
					</div>
					<small>
						<p class="bbp-forum-content"><?php bbp_forum_content(); ?></p>
					</small>

				</article><!-- . -->

			<?php endwhile; ?>

		<?php endif; ?>

		<?php do_action( 'wporg_support_after_forumlist' ); ?>

	</div><!-- .wrapper -->
</section><!-- #bbpress-forums .wporg-support-forumlist -->

<?php get_footer(); ?>

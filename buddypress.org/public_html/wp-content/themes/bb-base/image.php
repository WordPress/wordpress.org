<?php get_header(); ?>

<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
			<h2 id="post-<?php the_ID(); ?>"><a href="<?php echo get_permalink($post->post_parent); ?>" rev="attachment"><?php echo get_the_title($post->post_parent); ?></a> &raquo; <?php the_title(); ?></h2>
			<p class="attachment"><a href="<?php echo wp_get_attachment_url($post->ID); ?>"><?php echo wp_get_attachment_image( $post->ID, 'medium' ); ?></a></p>
			<p class="wp-caption"><?php if ( !empty($post->post_excerpt) ) the_excerpt(); // this is the "caption" ?></p>
<?php the_content('Read more &laquo;'); ?>
			<dl id="meta">
				<dt>Published on</dt>
				<dd><?php the_time('l, F jS, Y') ?> at <?php the_time() ?></dd>
				<dd>by <cite><?php the_author_posts_link(''); ?></cite></dd>
<?php the_tags("\t\t\t\t\t<dt>Tagged as</dt>\n\t\t\t\t\t<dd>", "</dd>\t\t\t\t\t<dd>", "</dd>\n"); ?>
				<dt>Categorized under</dt>
				<dd><?php the_category(', '); ?></dd>
				<dt>Feedback has</dt>
				<dd><?php comments_popup_link("not been left", "been left once", "been left % times", "", "been turned off"); ?></dd>
				<dt>Syndication through</dt>
				<dd><?php comments_rss_link('RSS 2.0'); ?></dd>
<?php if ('open' == $post->ping_status) { ?>
				<dt>Trackback from</dt>
					<dd><a href="<?php trackback_url(); ?>" rel="trackback">your own site</a></dd>
<?php }
 if ('open' == $post-> comment_status) { ?>
				<dt>Respond if</dt>
				<dd><a href="#respond">you'd like to leave feedback</a></dd>
<?php } edit_post_link("edit this publication", "\t\t\t\t\t<dt>You can</dt>\n\t\t\t\t\t<dd>", "</dd>\n"); ?>
			</dl>
			<hr class="hidden" />

			<h3>View Older or Newer Images</h3>
			<div class="navigation">
				<div class="alignleft"><?php previous_image_link() ?></div>
				<div class="alignright"><?php next_image_link() ?></div>
			</div>
			<hr class="hidden" />
<?php comments_template(); endwhile; else: ?>
			<h1>Whoops!</h1>
			<p><?php _e('Sorry, no images matched your criteria.'); ?></p>
<?php endif; get_footer(); ?>
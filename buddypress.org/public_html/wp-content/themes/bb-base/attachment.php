<?php get_header(); ?>
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
<?php $attachment_link = get_the_attachment_link($post->ID, true, array(450, 800)); // This also populates the iconsize for the next line ?>
<?php $_post = &get_post($post->ID); $classname = ($_post->iconsize[0] <= 128 ? 'small' : '') . 'attachment'; // This lets us style narrow icons specially ?>
			<div class="post" id="post-<?php the_ID(); ?>">
				<h1><a href="<?php the_permalink() ?>" rel="bookmark" title="Permanent Link to <?php the_title_attribute(); ?>"><?php the_title(); ?></a></h1>
				<label class="date"><?php the_time('F jS, Y') ?></label>
				<p class="<?php echo esc_attr( $classname ); ?>"><?php echo $attachment_link; ?><br /><?php echo basename($post->guid); ?></p>
<?php the_content(__('Check it out!')); ?>
<?php wp_link_pages(array('before' => 'Pages: ', 'after' => '', 'next_or_number' => 'number')); ?>
				<p class="postmeta">
					This entry was posted on <?php the_time('l, F jS, Y') ?> at <?php the_time() ?> and is filed under <?php the_category(', ') ?>.
					You can follow any responses to this entry through the <?php comments_rss_link('RSS 2.0'); ?> feed.
<?php if (('open' == $post-> comment_status) && ('open' == $post->ping_status)) {
					// Both Comments and Pings are open ?>
					You can <a href="#respond">leave a response</a>, or <a href="<?php trackback_url(); ?>" rel="trackback">trackback</a> from your own site.
<?php } elseif (!('open' == $post-> comment_status) && ('open' == $post->ping_status)) {
					// Only Pings are Open ?>
					Responses are currently closed, but you can <a href="<?php trackback_url(); ?> " rel="trackback">trackback</a> from your own site.
<?php } elseif (('open' == $post-> comment_status) && !('open' == $post->ping_status)) {
					// Comments are open, Pings are not ?>
					You can skip to the end and leave a response. Pinging is currently not allowed.
<?php } elseif (!('open' == $post-> comment_status) && !('open' == $post->ping_status)) {
					// Neither Comments, nor Pings are open ?>
					Both comments and pings are currently closed.
<?php } edit_post_link('Edit this entry.','',''); ?>
				</p>
			</div>
<?php comments_template(); ?>

<?php endwhile; else: ?>
			<h1>Whoops!</h1>
			<p class="error"><?php _e("Sorry friend, there's no attachments for you to see here."); ?></p>
<?php endif; ?>
<?php get_footer(); ?>
<?php
/**
 * Single Forum Content Part
 *
 * @package WPBBP
 */
?>
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

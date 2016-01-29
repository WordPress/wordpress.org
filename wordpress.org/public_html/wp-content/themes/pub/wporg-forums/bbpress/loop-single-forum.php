<ul id="bbp-forum-<?php bbp_forum_id(); ?>" <?php bbp_forum_class(); ?>>
	<li class="bbp-forum-info">
		<a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>"><?php bbp_forum_title(); ?></a>
		<br><?php bbp_forum_content(); ?>
	</li>
	<li class="bbp-forum-reply-count"><?php bbp_forum_post_count(); ?></li>
</ul><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->

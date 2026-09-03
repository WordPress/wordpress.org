<ul id="bbp-forum-<?php bbp_forum_id(); ?>" <?php bbp_forum_class(); ?>>
	<li class="bbp-forum-info">
		<a class="bbp-forum-title" href="<?php echo esc_url( bbp_get_forum_permalink() ); ?>" title="<?php echo esc_attr( bbp_get_forum_title() ); ?>"><?php echo esc_html( bbp_get_forum_title() ); ?></a>
	</li>
	<li class="bbp-forum-topic-count"><?php bbp_forum_post_count(); ?></li>
</ul><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->

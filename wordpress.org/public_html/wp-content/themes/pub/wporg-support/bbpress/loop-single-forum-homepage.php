<div id="bbp-forum-<?php bbp_forum_id(); ?>" <?php bbp_forum_class( bbp_get_forum_id(), array( '' ) ); ?>>
	
	<a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>"><h3><?php bbp_forum_title(); ?></h3></a>
	<p><?php bbp_forum_content(); ?></p>
	<p><a href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>" class="viewmore"><?php _e( 'View forum', 'wporg-forums' ); ?></a></p>

</div><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->

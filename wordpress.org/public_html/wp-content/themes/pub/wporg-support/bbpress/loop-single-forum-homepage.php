<a href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>" id="bbp-forum-<?php bbp_forum_id(); ?>" <?php bbp_forum_class( bbp_get_forum_id(), array( 'wp-block-wporg-link-wrapper', 'is-layout-flow', 'wp-block-wporg-link-wrapper-is-layout-flow' ) ); ?>>

	<h3 class="wp-block-heading has-inter-font-family has-normal-font-size"><?php bbp_forum_title(); ?></h3>

	<p><?php bbp_forum_content(); ?></p>

</a><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->

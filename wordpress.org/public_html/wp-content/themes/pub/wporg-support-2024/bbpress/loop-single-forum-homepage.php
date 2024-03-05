<div id="bbp-forum-<?php bbp_forum_id(); ?>" <?php bbp_forum_class( bbp_get_forum_id(), array( '' ) ); ?>>

	<a class="bbp-forum-title" href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>"><h3><?php bbp_forum_title(); ?></h3></a>

	<p><?php bbp_forum_content(); ?></p>

	<p>
		<?php $subforums = bbp_forum_get_subforums( bbp_get_forum_id() ); ?>

		<?php if ( $subforums ) : ?>

			<?php foreach ( $subforums as $subforum ) : ?>
				<a href="<?php bbp_forum_permalink( $subforum->ID ); ?>" title="<?php bbp_forum_title( $subforum->ID ); ?>" class="viewmore"><?php bbp_forum_title( $subforum->ID ); ?></a><br>
			<?php endforeach; ?>

		<?php endif; ?>

		<?php if ( ! bbp_is_forum_category() ) : ?>

			<a href="<?php bbp_forum_permalink(); ?>" title="<?php bbp_forum_title(); ?>" class="viewmore"><?php _e( 'View forum', 'wporg-forums' ); ?></a>

		<?php endif; ?>
	</p>

</div><!-- #bbp-forum-<?php bbp_forum_id(); ?> -->

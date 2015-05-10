<?php if ( $comments ) : ?>
<h2 id="commentheader" class="fancy"><?php _e('Comments', 'rosetta'); ?></h2>
<ol id="comments" class="commentlist">
	<?php foreach ($comments as $comment) : ?>
		<li id="comment-<?php comment_ID() ?>" class="comment">
			<div class="narrow">
				<span class="author"><?php comment_author_link(); ?></span>
				<span class="date"><?php comment_date(__('F j, Y', 'rosetta')) ?>,&nbsp;<?php comment_time(__('g:i a', 'rosetta')) ?></span>
				<span class="permlink"><a href="#comment-<?php comment_ID() ?>" title="<?php esc_attr_e('Permanent link to this comment', 'rosetta'); ?>">#</a></span>
				<?php edit_comment_link(__("Edit&nbsp;This", 'rosetta'), ''); ?>
			</div>
			<div class="wide">
				<?php comment_text() ?>
			</div>
		</li>
	<?php endforeach; ?>
</ol>
<?php endif; ?>

<?php if ( comments_open() ) : ?>

<form id="commentform" action="<?php echo get_option('siteurl'); ?>/wp-comments-post.php" method="post">
	<h3><?php _e('Add a Comment', 'rosetta'); ?></h3>

	<?php if ( $user_ID ) : ?>

	<div id="loggedin"><?php printf(__('Logged in as %s.', 'rosetta'), '<a href="' . admin_url( 'profile.php' ) .'">'.$user_identity.'</a>'); ?> <a href="<?php echo get_option('siteurl'); ?>/wp-login.php?action=logout" title="<?php esc_attr_e('Log out of this account.', 'rosetta') ?>"><?php _e('Logout &raquo;', 'rosetta'); ?></a></div>

	<?php else : ?>

	<div class="narrow">
		<input id="author" name="author" type="text" value="<?php echo esc_attr( $comment_author ) ?>" tabindex="1" />
		<label for="author"><?php _e('Name <em>(required)</em>', 'rosetta') ?></label>

		<input id="email" name="email" type="text" value="<?php echo esc_attr( $comment_author_email ) ?>" tabindex="2" />
		<label for="email"><?php _e('Email <em>(required)</em>', 'rosetta') ?></label>

		<input id="url" name="url" type="text" value="<?php echo esc_attr( $comment_author_url ) ?>" tabindex="3" />
		<label for="url"><?php _e('Web Site', 'rosetta') ?></label>
	</div>

<?php endif; ?>

	<div class="wide">
		<textarea id="comment" name="comment" cols="45" rows="8" tabindex="4"></textarea>
		<input id="submit" name="submit" type="submit" value="<?php esc_attr_e('Post Comment &raquo;', 'rosetta') ?>" tabindex="5" /><input type="hidden" name="comment_post_ID" value="<?php echo $id; ?>" />
	</div>

<?php do_action('comment_form', $post->ID); ?>

</form> <!-- end commentform -->

<?php else : // Comments are closed ?>
<?php endif; ?>

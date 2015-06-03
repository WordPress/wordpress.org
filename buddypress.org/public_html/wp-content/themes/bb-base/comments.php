<?php if ( post_password_required() ) : ?>
	<p class="nocomments">This post is password protected. Enter the password to view comments.</p>
<?php return; endif; ?>

<?php if ( have_comments() ) : ?>

	<h2 id="comments"><?php comments_number('No Responses', 'One Response', '% Responses' );?> to &#8220;<?php the_title(); ?>&#8221;</h2>
	<ol class="commentlist">
		<?php wp_list_comments(); ?>
	</ol>
	<div class="navigation">
		<div class="alignleft"><?php previous_comments_link() ?></div>
		<div class="alignright"><?php next_comments_link() ?></div>
	</div>

<?php else : ?>

	<?php if ( comments_open() ) : ?>

		<h2 id="comments">There are no comments to display</h2>

	<?php elseif ( !is_page() ) : ?>

		<p class="nocomments">Comments are closed.</p>

	<?php endif; ?>
<?php endif; ?>
				
<?php if ( comments_open() ) : ?>

	<?php if ( get_option( 'comment_registration' ) && ! is_user_logged_in() ) : ?>

		<p>You must be <a href="<?php echo site_url( '/wp-login.php' ); ?>?redirect_to=<?php echo urlencode( get_permalink() ); ?>">logged in</a> to post a comment.</p>

	<?php else : ?>

		<div id="respond">
			<form action="<?php echo site_url( '/wp-comments-post.php' ); ?>" method="post" id="commentform">
				<fieldset>
					<legend><?php comment_form_title( 'Leave a Reply', 'Leave a Reply to %s' ); ?></legend>
					<p class="cancel-reply"><?php cancel_comment_reply_link("Cancel Comment Reply"); ?></p>

					<?php if ( ! is_user_logged_in() ) : ?>

						<p><label for="author"><small>Name <?php if ($req) echo "(required)"; ?></small></label>
						<input type="text" name="author" id="author" value="<?php echo esc_attr( $comment_author ); ?>" size="22" tabindex="1" <?php if ($req) echo "aria-required='true'"; ?> /></p>
						<p><label for="email"><small>Mail (will not be published) <?php if ($req) echo "(required)"; ?></small></label>
						<input type="text" name="email" id="email" value="<?php echo esc_attr( $comment_author_email ); ?>" size="22" tabindex="2" <?php if ($req) echo "aria-required='true'"; ?> /></p>
						<p><label for="url"><small>Website</small></label>
						<input type="text" name="url" id="url" value="<?php echo esc_attr( $comment_author_url ); ?>" size="22" tabindex="3" /></p>

					<?php endif; ?>

					<p><textarea name="comment" id="comment" cols="70%" rows="10" tabindex="4"></textarea></p>
					<p>
						<input name="submit" type="submit" class="button" id="submit" tabindex="5" value="Submit Comment" />
						<?php comment_id_fields(); ?>
						<?php do_action( 'comment_form', get_the_ID() ); ?>
					</p>
				</fieldset>
			</form>
		</div>
		<hr class="hidden" />
	<?php endif; ?>
<?php endif; ?>

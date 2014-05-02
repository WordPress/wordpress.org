<?php
/**
 * Displays the content and meta information for a post object.
 *
 * @package wporg
 */
?>
<li id="prologue-<?php the_ID(); ?>" <?php post_class(); ?>>
	<h4>
		<span class="meta">
			<span class="actions">
				<a href="<?php the_permalink(); ?>" class="thepermalink printer-only" title="<?php esc_attr_e( 'Permalink', 'wporg' ); ?>"><?php _e( 'Permalink', 'wporg' ); ?></a>
				<?php
				if ( comments_open() && ! post_password_required() ) {
						echo post_reply_link( array(
							'before'        => isset( $before_reply_link ) ? $before_reply_link : '',
							'after'         => '',
							'reply_text'    => __( 'Reply', 'wporg' ),
							'add_below'     => 'comments'
						), get_the_ID() );
				}

				if ( current_user_can( 'edit_post', get_the_ID() ) ) : ?> | <a href="<?php echo ( get_edit_post_link( get_the_ID() ) ); ?>" class="edit-post-link" rel="<?php the_ID(); ?>" title="<?php esc_attr_e( 'Edit', 'wporg' ); ?>"><?php _e( 'Edit', 'wporg' ); ?></a>
				<?php endif; ?>

				<?php do_action( 'wporg_action_links' ); ?>
			</span>
			<?php if ( is_object_in_taxonomy( get_post_type(), 'post_tag' ) ) : ?>
				<span class="tags">
					<?php tags_with_count( '', __( '<br />Tags:' , 'wporg' ) .' ', ', ', ' &nbsp;' ); ?>&nbsp;
				</span>
			<?php endif; ?>
		</span>
	</h4>

	<?php
	/*
	 * Content
	 */
	?>

	<div id="content-<?php the_ID(); ?>" class="postcontent">
	<?php the_content( __( '(More ...)' , 'wporg' ) ); ?>
	</div>

	<?php
	/*
	 * Comments
	 */

	$comment_field = '<div class="form"><textarea id="comment" class="expand50-100" name="comment" cols="45" rows="3"></textarea></div> <label class="post-error" for="comment" id="commenttext_error"></label>';

	$comment_notes_before = '<p class="comment-notes">' . ( get_option( 'require_name_email' ) ? sprintf( ' ' . __( 'Required fields are marked %s', 'wporg' ), '<span class="required">*</span>' ) : '' ) . '</p>';

	$wporg_comment_args = array(
		'title_reply'           => __( 'Reply', 'wporg' ),
		'comment_field'         => $comment_field,
		'comment_notes_before'  => $comment_notes_before,
		'comment_notes_after'   => '<span class="progress spinner-comment-new"></span>',
		'label_submit'          => __( 'Reply', 'wporg' ),
		'id_submit'             => 'comment-submit',
	);

	?>

	<?php if ( get_comments_number() > 0 && ! post_password_required() ) : ?>
		<div class="discussion" style="display: none">
			<p>
				<?php wporg_discussion_links(); ?>
				<a href="#" class="show-comments"><?php _e( 'Toggle Comments', 'wporg' ); ?></a>
			</p>
		</div>
	<?php endif;

	wp_link_pages( array( 'before' => '<p class="page-nav">' . __( 'Pages:', 'wporg' ) ) ); ?>

	<div class="bottom-of-entry">&nbsp;</div>

</li>

<?php
/**
 * The template for displaying Comments.
 *
 * The area of the page that contains both current comments
 * and the comment form. The actual display of comments is
 * handled by a callback to wporg_developer_comment() which is
 * located in the inc/template-tags.php file.
 *
 * @package wporg-developer
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}
?>

<div id="comments" class="comments-area">

	<?php if ( have_comments() ) : ?>
		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : // are there comments to navigate through ?>
		<nav id="comment-nav-above" class="comment-navigation" role="navigation">
			<h1 class="screen-reader-text"><?php _e( 'Comment navigation', 'wporg' ); ?></h1>
			<div class="nav-previous"><?php previous_comments_link( __( '&larr; Older Comments', 'wporg' ) ); ?></div>
			<div class="nav-next"><?php next_comments_link( __( 'Newer Comments &rarr;', 'wporg' ) ); ?></div>
		</nav><!-- #comment-nav-above -->
		<?php endif; // check for comment navigation ?>

		<ol class="comment-list">
			<?php
				$feedback_editor = false;

				/* Loop through and list the comments. Use wporg_developer_list_notes() to format the comments.
				 * If you want to override this in a child theme, then you can
				 * define wporg_developer_list_notes() and that will be used instead.
				 * See wporg_developer_list_notes() in inc/template-tags.php for more.
				 */
				if ( is_singular( 'post' ) ) {
					wp_list_comments();
				} else {
					$ordered_comments = wporg_developer_get_ordered_notes();
					if ( $ordered_comments ) {
						wporg_developer_list_notes( $ordered_comments, array( 'avatar_size' => 32 ) );
					}
				}
			?>
		</ol><!-- .comment-list -->

		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : // are there comments to navigate through ?>
		<nav id="comment-nav-below" class="comment-navigation" role="navigation">
			<h1 class="screen-reader-text"><?php _e( 'Comment navigation', 'wporg' ); ?></h1>
			<div class="nav-previous"><?php previous_comments_link( __( '&larr; Older Comments', 'wporg' ) ); ?></div>
			<div class="nav-next"><?php next_comments_link( __( 'Newer Comments &rarr;', 'wporg' ) ); ?></div>
		</nav><!-- #comment-nav-below -->
		<?php endif; // check for comment navigation ?>

	<?php endif; // have_comments() ?>

	<?php if ( \DevHub\is_parsed_post_type() && DevHub\can_user_post_note( true, get_the_ID() ) ) : ?>

		<?php
		if ( class_exists( 'DevHub_User_Submitted_Content' ) )  {
			$args = \DevHub_User_Submitted_Content::comment_form_args();
			comment_form( $args );
		}
		?>
	<?php endif; ?>

	<?php if ( ! \DevHub\is_parsed_post_type() && comments_open() ) : ?>
		<p id="add-user-note" style="display:none;"><a href=""><?php _e( 'Leave a reply', 'wporg' ); ?></a></p>

		<?php comment_form(); ?>
	<?php endif; ?>

</div><!-- #comments -->

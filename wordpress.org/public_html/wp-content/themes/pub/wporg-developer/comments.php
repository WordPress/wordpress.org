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
				/* Loop through and list the comments. Tell wp_list_comments()
				 * to use wporg_developer_comment() to format the comments.
				 * If you want to override this in a child theme, then you can
				 * define wporg_developer_comment() and that will be used instead.
				 * See wporg_developer_comment() in inc/template-tags.php for more.
				 */
				if ( is_singular( 'post' ) ) {
					wp_list_comments();
				} else {
					$ordered_comments = wporg_developer_get_ordered_notes();
					if ( $ordered_comments ) {
						wp_list_comments( array( 'callback' => 'wporg_developer_user_note' ), $ordered_comments );
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

		<p id="add-user-note" style="display:none;"><a href=""><?php _e( 'Have a note or feedback to contribute?', 'wporg' ); ?></a></p>

		<?php comment_form( array(
			'comment_field'       => DevHub_User_Submitted_Content::wp_editor_comments(),
			'comment_notes_after' => '<p>' .
				__( 'Notes should supplement code reference entries, for example examples, tips, explanations, use-cases, and best practices.', 'wporg' ) .
				'</p><p>' .
				__( 'Feedback can be to report errors or omissions with the documentation on this page. Such feedback will not be publicly posted.', 'wporg' ) . 
				'</p><p>' .
				__( 'Do not use this form for support requests, discussions, spam, bug reports, complaints, or self-promotion. Entries of this nature will be deleted.', 'wporg' ) .
				'</p><p>' .
				__( 'You can enter text and code. Use the php, js, or inline code buttons to wrap code snippets.', 'wporg' ) .
				'</p><p>' .
				__( 'In the editing area the Tab key enters a tab character. To move below this area by pressing Tab, press the Esc key followed by the Tab key. In some cases the Esc key will need to be pressed twice before the Tab key will allow you to continue.', 'wporg' ) .
				'</p><p class="user-notes-are-gpl">' .
				sprintf( __( '<strong>NOTE:</strong> All contributions are licensed under <a href="%s">GFDL</a> and are moderated before appearing on the site.', 'wporg' ), 'https://gnu.org/licenses/fdl.html' ) .
				'</p>',
			'label_submit'        => __( 'Add Note or Feedback', 'wporg' ),
			'must_log_in'         => '<p>' . sprintf(
				__( 'You must <a href="%s">log in</a> before being able to contribute a note or feedback.', 'wporg' ),
				'https://wordpress.org/support/bb-login.php?redirect_to=' . urlencode( get_comments_link() )
			) . '</p>',
			'title_reply'         =>  '', //'Add Example'
		) ); ?>

		<!-- Comment Preview -->
		<div id='comment-preview' class='comment byuser depth-1' style='display:none;'>
			<article class='comment-body'>
				<header class='comment-meta'>
					<div>
						<?php _e( 'Preview', 'wporg' ); ?>
						<span class='spinner' style='display:none'></span>
					</div>
				</header>
				<div class='comment-content'></div>
			</article>
		</div>

	<?php endif; ?>

	<?php if ( ! \DevHub\is_parsed_post_type() && comments_open() ) : ?>
		<p id="add-user-note" style="display:none;"><a href=""><?php _e( 'Leave a reply', 'wporg' ); ?></a></p>

		<?php comment_form(); ?>
	<?php endif; ?>

</div><!-- #comments -->

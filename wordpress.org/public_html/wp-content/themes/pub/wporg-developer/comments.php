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
			'class_form'          => 'comment-form tab-container',
			'comment_field'       => DevHub_User_Submitted_Content::wp_editor_comments(),
			'logged_in_as'        => '<p class="logged-in-as">'
				. sprintf(
					/* translators: 1: user profile link, 2: accessibility text, 3: user name, 4: logout URL */
					__( '<a href="%1$s" aria-label="%2$s">Logged in as %3$s</a>. <a href="%4$s">Log out?</a>' ),
					'https://profiles.wordpress.org/' . esc_attr( wp_get_current_user()->user_nicename ),
					/* translators: %s: user name */
					esc_attr( sprintf( __( 'Logged in as %s. Edit your profile.' ), $user_identity ) ),
					$user_identity,
					wp_logout_url( apply_filters( 'the_permalink', get_permalink() ) )
				)
				. '</p><p><ul><li>'
				. __( 'Notes should supplement code reference entries, for example examples, tips, explanations, use-cases, and best practices.', 'wporg' )
				. '</li><li>'
				. __( 'Feedback can be to report errors or omissions with the documentation on this page. Such feedback will not be publicly posted.', 'wporg' )
				. '</li><li>'
				/* translators: 1: php button, 2: js button, 3: inline code button */
				. sprintf(
					__( 'You can enter text and code. Use the %1$s, %2$s, or %3$s buttons to wrap code snippets.', 'wporg' ),
					'<span class="text-button">php</span>',
					'<span class="text-button">js</span>',
					'<span class="text-button">' . __( 'inline code', 'wporg' ) . '</span>'
				)
				. '</li></ul></p>',
			'comment_notes_after' => DevHub_Note_Preview::comment_preview()
				. '<p>'
				. __( 'Submission Notes:', 'wporg' ) 
				. '<ul><li>'
				. __( 'This form is not for support requests, discussions, spam, bug reports, complaints, or self-promotion. Entries of this nature will be deleted.', 'wporg' )
				. '</li><li>'
				. __( 'In the editing area the Tab key enters a tab character. To move below this area by pressing Tab, press the Esc key followed by the Tab key. In some cases the Esc key will need to be pressed twice before the Tab key will allow you to continue.', 'wporg' )
				. '</li><li class="user-notes-are-gpl">'
				. sprintf(
					/* translators: 1: GFDL link */
					__( '<strong>NOTE:</strong> All contributions are licensed under %s and are moderated before appearing on the site.', 'wporg' ),
					'<a href="https://gnu.org/licenses/fdl.html">GFDL</a>'
				)
				. '</li></ul></p>',
			'label_submit'        => __( 'Add Note or Feedback', 'wporg' ),
			'must_log_in'         => '<p>' . sprintf(
				__( 'You must <a href="%s">log in</a> before being able to contribute a note or feedback.', 'wporg' ),
				'https://wordpress.org/support/bb-login.php?redirect_to=' . urlencode( get_comments_link() )
			) . '</p>',
			'title_reply'         =>  '', //'Add Example'
		) ); ?>

	<?php endif; ?>

	<?php if ( ! \DevHub\is_parsed_post_type() && comments_open() ) : ?>
		<p id="add-user-note" style="display:none;"><a href=""><?php _e( 'Leave a reply', 'wporg' ); ?></a></p>

		<?php comment_form(); ?>
	<?php endif; ?>

</div><!-- #comments -->

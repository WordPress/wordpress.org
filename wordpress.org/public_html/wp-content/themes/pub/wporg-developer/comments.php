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
				wp_list_comments( array( 'callback' => 'wporg_developer_comment' ) );
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

	<p id="add-example" style="display:none;"><a href=""><?php _e( 'Have an example to add?', 'wporg' ); ?></a></p>

	<?php comment_form( array(
		'comment_field'       => '<p class="comment-form-comment"><label for="comment">' . _x( 'Add Example', 'noun', 'wporg' ) . '</label> <textarea id="comment" name="comment" cols="45" rows="8" aria-required="true"></textarea></p>',
		'comment_notes_after' => '<p class="examples-are-gpl">' .
			sprintf( __( '<strong>NOTE:</strong> All contributions are licensed under <a href="%s">GFDL</a> and are moderated before appearing on the site.', 'wporg' ), 'https://gnu.org/licenses/fdl.html' ) .
			'</p><p>' .
			__( 'The entirety of your submission is considered a code example. Any included non-code text should be formatted as code comments.', 'wporg' ) .
			'</p>',
		'label_submit'        => __( 'Add Example', 'wporg' ),
		'must_log_in'         => '<p>' . sprintf( __( 'You must <a href="%s">log in</a> before being able to submit an example.', 'wporg' ), 'https://wordpress.org/support/bb-login.php' ) . '</p>',
		'title_reply'         =>  '', //'Add Example'
	) ); ?>

</div><!-- #comments -->

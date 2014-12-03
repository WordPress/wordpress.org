<?php
/**
 * WordPress.tv Comments Template
 * @uses global $wptv->list_comments
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if ( post_password_required() ) {
	return;
}

global $wptv;

if ( have_comments() ) :
?>
	<h3 id="comments">
		<?php
			printf( _nx( 'One response on &ldquo;%2$s&rdquo;', '%1$s responses on &ldquo;%2$s&rdquo;', get_comments_number(), 'comments title', 'wptv' ),
				number_format_i18n( get_comments_number() ), '<span>' . get_the_title() . '</span>' );
		?>
	</h3>

	<ol class="commentlist">
		<?php wp_list_comments( array( 'callback' => array( $wptv, 'list_comments' ) ) ); ?>
	</ol>
<?php
endif;

if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) :
?>
	<p class="nocomments"><?php esc_html_e( 'Comments are closed.', 'wptv' ); ?></p>
<?php

endif;

comment_form();

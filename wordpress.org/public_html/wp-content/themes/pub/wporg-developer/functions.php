<?php


add_action( 'wp_enqueue_scripts', 'theme_scripts_styles' );
function theme_scripts_styles() {
	wp_enqueue_style( 'wp-doc-style', get_stylesheet_uri() );
	wp_enqueue_style( 'droid-sans-mono', '//fonts.googleapis.com/css?family=Droid+Sans+Mono' );

	## wp_enqueue_script( 'ace-editor', 'http://rawgithub.com/ajaxorg/ace-builds/master/src-noconflict/ace.js' );
}

function wp_doc_comment( $comment, $args, $depth ) {
	?>
	<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
		<article id="comment-<?php comment_ID(); ?>" class="comment">

			<?php if ( $comment->comment_approved == '0' ) : ?>
				<em class="comment-awaiting-moderation"><?php _e('Your example is awaiting moderation.'); ?></em>
				<br />
			<?php endif; ?>

			<pre class="example-content"><?php echo htmlentities( get_comment_text() ); ?></pre>

			<footer class="comment-meta">
				<div class="comment-author vcard">
					<?php
						echo get_avatar( $comment );

						/* translators: 1: comment author, 2: date and time */
						printf( __('Contributed by %1$s on %2$s'),
							sprintf( '<span class="fn">%s</span>', get_comment_author_link() ),
							sprintf( '<a href="%1$s"><time datetime="%2$s">%3$s</time></a>',
								esc_url( get_comment_link( $comment->comment_ID ) ),
								get_comment_time( 'c' ),
								/* translators: 1: date, 2: time */
								sprintf( __('%1$s at %2$s'), get_comment_date(), get_comment_time() )
							)
						);
					?>

					<?php edit_comment_link( __('Edit'), '<span class="edit-link">', '</span>' ); ?>
				</div><!-- .comment-author .vcard -->

			</footer>

		</article><!-- #comment-## -->

	<?php
}


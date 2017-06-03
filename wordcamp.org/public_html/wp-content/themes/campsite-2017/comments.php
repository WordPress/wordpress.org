<?php
/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

if ( post_password_required() ) {
	return;
}

?>

<div id="comments" class="comments-area">
	<?php if ( have_comments() ) : ?>
		<h2 class="comments-title">
			<?php
			$comments_number = get_comments_number();

			if ( '1' === $comments_number ) {
				echo wp_kses_data( sprintf(
					/* translators: %s: post title */
					_x( 'One Reply to &ldquo;%s&rdquo;', 'comments title', 'wordcamporg' ),
					get_the_title()
				) );
			} else {
				echo wp_kses_data( sprintf(
					/* translators: 1: number of comments, 2: post title */
					_nx( '%1$s Reply to &ldquo;%2$s&rdquo;', '%1$s Replies to &ldquo;%2$s&rdquo;', absint( $comments_number ), 'comments title', 'wordcamporg' ),
					number_format_i18n( $comments_number ),
					get_the_title()
				) );
			}

			?>
		</h2>

		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : // Are there comments to navigate through? ?>
			<nav id="comment-nav-above" class="navigation comment-navigation" role="navigation">
				<h2 class="screen-reader-text">
					<?php esc_html_e( 'Comment navigation', 'wordcamporg' ); ?>
				</h2>

				<div class="nav-links">
					<div class="nav-previous">
						<?php previous_comments_link( esc_html__( 'Older Comments', 'wordcamporg' ) ); ?>
					</div>

					<div class="nav-next">
						<?php next_comments_link( esc_html__( 'Newer Comments', 'wordcamporg' ) ); ?>
					</div>
				</div>
			</nav>
		<?php endif; ?>

		<ol class="comment-list">
			<?php
				wp_list_comments( array(
					'style'      => 'ol',
					'short_ping' => true,
				) );
			?>
		</ol>

		<?php if ( get_comment_pages_count() > 1 && get_option( 'page_comments' ) ) : // Are there comments to navigate through? ?>
			<nav id="comment-nav-below" class="navigation comment-navigation" role="navigation">
				<h2 class="screen-reader-text">
					<?php esc_html_e( 'Comment navigation', 'wordcamporg' ); ?>
				</h2>

				<div class="nav-links">
					<div class="nav-previous">
						<?php previous_comments_link( esc_html__( 'Older Comments', 'wordcamporg' ) ); ?>
					</div>

					<div class="nav-next">
						<?php next_comments_link( esc_html__( 'Newer Comments', 'wordcamporg' ) ); ?>
					</div>
				</div>
			</nav>
		<?php endif;

	endif; // Check for have_comments().

	if ( ! comments_open() && get_comments_number() && post_type_supports( get_post_type(), 'comments' ) ) : ?>
		<p class="no-comments">
			<?php esc_html_e( 'Comments are closed.', 'wordcamporg' ); ?>
		</p>
	<?php endif; ?>

	<?php comment_form(); ?>

</div>

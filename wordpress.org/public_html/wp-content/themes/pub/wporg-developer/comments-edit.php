<?php namespace DevHub;
/**
 * The Template for editing user contributed notes.
 *
 * This template is used if the current user can edit a note.
 * The global post data is set with the pre_get_posts action.
 *
 * @package wporg-developer
 */

get_header();

$comment_id    = get_query_var( 'edit_user_note' );
$comment       = get_comment( $comment_id );
$post          = get_queried_object();
$post_id       = get_queried_object_id();
$can_user_edit = \DevHub\can_user_edit_note( $comment_id );

if ( ! ( $comment && $post && $post_id && $can_user_edit ) ) {
	// Bail if the current user can't edit this note, or if
	// the comment or global post data is not found.
	include get_404_template();
	return;
}

$is_parent   = $comment->comment_parent ?  true : false;
$parent      = $is_parent ? get_comment( $comment->comment_parent ) : false;
$post_url    = get_permalink( $post_id );
$post_title  = single_post_title( '', false );
$post_types  = get_parsed_post_types( 'labels' );
$type_single = get_post_type_object( $post->post_type )->labels->singular_name;
$type_url    = get_post_type_archive_link( $post->post_type );
$type_label  = $post_types[ $post->post_type ];
$ref_url     = get_site_section_url();
$ref_link    = sprintf( '<a href="%s">%s</a>', esc_url( $ref_url ), __( 'Reference', 'wporg' ) );
$post_link   = sprintf( '<a href="%s">%s</a>', esc_url( $post_url ), $post_title );
/* translators: %d: comment ID */
$note_link   = sprintf( '<a href="%s">%s</a>', esc_url( $post_url . '#comment-' . $comment_id ), sprintf( __( 'note %d', 'wporg' ), $comment_id ) );
$type_link   = sprintf( '<a href="%s">%s</a>', esc_url( $type_url ), $type_label );

$parent_link   = '';
$parent_author = '';
if ( $is_parent && isset( $parent->comment_ID ) ) {
	$parent_author = get_note_author_link( $parent );
	/* translators: %d: comment ID */
	$parent_label  = sprintf( __( 'note %d', 'wporg' ), $parent->comment_ID );
	$parent_link   = sprintf( '<a href="%s">%s</a>', esc_url( $post_url . '#comment-' . $parent->comment_ID ), $parent_label );
}

add_filter( 'breadcrumb_trail_items', function( $items ) use ( $ref_link, $type_link, $post_link, $note_link ) {
	$items[] = $ref_link;
	$items[] = $type_link;
	$items[] = $post_link;
	$items[] = $note_link;
	$items[] = __('Edit', 'wporg');
	return $items;
} );
?>

	<div id="content-area" <?php body_class( 'code-reference' ); ?>>

		<?php breadcrumb_trail( array( 'show_title' => false ) ); ?>

		<main id="main" class="site-main" role="main">
			<h1><?php printf( __( 'Edit Note %d', 'wporg' ), $comment_id ); ?></h1>

			<p>
				<?php if ( $is_parent ) : ?>
					<?php
						/* translators: 1: comment title, 2: comment author name, 3: reference type (function, class, method, hook), 4: post title */
						printf( __( 'This is a feedback note to %1$s by %2$s for the %3$s %4$s.', 'wporg' ), $parent_link, $parent_author, strtolower( $type_single ), $post_link );
					?>
				<?php else : ?>
					<?php
						/* translators: 1: reference type (function, class, method, hook), 2: post title */
						printf( __( 'This is a note for the %1$s %2$s.', 'wporg' ), strtolower( $type_single ), $post_link ); ?>
				<?php endif; ?>

				<?php echo ' ' . __( "You can edit this note as long as it's in moderation.", 'wporg' ); ?>
			</p>
			<?php
				if ( $is_parent ) {
					echo \DevHub_User_Submitted_Content::wp_editor_feedback( $comment, 'show', true );
				} else {
					$args = \DevHub_User_Submitted_Content::comment_form_args( $comment, 'edit');
					comment_form( $args );
				}
			?>

		</main><!-- #main -->
	</div><!-- #primary -->
<?php get_footer(); ?>


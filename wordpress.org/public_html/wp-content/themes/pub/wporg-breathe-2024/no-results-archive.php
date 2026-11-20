<?php
/**
 * The template part for an archive without posts.
 *
 * @package wporg-breathe
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Make\Breathe_2024;

global $wp_query;

$archive_user = get_archive_user( $wp_query );

if ( ! $archive_user ) {
	get_template_part( 'no-results' );
	return;
}

$heading = is_author() ? __( 'No posts yet', 'wporg' ) : __( 'No mentions yet', 'wporg' );
$message = get_empty_archive_message( $archive_user );
?>

<article id="post-0" class="post no-results not-found">
	<header class="entry-header">
		<h1 class="entry-title"><?php echo esc_html( $heading ); ?></h1>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<p><?php echo esc_html( $message ); ?></p>
	</div><!-- .entry-content -->
</article><!-- #post-0 .post .no-results .not-found -->

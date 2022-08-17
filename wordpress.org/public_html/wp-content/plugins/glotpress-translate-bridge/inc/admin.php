<?php
namespace WordPressdotorg\Post_Translation;

add_action( 'save_post', function( $post_id, $post ) {
	// Only need to process published pages.
	if ( 'publish' != $post->post_status || 'page' !== $post->post_type ) {
		return;
	}

	$project = get_post_translation_project( $post );
	if ( ! $project ) {
		return;
	}

	// Import any changes into GlotPress.
	if ( ! wp_next_scheduled( 'post_translation_import_to_glotpress', array( $post_id ) ) ) {
	//	wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'post_translation_import_to_glotpress', array( $post_id ) );
	// TEMP HACKERY: Run the import now, rather than queueing a cron task that won't actually do anything in production.
		do_action( 'post_translation_import_to_glotpress', $post_id );
	}
}, 10, 2 );

// Import into GlotPress for the changed post.
add_action( 'post_translation_import_to_glotpress', function( $post_id ) {
	include_once __DIR__ . '/class-makepot.php';

	$post = get_post( $post_id );
	if ( ! $post || 'publish' != $post->post_status || 'page' != $post->post_type ) {
		return;
	}

	$project = get_post_translation_project( $post );
	if ( ! $project ) {
		return;
	}

var_dump( $project );

	// Import changes to GlotPress.
	$makepot  = new Makepot( $project, array( $post ) );
	echo $makepot->import( true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} );
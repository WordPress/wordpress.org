<?php
/**
 * Renders the release flags block.
 */

use WordPressdotorg\Plugin_Directory\Readme\Validator as Readme_Validator;

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
	return;
}

if ( ! $block->context['postId'] ) {
	return;
}

$release_post = get_post( $block->context['postId'] );

if ( 'draft' !== $release_post->post_status ) {
	return;
}

// Warnings are currently associated to the plugin post, not the release post.
$import_warnings = get_post_meta( $release_post->post_parent, '_import_warnings', true );

if ( ! $import_warnings ) {
	return;
}

// Back-compat; previously this was an array of numeric-indexed human-readable strings.
if ( ! wp_is_numeric_array( $import_warnings ) ) {
	// error_code => error_data, convert to error_code => human_readable_error.
	foreach ( $import_warnings as $error_code => $error_data ) {
		$import_warnings[ $error_code ] = Readme_Validator::instance()->translate_code_to_message( $error_code, $error_data );
	}
}

$warnings = '';
foreach ( $import_warnings as $error_code => $error_data ) {
	$warnings .= sprintf(
		'<!-- wp:wporg/release-check-item {"status":"%1$s"} --><p>%2$s</p><!-- /wp:wporg/release-check-item -->',
		'warning',
		$error_data
	);
}

$heading = sprintf(
	'<!-- wp:heading {"level":4,"fontSize":"normal", "style":{"spacing":{"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}}} -->
		<h4 class="wp-block-heading has-normal-font-size" style="margin-top:0;margin-right:0;margin-bottom:0;margin-left:0">%s</h4>
	<!-- /wp:heading -->',
	esc_attr__( 'Flags', 'wporg-plugins' )
);

printf(
	'<div %1$s>%2$s<ul>%3$s</ul></div>',
	wp_kses_data( get_block_wrapper_attributes() ),
	do_blocks( $heading ), //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	do_blocks( $warnings ) //phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

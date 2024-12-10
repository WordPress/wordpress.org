<?php
/**
 * Renders the Release Checks block.
 *
 * @package wporg-plugins
 */

use function WordPressdotorg\Plugin_Directory\Theme\{format_plugin_check_results, get_test_run_message};

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
	return;
}

if ( empty( $block->context['postId'] ) ) {
	return;
}

$plugin_check_errors = get_post_meta( get_post( $block->context['postId'] )->ID, 'plugin_check_result', true );

$heading = sprintf(
	'<!-- wp:heading {"level":4,"fontSize":"normal", "style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0"}}}} -->
		<h4 class="wp-block-heading has-normal-font-size" style="margin-right:0;margin-bottom:0;margin-left:0">%s</h4>
	<!-- /wp:heading -->',
	esc_html__( 'Checks', 'wporg-plugins' )
);

echo do_blocks( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

if ( empty( $plugin_check_errors ) ) {
	printf(
		'<p>%s</p>',
		esc_html__( 'No checks were run.', 'wporg-plugins' )
	);
	return;
}

// Create a block with the overall status.
$status_block = sprintf(
	'<!-- wp:wporg/release-result-item {"status":"%1$s"} -->%2$s<!-- /wp:wporg/release-result-item -->',
	$plugin_check_errors['verdict'] ? 'success' : 'warning',
	get_test_run_message( $plugin_check_errors )
);

printf(
	'<ul %1$s>%2$s</ul>',
	wp_kses_data( get_block_wrapper_attributes() ),
	do_blocks( $status_block ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

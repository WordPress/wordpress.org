<?php
/**
 * Renders the Release Checks block.
 *
 * @package wporg-plugins
 */
use WordPressdotorg\Plugin_Directory\Readme\Validator as Readme_Validator;

use function WordPressdotorg\Theme\Plugins_2024\ReleaseChecks\{format_plugin_check_results, get_test_run_message};
use function WordPressdotorg\Plugin_Directory\Theme\{get_latest_release, get_plugin};

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

// Warnings are currently associated to the plugin post, not the release post.
$import_warnings = get_post_meta( get_plugin()->ID, '_import_warnings', true );

if ( $import_warnings ) {
	// Back-compat; previously this was an array of numeric-indexed human-readable strings.
	if ( ! wp_is_numeric_array( $import_warnings ) ) {
		// error_code => error_data, convert to error_code => human_readable_error.

		foreach ( $import_warnings as $error_code => $error_data ) {
			$import_warnings[ $error_code ] = Readme_Validator::instance()->translate_code_to_message( $error_code, $error_data );
		}
	}

	$warnings = '';
	foreach ( $import_warnings as $error_code => $error_data ) {

		// Skip these warnings,they are not relevant to the release.
		if ( in_array( $error_code, array( 'stable_tag_invalid_trunk_fallback', 'stable_tag_invalid' ), true ) ) {
			continue;
		}

		$blocks .= sprintf(
			'<!-- wp:wporg/release-result-item {"status":"warning"} --><div>%s</div><!-- /wp:wporg/release-result-item -->',
			$error_data
		);
	}
}

// Create a block with the overall status.
$blocks = sprintf(
	'<!-- wp:wporg/release-result-item {"status":"%1$s"} -->%2$s<!-- /wp:wporg/release-result-item -->',
	$plugin_check_errors['verdict'] ? 'success' : 'warning',
	get_test_run_message( $plugin_check_errors )
);

printf(
	'<ul %1$s>%2$s</ul>',
	wp_kses_data( get_block_wrapper_attributes() ),
	do_blocks( $blocks ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

<?php
/**
 * Block Name: Release checks
 * Description: A block to display release checks.
 *
 * @package wporg
 */

namespace WordPressdotorg\Theme\Plugins_2024\ReleaseChecks;

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function init() {
	register_block_type( __DIR__ . '/../../../build/blocks/release-checks' );
}

/**
 * Formats plugin check results into an HTML list.
 *
 * @param array $results The plugin check results.
 * @return string HTML formatted results.
 */
function format_plugin_check_results( $results ) {
	if ( empty( $results ) ) {
		return '<p>' . __( 'No issues found.', 'wporg-plugins' ) . '</p>';
	}

	$output = '<ul class="wp-block-wporg-release-checks-results">';

	$grouped_by_file = array();
	foreach ( $results as $plugin_error ) {
		$grouped_by_file[ strtolower( $plugin_error['file'] ) ][] = $plugin_error;
	}

	foreach ( $grouped_by_file as $file_name => $list ) {
		$output .= '<li>';

		// For some reason, we need to render the block otherwise it won't render.
		$output .= do_blocks(
			sprintf(
				'<!-- wp:heading {"level":5,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
				<h5 class="wp-block-heading" style="margin-top:0;margin-bottom:0">%s</h5>
				<!-- /wp:heading -->',
				esc_html( $file_name )
			)
		);

		$output .= '<ul>';
		foreach ( $list as $plugin_error ) {
			$output .= '<li>';
			$output .= wp_kses_post( $plugin_error['message'] );
			if ( ! empty( $plugin_error['docs'] ) ) {
				$output .= sprintf(
					' <a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $plugin_error['docs'] ),
					__( 'Learn more', 'wporg-plugins' )
				);
			}
			$output .= '</li>';
		}
		$output .= '</ul>';

		$output .= '</li>';
	}

	$output .= '</ul>';

	return $output;
}

/**
 * Get the test run message.
 *
 * @param object $plugin_check_errors The plugin check errors.
 *
 * @return string The test run message.
 */
function get_test_run_message( $plugin_check_errors ) {
	$plugin_check_link = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://wordpress.org/plugins/plugin-check' ),
		esc_html__( 'Static code analysis', 'wporg-plugins' )
	);

	if ( $plugin_check_errors['verdict'] ) {
		return sprintf(
			/* translators: %1$s is a link to the code review ruleset. */
			__( '%1$s found no issues.', 'wporg-plugins' ),
			$plugin_check_link
		);
	}

	$result_count = count( $plugin_check_errors['results'] );
	$message      = sprintf(
		/* translators: %s number of issues reported from test. */
		_n( '%s issue', '%s issues', $result_count, 'wporg-plugins' ),
		$result_count
	);

	return sprintf(
		'<div>%1$s%2$s</div>',
		sprintf(
		/* translators: %1$s is a link to the Plugin Check (PCP) tool. */
			__( '%1$s completed with %2$s.', 'wporg-plugins' ),
			$plugin_check_link,
			$message
		),
		format_plugin_check_results( $plugin_check_errors['results'] ),
	);
}

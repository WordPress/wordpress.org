<?php
/**
 * Renders the Release Checks block.
 */

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
	return;
}

if ( empty( $block->context['postId'] ) ) {
	return;
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

	foreach ( $results as $result ) {
		$type_class = isset( $result['type'] ) ? strtolower( $result['type'] ) : '';

		$output .= sprintf(
			'<li>%1$s %2$s</li>',
			esc_html( $result['message'] ),
			! empty( $result['docs'] )
				? sprintf(
					'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
					esc_url( $result['docs'] ),
					__( 'More Information', 'wporg-plugins' )
				)
				: ''
		);
	}

	$output .= '</ul>';

	return $output;
}

/**
 * Get the counts of each error type.
 *
 * @param array $result The plugin check result.
 *
 * @return array Array of error type counts.
 */
function get_error_type_counts( $result ) {
	return array_reduce(
		$result ?? array(),
		function ( $carry, $item ) {
			if ( isset( $item['type'] ) ) {
				$carry[ $item['type'] ] = ( $carry[ $item['type'] ] ?? 0 ) + 1;
			}
			return $carry;
		},
		array(
			'ERROR'   => 0,
			'WARNING' => 0,
		)
	);
}

/**
 * Get the test run message.
 *
 * @return string The test run message.
 */
function get_test_run_message( $plugin_check_errors ) {

	$plugin_check_link = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
		esc_url( 'https://wordpress.org/plugins/plugin-check' ),
		esc_html__( 'Plugin Check (PCP)', 'wporg-plugins' )
	);

	if ( $plugin_check_errors->verdict ) {
		return sprintf(
			/* translators: %1$s is a link to the Plugin Check (PCP) tool. */
			__( 'Passed the %1$s.', 'wporg-plugins' ),
			$plugin_check_link
		);
	}

	$counts = get_error_type_counts( $plugin_check_errors['results'] );

	$error_message   = sprintf( _n( '%s error', '%s errors', $counts['ERROR'], 'wporg-plugins' ), $counts['ERROR'] );
	$warning_message = sprintf( _n( '%s warning', '%s warnings', $counts['WARNING'], 'wporg-plugins' ), $counts['WARNING'] );
	$message         = '';

	if ( empty( $counts['ERROR'] ) ) {
		$message = $warning_message;
	} elseif ( empty( $counts['WARNING'] ) ) {
		$message = $error_message;
	} else {
		/* translators: %1$s: number of errors, %2$s: number of warnings */
		$message = sprintf(
			'%1$s and %2$s',
			$error_message,
			$warning_message
		);
	}

	$mm = sprintf(
		/* translators: %1$s is a link to the Plugin Check (PCP) tool. */
		__( '%1$s completed with %2$s.', 'wporg-plugins' ),
		$plugin_check_link,
		$message
	);

	return sprintf(
		'<div>%1$s<ul>%2$s</ul></div>',
		$mm,
		format_plugin_check_results( $plugin_check_errors['results'] ),
	);
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
	'<!-- wp:wporg/release-check-item {"status":"%1$s"} -->%2$s<!-- /wp:wporg/release-check-item -->',
	$plugin_check_errors->verdict ? 'success' : 'error',
	get_test_run_message( $plugin_check_errors )
);

printf(
	'<ul %1$s>%2$s</ul>',
	wp_kses_data( get_block_wrapper_attributes() ),
	do_blocks( $status_block ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

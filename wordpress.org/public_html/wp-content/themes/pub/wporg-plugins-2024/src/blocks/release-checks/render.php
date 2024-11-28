<?php
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

$release_post       = get_post( $block->context['postId'] );
$plugin_check_errors = get_post_meta( $release_post->ID, 'plugin_check_result', true );

echo do_blocks(
	sprintf(
		'<!-- wp:heading {"level":4,"fontSize":"normal"} -->
		<h4 class="wp-block-heading has-normal-font-size">%s</h4>
		<!-- /wp:heading -->',
		esc_html__( 'Checks', 'wporg-plugins' )
	)
);

if ( empty( $plugin_check_errors ) ) {
	printf(
		'<p>%s</p>',
		esc_html__( 'No checks were run.', 'wporg-plugins' )
	);
	return;
}

$plugin_check_link = sprintf(
	'<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
	esc_url( 'https://wordpress.org/plugins/plugin-check' ),
	esc_html__( 'Plugin Check (PCP)', 'wporg-plugins' )
);

$message = '';
if ( ! empty( $plugin_check_errors->verdict ) ) {
	$message = sprintf(
		/* translators: %1$s is a link to the Plugin Check (PCP) tool. */
		'<p>' . __( 'Passed the %1$s.', 'wporg-plugins' ) . '</p>',
		$plugin_check_link
	);
} else {
	$message = sprintf(
		'<details><summary>%1$s</summary>%2$s</details>',
		sprintf(
			/* translators: %1$s is a link to the Plugin Check (PCP) tool. */
			__( '%1$s detected issues.', 'wporg-plugins' ),
			$plugin_check_link
		),
		format_plugin_check_results( $plugin_check_errors['results'] )
	);
}

// Create a block with the overall status.
$status_block = sprintf(
	'<!-- wp:wporg/release-check-item {"status":"%1$s"} -->%2$s<!-- /wp:wporg/release-check-item -->',
	$plugin_check_errors->verdict ? 'success' : 'error',
	$message
);

printf(
	'<ul %1$s>%2$s</ul>',
	wp_kses_data( get_block_wrapper_attributes() ),
	do_blocks( $status_block )
);

<?php
/**
 * Render the release page block.
 *
 * @package wporg-plugins
 */

if ( ! $block->context['postId'] ) {
	return;
}

$plugin_post = get_post( $block->context['postId'] );

if ( ! $plugin_post ) {
	return;
}

$heading_text = __( 'Releases', 'wporg-plugins' );

$markup = <<<HTML
<!-- wp:heading -->
<h2 id="releases" class="wp-block-heading">$heading_text</h2>
<!-- /wp:heading -->

<!-- wp:wporg/release-draft /-->

<div data-wp-bind--hidden="state.isCreatingRelease">
	<!-- wp:spacer {"height":"var:preset|spacing|20"} -->
	<div style="height:var(--wp--preset--spacing--20)" aria-hidden="true" class="wp-block-spacer"></div>
	<!-- /wp:spacer -->

	<!-- wp:pattern {"slug":"wporg-plugins-2024/release-list"} /-->
</div>
HTML;

/**
 * Create initial state for the async-action-block.
 */
wp_interactivity_state(
	'async-action-block',
	array(
		'isCreatingRelease' => false,
	)
);

/**
 * Create initial context for the async-action-block.
 */

printf(
	'<div %1$s %2$s>%3$s</div>',
	wp_kses_data( get_block_wrapper_attributes() ),
	'data-wp-interactive="async-action-block"',
	do_blocks( $markup ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

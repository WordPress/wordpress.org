<?php
/**
 * Render the release draft block.
 *
 * @package wporg-plugins
 */

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
	return;
}

if ( ! $block->context['postId'] ) {
	return;
}

$plugin_post = get_post( $block->context['postId'] );

if ( ! $plugin_post ) {
	return;
}

/**
 * We are in the context of the plugin post, so we can query for the latest draft post.
 */
$query_args = array(
	'post_type'      => 'plugin_release',
	'posts_per_page' => 1,
	'post_parent'    => $plugin_post->ID,
	'orderby'        => 'date',
	'post_status'    => 'draft',
	'order'          => 'DESC',
);

$latest_draft_query = new WP_Query( $query_args );

if ( ! $latest_draft_query->have_posts() ) {
	return;
}

// Fetch the latest draft post.
$latest_draft_query->the_post();

$new_version = get_post_meta( get_the_ID(), 'release_version', true );

$post_title    = sprintf(
	__( 'Trunk (v.%s)', 'wporg-plugins' ),
	$new_version
);
$intro_text    = __( 'There are unpublished changes in your trunk folder.', 'wporg-plugins' );
$button_text   = __( 'Create release', 'wporg-plugins' );
$publish_title = __( 'Create release', 'wporg-plugins' );

$markup = <<<HTML
<div data-wp-bind--hidden="state.isCreatingRelease">
	<!-- wp:wporg/card {"title":"$post_title"} -->
		<!-- wp:paragraph -->
		<p>$intro_text</p>
		<!-- /wp:paragraph -->

		<!-- wp:wporg/release-commits /-->
		<!-- wp:wporg/release-checks /-->

		<!-- wp:group {"className":"wporg-release-confirmation-actions","style":{"spacing":{"padding":{"top":"var:preset|spacing|10"}}},"layout":{"type":"default"}} -->
		<div class="wp-block-group wporg-release-confirmation-actions" style="padding-top:var(--wp--preset--spacing--10);">	
			<div class="wp-block-button is-small">
				<button data-wp-on--click="actions.handlePreSubmit" class="wp-block-button__link wp-element-button">
					$button_text
				</button>
			</div>
		</div>
		<!-- /wp:group -->
	<!-- /wp:wporg/card -->
</div>

<div data-wp-bind--hidden="!state.isCreatingRelease">
	<!-- wp:wporg/card {"title":"$publish_title"} -->
		<!-- wp:wporg/release-publish /-->
	<!-- /wp:wporg/card -->
</div>
HTML;

printf(
	'<div %1$s %2$s>%3$s</div>',
	'data-wp-interactive="async-action-block"',
	wp_kses_data( get_block_wrapper_attributes() ),
	do_blocks( $markup ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

// Reset global post data.
wp_reset_postdata();

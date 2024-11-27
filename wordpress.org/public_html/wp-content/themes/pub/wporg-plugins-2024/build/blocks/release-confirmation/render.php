<?php

use function WordPressdotorg\Plugin_Directory\Theme\{get_releases,get_trac_changeset_link,get_previous_version};

if ( ! $block->context['postId'] ) {
	return;
}

$release_post = get_post( $block->context['postId'] );
if ( ! $release_post ) {
	return;
}

$releases = get_releases( $release_post->post_parent );
$previous_version = get_previous_version( $release_post, $releases );

$copy = sprintf(
	/* translators: %s: URL to the changeset */
	__( 'You have some <a href="%s">unpublished changes</a>. Would you like to release these changes?', 'wporg-plugins'),
	get_trac_changeset_link( $release_post->post_parent, $previous_version )
);

?>
<form method="post" <?php echo get_block_wrapper_attributes(); // phpcs:ignore ?>>
	<input type="hidden" name="publish_release_nonce" value="<?php echo esc_attr( wp_create_nonce( 'publish-release-action' ) ); ?>">

	<!-- wp:group {"style":{"border":{"radius":"2px"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}}},"backgroundColor":"blueberry-4","layout":{"type":"constrained"}} -->
	<div class="wp-block-group has-blueberry-4-background-color has-background" style="border-radius:2px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20)"><!-- wp:heading {"level":4,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
		<h4 class="wp-block-heading" style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'Publish Release', 'wporg-plugins' ); ?></h4>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p  class="has-small-font-size"><?php echo wp_kses_post( $copy ); ?></p>
		<!-- /wp:paragraph -->

		<!-- wp:group {"className":"wporg-release-confirmation-actions","layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group wporg-release-confirmation-actions">
			<div class="wp-block-button is-small">
					<button type="submit" name="action" value="publish" class="wp-block-button__link wp-element-button">
						<?php esc_html_e( 'Publish', 'wporg-plugins' ); ?>
					</button>
				</div>

				<div class="wp-block-button is-small is-style-text">
					<button type="submit" name="action" value="discard" class="wp-block-button__link wp-element-button">
						<?php esc_html_e( 'Discard', 'wporg-plugins' ); ?>
					</button>
				</div>
		</div>
		<!-- /wp:group -->

	</div>
	<!-- /wp:group -->
</form>

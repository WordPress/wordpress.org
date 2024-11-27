<?php

if ( ! $block->context['postId'] ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
	return;
}

$copy = sprintf(
	'This release was last updated on %s by %s.',
	date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $post->post_modified ) ),
	get_the_author_meta( 'display_name', $post->post_author )
);

?>
<form method="post" <?php echo get_block_wrapper_attributes(); // phpcs:ignore ?>>
	<input type="hidden" name="publish_release_nonce" value="<?php echo esc_attr( wp_create_nonce( 'publish-release-action' ) ); ?>">

	<!-- wp:group {"style":{"border":{"radius":"2px"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}}},"backgroundColor":"blueberry-4","layout":{"type":"constrained"}} -->
	<div class="wp-block-group has-blueberry-4-background-color has-background" style="border-radius:2px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20)"><!-- wp:heading {"level":4,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
		<h4 class="wp-block-heading" style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'Publish Release', 'wporg-plugins' ); ?></h4>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"fontSize":"small"} -->
		<p  class="has-small-font-size"><?php esc_html_e( $copy ); ?></p>
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

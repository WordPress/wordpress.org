<?php

$current_post_id = $block->context['postId'];
if ( ! $current_post_id ) {
	return;
}

$post = get_post( $block->context['postId'] );
if ( ! $post ) {
    return;
}

if( 'publish' === $post->post_status) {
    return;
}

$copy = sprintf(
    'This release was last updated on %s by %s.',
    date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $post->post_modified ) ),
    get_the_author_meta( 'display_name', $post->post_author )
);

?>

<div <?php echo get_block_wrapper_attributes(); // phpcs:ignore ?>>

<!-- wp:group {"style":{"border":{"radius":"2px"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}}},"backgroundColor":"blueberry-4","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-blueberry-4-background-color has-background" style="border-radius:2px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20)"><!-- wp:heading {"level":4,"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}}} -->
<h4 class="wp-block-heading" style="margin-top:0;margin-bottom:0"><?php esc_html_e( 'Publish Release', 'wporg-plugins' ); ?></h4>
<!-- /wp:heading -->

<!-- wp:paragraph {"fontSize":"small"} -->
<p  class="has-small-font-size"><?php esc_html_e( $copy ); ?></p>
<!-- /wp:paragraph -->

<!-- wp:group {"layout":{"type":"constrained","justifyContent":"left"}} -->
<div class="wp-block-group"><!-- wp:buttons {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"left","orientation":"horizontal","verticalAlignment":"top"}} -->
<div class="wp-block-buttons"><!-- wp:button {"className":"is-style-fill","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10"}}},"fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size is-style-fill has-small-font-size"><a class="wp-block-button__link wp-element-button" style="padding-top:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10)"><?php esc_html_e( 'Publish', 'wporg-plugins' ); ?></a></div>
<!-- /wp:button -->

<!-- wp:button {"className":"is-style-text","style":{"spacing":{"padding":{"top":"var:preset|spacing|10","bottom":"var:preset|spacing|10"}}},"fontSize":"small"} -->
<div class="wp-block-button has-custom-font-size is-style-text has-small-font-size"><a class="wp-block-button__link wp-element-button" style="padding-top:var(--wp--preset--spacing--10);padding-bottom:var(--wp--preset--spacing--10)"><?php esc_html_e( 'Discard', 'wporg-plugins' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->





</div>




<?php

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
    return;
}

$current_post_id = $block->context['postId'];
if ( ! $current_post_id ) {
	return;
}

?>

<!-- wp:heading {"level":4,"fontSize":"normal"} -->
<h4 class="wp-block-heading has-normal-font-size"><?php esc_html_e( 'Checks', 'wporg-plugins' ); ?></h4>
<!-- /wp:heading -->

<ul>
    <li>First Item</li>
    <li>Second Item</li>
</ul>

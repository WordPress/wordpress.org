<?php

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
    return;
}

if ( ! $block->context['postId'] ) {
	return;
}

$release_post = get_post( $block->context['postId'] );

$plugin_check_errors = get_post_meta( $release_post->ID, 'plugin_check_result', true );

echo do_blocks( 
    sprintf(
        '<!-- wp:heading {"level":4,"fontSize":"normal"} -->
        <h4 class="wp-block-heading has-normal-font-size">%s</h4>
        <!-- /wp:heading -->',
        __( 'Checks', 'wporg-plugins' )
    )
);

if ( empty( $plugin_check_errors ) ) {
    printf(
        '<p>%s</p>',
        __( 'No checks were run.', 'wporg-plugins' )
    );
}


echo '<ul>';


$blocks = sprintf(
    '<!-- wp:wporg/release-check-item {"status":"%1$s","text":"somd<a>sadf</a"} -->%2$s<!-- /wp:wporg/release-check-item -->',
    esc_attr( 'error' ),
    sprintf(
        /* translators: %1$s is a link to the Plugin Check (PCP) tool, %2$s is a "See details" link. */
        __('%1$s detected some issues.', 'wporg-plugins'),
        '<summary>ok</summary><a href="https://wordpress.org/plugins/plugin-check" target="_blank">' . 
        esc_html__( 'Plugin Check (PCP)', 'wporg-plugins' ) . 
        '</a>'
    )
);

echo do_blocks( $blocks );

echo '</ul>';

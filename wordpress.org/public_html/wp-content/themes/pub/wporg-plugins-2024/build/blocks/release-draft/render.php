<?php

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
	return;
}

if ( ! $block->context['postId'] ) {
	return;
}

$post = get_post( $block->context['postId'] );

if ( ! $post ) {
	return;
}

// get release for the post
$query_args = [
	'post_type'      => 'plugin_release',
	'posts_per_page' => 1,
	'post_parent'    => $post->ID,
	'orderby'        => 'date',
	'post_status'    => 'draft',
	'order'          => 'DESC',
];

$latest_draft_query = new WP_Query( $query_args );

if ( ! $latest_draft_query->have_posts() ) {
	return;
}

// Fetch the latest draft post.
$latest_draft_query->the_post();
$post_title = __( 'Trunk', 'wporg-plugins' );

$markup = <<<HTML
<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"width":"1px","radius":"2px"}},"borderColor":"light-grey-1","layout":{"type":"default","justifyContent":"left"}} -->
<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20);border-radius:2px">
		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
		<div class="wp-block-group">
			<!-- wp:group {"layout":{"type":"default"}} -->
			<div class="wp-block-group">

			<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
			<div class="wp-block-group">
				<!-- wp:heading {"style":{"spacing":{"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"heading-4","fontFamily":"inter"} -->
					<h2 class="wp-block-heading has-inter-font-family has-heading-4-font-size" style="margin-top:0;margin-right:0;margin-bottom:0;margin-left:0">
					$post_title
					</h2>
				<!-- /wp:heading -->
				<!-- wp:wporg/release-status /-->
			</div>
			<!-- /wp:group -->

			</div> 
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:wporg/release-confirmation /-->
		<!-- wp:wporg/release-checks /-->
		<!-- wp:wporg/release-flags /-->
	</div>
<!-- /wp:group -->
HTML;

printf(
	'<div %1$s>%2$s</div>',
	get_block_wrapper_attributes(),
	do_blocks( $markup )
);

// Reset global post data.
wp_reset_postdata();

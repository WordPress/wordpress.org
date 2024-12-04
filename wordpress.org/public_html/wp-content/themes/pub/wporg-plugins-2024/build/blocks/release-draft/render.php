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
$query_args = array(
	'post_type'      => 'plugin_release',
	'posts_per_page' => 1,
	'post_parent'    => $post->ID,
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
$post_title = __( 'Trunk', 'wporg-plugins' );
$intro_text = __( 'There are unpublished changes in your trunk folder.', 'wporg-plugins' );

$publish_title       = __( 'Create release', 'wporg-plugins' );
$publish_intro_text  = __( 'Before releasing your plugin, take a moment to verify and update the following values:', 'wporg-plugins' );
$publish_button_text = __( 'Create release', 'wporg-plugins' );

$markup = <<<HTML
<div data-wp-bind--hidden="state.preSubmitting">
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"width":"1px","radius":{"topLeft":"2px","topRight":"2px"}}},"borderColor":"light-grey-1","layout":{"type":"default","justifyContent":"left"}} -->
	<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20);border-top-left-radius:2px;border-top-right-radius:2px">
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

					<!-- wp:paragraph -->
					<p>$intro_text</p>
					<!-- /wp:paragraph -->

					</div> 
					<!-- /wp:group -->
				</div>
				<!-- /wp:group -->
			<!-- wp:wporg/release-commits /-->
			<!-- wp:wporg/release-checks /-->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"style":{"border":{"radius":{"topRight":"0px","topLeft":"0px","bottomLeft":"2px","bottomRight":"2px"},"top":{"width":"0px","style":"none"},"right":{"color":"var:preset|color|light-grey-1","width":"1px"},"bottom":{"color":"var:preset|color|light-grey-1","width":"1px"},"left":{"color":"var:preset|color|light-grey-1","width":"1px"}},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}}},"layout":{"type":"default"}} -->
	<div class="wp-block-group" style="border-top-left-radius:0px;border-top-right-radius:0px;border-bottom-left-radius:2px;border-bottom-right-radius:2px;border-top-style:none;border-top-width:0px;border-right-color:var(--wp--preset--color--light-grey-1);border-right-width:1px;border-bottom-color:var(--wp--preset--color--light-grey-1);border-bottom-width:1px;border-left-color:var(--wp--preset--color--light-grey-1);border-left-width:1px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20)">
		<!-- wp:group {"className":"wporg-release-confirmation-actions","layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group wporg-release-confirmation-actions">
			<div class="wp-block-button is-small">
				<button data-wp-on--click="actions.handlePreSubmit" class="wp-block-button__link wp-element-button">
					$publish_button_text
				</button>
			</div>
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
</div>

<div data-wp-bind--hidden="!state.preSubmitting">
	<!-- wp:group {"style":{"spacing":{"margin":{"bottom:"150px"},"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"width":"1px","radius":{"topLeft":"2px","topRight":"2px"}}},"borderColor":"light-grey-1","layout":{"type":"default","justifyContent":"left"}} -->
	<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;margin-bottom:150px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20);border-top-left-radius:2px;border-top-right-radius:2px">
	<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group">
		<!-- wp:heading {"style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0"}}},"fontSize":"heading-4","fontFamily":"inter"} -->
			<h2 class="wp-block-heading has-inter-font-family has-heading-4-font-size" style="margin-right:0;margin-bottom:0;margin-left:0">
				$publish_title
			</h2>
		<!-- /wp:heading -->
		</div>
	<!-- /wp:group -->

	<!-- wp:paragraph -->
	<p>$publish_intro_text</p>
	<!-- /wp:paragraph -->

	<!-- wp:wporg/release-details-form /-->
	</div>
<!-- /wp:group -->
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

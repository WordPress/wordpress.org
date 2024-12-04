<?php

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
	return;
}

$release_post = get_post( $post->ID );

if ( ! $release_post ) {
	return;
}

// Why use `do_blocks` here? for some reason, just outputting the content directly doesn't work and context gets messed up.
$markup = <<<HTML
<!-- wp:heading -->
<h2 class="wp-block-heading">Releases</h2>
<!-- /wp:heading -->

<!-- wp:wporg/release-draft /-->

<div data-wp-bind--hidden="state.preSubmitting">

<!-- wp:spacer {"height":"var:preset|spacing|20"} -->
<div style="height:var(--wp--preset--spacing--20)" aria-hidden="true" class="wp-block-spacer"></div>
<!-- /wp:spacer -->
 
<!-- wp:query {"queryId":9,"query":{"perPage":100,"pages":0,"offset":0,"postType":"plugin_release","order":"desc","orderBy":"date","author":"","search":"","exclude":[],"sticky":"","inherit":false}} -->
<div class="wp-block-query">
	<!-- wp:post-template -->
	<!-- wp:group {"style":{"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|20","right":"var:preset|spacing|20"}},"border":{"width":"1px","radius":"2px"}},"borderColor":"light-grey-1","layout":{"type":"default","justifyContent":"left"}} -->
	<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;padding-top:var(--wp--preset--spacing--20);padding-right:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--20);border-radius:2px">
		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
		<div class="wp-block-group">
			<!-- wp:group {"layout":{"type":"default"}} -->
			<div class="wp-block-group">
				<!-- wp:post-title {"style":{"spacing":{"margin":{"top":"0","bottom":"0","left":"0","right":"0"}}},"fontSize":"heading-4","fontFamily":"inter"} /-->
				<!-- wp:wporg/release-date /-->
			</div>
			<!-- /wp:group -->

			<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
			<div class="wp-block-group">
				<!-- wp:wporg/release-menu-options /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

	</div><!-- /wp:group -->
	<!-- /wp:post-template -->

	<!-- wp:query-pagination -->
	<!-- wp:query-pagination-previous /-->
	<!-- wp:query-pagination-numbers /-->
	<!-- wp:query-pagination-next /-->
	<!-- /wp:query-pagination -->

	<!-- wp:query-no-results -->
		<!-- wp:paragraph -->
		<p>There are no releases yet</p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->

</div>
<!-- /wp:query -->
</div>
HTML;


// Interactivity API context
$form_context = array(
	'btnDefaultText' => 'Publish release',
	'btnLoadingText' => 'Publishing...',
	'preSubmitting'  => false,
	'isWaiting'      => false,
	'isComplete'     => false,
	'errorMessage'   => '',
	'pluginSlug'     => $release_post->post_name,
	'nonce'          => wp_create_nonce( 'wp_rest' )
);

printf(
	'<div %1$s %2$s %3$s>%4$s</div>',
	'data-wp-interactive="async-action-block"',
	wp_kses_data( get_block_wrapper_attributes() ),
	wp_interactivity_data_wp_context( $form_context ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	do_blocks( $markup ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
);

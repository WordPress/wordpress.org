<?php
/**
 * Title: Release list
 * Slug: wporg-plugins-2024/release-list
 * Inserter: no
 *
 */

?>

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
				<!-- wp:post-date {"style":{"spacing":{"margin":{"top":"0","bottom":"0"}}},"fontSize":"extra-small"} /-->
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
		<p><?php esc_html_e( 'There are no releases yet.', 'wporg-plugins' ); ?></p>
		<!-- /wp:paragraph -->
	<!-- /wp:query-no-results -->

</div>
<!-- /wp:query -->

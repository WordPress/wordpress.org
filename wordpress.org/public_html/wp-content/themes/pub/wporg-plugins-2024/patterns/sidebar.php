<?php
/**
 * Title: Plugin Sidebar
 * Slug: wporg-plugins-2024/sidebar
 * Inserter: no
 */

// This pattern can be expanded as more widgets are converted to blocks and/or block bindings.
?>

<!-- wp:group -->
<div class="wp-block-group">
	<!-- wp:heading {"className":"widget-title"} -->
	<h2 class="wp-block-heading widget-title">Ratings</h2>
	<!-- /wp:heading -->

	<!-- wp:wporg/ratings-stars /-->

	<!-- wp:wporg/ratings-bars /-->

	<!-- wp:group {"style":{"spacing":{"margin":{"top":"var:preset|spacing|10"},"blockGap":"var:preset|spacing|10"}},"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group" style="margin-top:var(--wp--preset--spacing--10)">
		<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"wporg-plugins/meta","args":{"key":"submit-review-link"}}}}} -->
		<p>Add my review</p>
		<!-- /wp:paragraph -->
		
		<!-- wp:paragraph {"metadata":{"bindings":{"content":{"source":"wporg-plugins/meta","args":{"key":"ratings-link"}}}},"className":"wporg-ratings-link"} -->
		<p class="wporg-ratings-link">See all</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

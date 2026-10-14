<?php
/**
 * Title: Plugin
 * Slug: wporg-plugins-2024/single-plugin
 * Inserter: no
 */

use WordPressdotorg\Plugin_Directory\Plugin_Directory;

?>

<!-- wp:group {"align":"wide","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignwide">
	<!-- wp:image {"align":"wide","metadata":{"bindings":{"url":{"source":"wporg-plugins/meta","args":{"key":"plugin-banner-url"}}}}} -->
	<figure class="wp-block-image alignwide"><img alt=""/></figure>
	<!-- /wp:image -->

	<!-- wp:group {"align":"wide","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide">
		<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group">
			<!-- wp:image {"width":"96px","height":"96px","scale":"cover","metadata":{"bindings":{"url":{"source":"wporg-plugins/meta","args":{"key":"plugin-icon-url"}}}}} -->
			<figure class="wp-block-image is-resized"><img alt="" style="object-fit:cover;width:96px;height:96px"/></figure>
			<!-- /wp:image -->

			<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"layout":{"type":"flex","orientation":"vertical"}} -->
			<div class="wp-block-group">
				<!-- wp:post-title {"level":1,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"}},"fontSize":"heading-3","fontFamily":"inter"} /-->

				<!-- wp:post-author-name {"isLink":true} /-->
			</div>
			<!-- /wp:group -->
		</div>
		<!-- /wp:group -->

		<!-- wp:buttons {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}}} -->
		<div class="wp-block-buttons">
			<!-- wp:wporg/favorite-button /-->

			<!-- wp:button {"className":"is-small"} -->
			<div class="wp-block-button is-small"><a class="wp-block-button__link wp-element-button" href="#">Download</a></div>
			<!-- /wp:button -->

			<!-- wp:button {"className":"is-style-outline is-small"} -->
			<div class="wp-block-button is-style-outline is-small"><a class="wp-block-button__link wp-element-button" href="#">Live preview</a></div>
			<!-- /wp:button -->
		</div>
		<!-- /wp:buttons -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:group {"align":"wide","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide">
	<!-- wp:paragraph -->
	<p>[TABS]</p>
	<!-- /wp:paragraph -->

	<!-- wp:columns -->
	<div class="wp-block-columns">
		<!-- wp:column {"width":"66.66%"} -->
		<div class="wp-block-column" style="flex-basis:66.66%">
			<!-- wp:wporg/plugin-section {"section":"description"} /-->

			<!-- wp:wporg/plugin-section {"section":"screenshots"} /-->

			<!-- wp:wporg/plugin-section {"section":"blocks"} /-->

			<!-- wp:wporg/plugin-section {"section":"installation"} /-->

			<!-- wp:wporg/plugin-section {"section":"faq"} /-->

			<!-- wp:wporg/plugin-section {"section":"reviews"} /-->

			<!-- wp:wporg/plugin-section {"section":"developers"} /-->

			<!-- wp:wporg/plugin-section {"section":"changelog"} /-->
		</div>
		<!-- /wp:column -->

		<!-- wp:column {"width":"33.33%"} -->
		<div class="wp-block-column" style="flex-basis:33.33%">
			<!-- wp:pattern {"slug":"wporg-plugins-2024/sidebar"} /-->
		</div>
		<!-- /wp:column -->
	</div>
	<!-- /wp:columns -->
</div>
<!-- /wp:group -->

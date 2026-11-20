<?php
/**
 * Title: Category and event spotlight
 * Slug: wptv/taxonomy-spotlight
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"align":"full","className":"wptv-taxonomy__spotlight","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-taxonomy__spotlight"><!-- wp:columns {"align":"wide","className":"wptv-taxonomy__columns"} -->
<div class="wp-block-columns alignwide wptv-taxonomy__columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:query {"query":{"inherit":true}} -->
<div class="wp-block-query"><!-- wp:post-template {"className":"wptv-taxonomy__lead"} -->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-content {"layout":{"type":"default"}} /-->
<!-- wp:group {"className":"wptv-taxonomy__caption","backgroundColor":"charcoal-1","textColor":"white","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-taxonomy__caption has-white-color has-charcoal-1-background-color has-text-color has-background"><!-- wp:post-terms {"term":"speakers","suffix":":","className":"wptv-card__speakers","fontSize":"small"} /-->
<!-- wp:post-title {"level":2,"isLink":true,"fontSize":"large"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:query {"query":{"inherit":true}} -->
<div class="wp-block-query"><!-- wp:post-template {"className":"wptv-taxonomy__secondary"} -->
<!-- wp:post-featured-image {"aspectRatio":"16/9","className":"wptv-card__thumbnail"} /-->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:post-terms {"term":"speakers","suffix":":","className":"wptv-card__speakers","fontSize":"small"} /-->
<!-- wp:post-title {"level":2,"isLink":true,"fontSize":"small"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->

<?php
/**
 * Title: Inherited video grid
 * Slug: wptv/video-grid
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:query {"query":{"inherit":true},"align":"wide","className":"wptv-listing__query"} -->
<div class="wp-block-query alignwide wptv-listing__query"><!-- wp:post-template {"className":"wptv-listing__videos wptv-taxonomy__remaining is-style-cards-grid","layout":{"type":"grid","columnCount":4}} -->
<!-- wp:post-featured-image {"aspectRatio":"16/9","className":"wptv-card__thumbnail"} /-->
<!-- wp:post-terms {"term":"speakers","suffix":":","className":"wptv-card__speakers"} /-->
<!-- wp:post-title {"level":2,"isLink":true,"fontSize":"normal"} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template -->
<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous /-->
<!-- wp:query-pagination-numbers /-->
<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'No videos were found.', 'wptv' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query -->

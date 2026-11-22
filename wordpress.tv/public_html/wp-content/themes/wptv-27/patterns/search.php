<?php
/**
 * Title: Video search
 * Slug: wptv/search
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"tagName":"main","align":"full","className":"is-flush wptv-listing","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull is-flush wptv-listing"><!-- wp:group {"align":"full","className":"wptv-listing__heading","backgroundColor":"light-grey-2","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-listing__heading has-light-grey-2-background-color has-background"><!-- wp:query-title {"type":"search","level":1,"align":"wide","fontSize":"heading-2"} /-->
<!-- wp:search {"label":"<?php esc_attr_e( 'Search videos', 'wptv' ); ?>","showLabel":false,"placeholder":"<?php esc_attr_e( 'Search videos', 'wptv' ); ?>","buttonText":"<?php esc_attr_e( 'Search', 'wptv' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"className":"wptv-listing__search is-style-light"} /--></div>
<!-- /wp:group -->
<!-- wp:pattern {"slug":"wptv/video-grid"} /--></main>
<!-- /wp:group -->

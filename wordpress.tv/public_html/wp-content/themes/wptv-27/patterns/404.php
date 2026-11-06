<?php
/**
 * Title: Page not found
 * Slug: wptv/404
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"tagName":"main","align":"full","className":"wptv-not-found","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull wptv-not-found"><!-- wp:heading {"level":1} -->
<h1 class="wp-block-heading"><?php esc_html_e( 'Page not found', 'wptv' ); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'The page you requested could not be found. Try searching for a video.', 'wptv' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:search {"label":"<?php esc_attr_e( 'Search videos', 'wptv' ); ?>","showLabel":true,"buttonText":"<?php esc_attr_e( 'Search', 'wptv' ); ?>"} /--></main>
<!-- /wp:group -->

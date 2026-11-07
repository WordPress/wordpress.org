<?php
/**
 * Title: Header
 * Slug: wptv/header
 * Categories: header
 * Block Types: core/template-part/header
 * Inserter: no
 *
 * The navigation links are a fallback: a classic menu assigned to the
 * `primary` location replaces them (inc/setup.php).
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"align":"full","className":"wptv-header","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-header"><!-- wp:group {"align":"wide","className":"wptv-header__bar","layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between","verticalAlignment":"center"}} -->
<div class="wp-block-group alignwide wptv-header__bar"><!-- wp:group {"className":"wptv-header__brand","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group wptv-header__brand"><!-- wp:html -->
<svg class="wptv-header__mark" xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 64 64" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4.548 31.999c0 10.9 6.3 20.3 15.5 24.706L6.925 20.827C5.402 24.2 4.5 28 4.5 31.999z M50.531 30.614c0-3.394-1.219-5.742-2.264-7.57c-1.391-2.263-2.695-4.177-2.695-6.439c0-2.523 1.912-4.872 4.609-4.872 c0.121 0 0.2 0 0.4 0.022C45.653 7.3 39.1 4.5 32 4.548c-9.591 0-18.027 4.921-22.936 12.4 c0.645 0 1.3 0 1.8 0.033c2.871 0 7.316-0.349 7.316-0.349c1.479-0.086 1.7 2.1 0.2 2.3 c0 0-1.487 0.174-3.142 0.261l9.997 29.735l6.008-18.017l-4.276-11.718c-1.479-0.087-2.879-0.261-2.879-0.261 c-1.48-0.087-1.306-2.349 0.174-2.262c0 0 4.5 0.3 7.2 0.349c2.87 0 7.317-0.349 7.317-0.349 c1.479-0.086 1.7 2.1 0.2 2.262c0 0-1.489 0.174-3.142 0.261l9.92 29.508l2.739-9.148 C49.628 35.7 50.5 33 50.5 30.614z M32.481 34.4l-8.237 23.934c2.46 0.7 5.1 1.1 7.8 1.1 c3.197 0 6.262-0.552 9.116-1.556c-0.072-0.118-0.141-0.243-0.196-0.379L32.481 34.4z M56.088 18.8 c0.119 0.9 0.2 1.8 0.2 2.823c0 2.785-0.521 5.916-2.088 9.832l-8.385 24.242c8.161-4.758 13.65-13.6 13.65-23.728 C59.451 27.2 58.2 22.7 56.1 18.83z M32 0c-17.645 0-32 14.355-32 32C0 49.6 14.4 64 32 64s32-14.355 32-32.001 C64 14.4 49.6 0 32 0z M32 62.533c-16.835 0-30.533-13.698-30.533-30.534C1.467 15.2 15.2 1.5 32 1.5 s30.534 13.7 30.5 30.532C62.533 48.8 48.8 62.5 32 62.533z"/></svg>
<!-- /wp:html -->
<!-- wp:site-title {"level":0} /--></div>
<!-- /wp:group -->
<!-- wp:navigation {"overlayMenu":"mobile","className":"wptv-header__nav","ariaLabel":"<?php esc_attr_e( 'Main navigation', 'wptv' ); ?>","layout":{"type":"flex","flexWrap":"nowrap"}} -->
<!-- wp:navigation-link {"label":"<?php esc_attr_e( 'WordCamp.tv', 'wptv' ); ?>","url":"/category/wordcamptv/","kind":"custom"} /-->
<!-- wp:navigation-link {"label":"<?php esc_attr_e( 'How-To', 'wptv' ); ?>","url":"/category/how-to/","kind":"custom"} /-->
<!-- wp:navigation-link {"label":"<?php esc_attr_e( 'Learn WordPress', 'wptv' ); ?>","url":"/category/learn-wordpress/","kind":"custom"} /-->
<!-- wp:navigation-link {"label":"<?php esc_attr_e( 'Get Involved', 'wptv' ); ?>","url":"/get-involved/","kind":"custom"} /-->
<!-- wp:search {"label":"<?php esc_attr_e( 'Search videos', 'wptv' ); ?>","showLabel":false,"placeholder":"<?php esc_attr_e( 'Search videos', 'wptv' ); ?>","buttonText":"<?php esc_attr_e( 'Search', 'wptv' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"className":"is-style-light"} /-->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"className":"wptv-header__submit"} -->
<div class="wp-block-button wptv-header__submit"><a class="wp-block-button__link wp-element-button" href="/submit-video/"><?php esc_html_e( 'Submit a video', 'wptv' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
<!-- /wp:navigation --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

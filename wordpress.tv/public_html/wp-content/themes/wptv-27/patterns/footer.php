<?php
/**
 * Title: Footer
 * Slug: wptv/footer
 * Categories: footer
 * Block Types: core/template-part/footer
 * Inserter: no
 *
 * The Contribute links are a fallback: a classic menu assigned to the
 * `footer` location replaces them (inc/setup.php). The About links are fixed.
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"align":"full","className":"wptv-footer","backgroundColor":"charcoal-1","textColor":"charcoal-5","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-footer has-charcoal-5-color has-charcoal-1-background-color has-text-color has-background"><!-- wp:columns {"align":"wide","className":"wptv-footer__columns"} -->
<div class="wp-block-columns alignwide wptv-footer__columns"><!-- wp:column -->
<div class="wp-block-column"><!-- wp:site-title {"level":0} /-->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e( 'Talks, workshops and tutorials from the WordPress community, from WordCamps and meetups all over the world.', 'wptv' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":2,"className":"wptv-footer__heading"} -->
<h2 class="wp-block-heading wptv-footer__heading"><?php esc_html_e( 'Contribute', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:list {"className":"wptv-footer__links wptv-footer__contribute"} -->
<ul class="wp-block-list wptv-footer__links wptv-footer__contribute"><!-- wp:list-item -->
<li><a href="/submit-video/"><?php esc_html_e( 'Submit a video', 'wptv' ); ?></a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><a href="/get-involved/"><?php esc_html_e( 'Get involved', 'wptv' ); ?></a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><a href="https://make.wordpress.org/">Make WordPress <span aria-hidden="true">↗</span></a></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column -->
<!-- wp:column -->
<div class="wp-block-column"><!-- wp:heading {"level":2,"className":"wptv-footer__heading"} -->
<h2 class="wp-block-heading wptv-footer__heading"><?php esc_html_e( 'About', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:list {"className":"wptv-footer__links"} -->
<ul class="wp-block-list wptv-footer__links"><!-- wp:list-item -->
<li><a href="/about/"><?php esc_html_e( 'About WordPress.tv', 'wptv' ); ?></a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><a href="https://videopress.com/"><?php esc_html_e( 'Powered by VideoPress', 'wptv' ); ?> <span aria-hidden="true">↗</span></a></li>
<!-- /wp:list-item -->
<!-- wp:list-item -->
<li><a href="https://wordpress.org/about/privacy/"><?php esc_html_e( 'Privacy', 'wptv' ); ?> <span aria-hidden="true">↗</span></a></li>
<!-- /wp:list-item --></ul>
<!-- /wp:list --></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->
<!-- wp:group {"align":"wide","className":"wptv-footer__license","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wptv-footer__license"><!-- wp:image {"sizeSlug":"full","linkDestination":"custom","className":"wptv-footer__wporg"} -->
<figure class="wp-block-image size-full wptv-footer__wporg"><a href="https://wordpress.org/"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/wordpress-org.svg' ) ); ?>" alt="WordPress.org"/></a></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php esc_html_e( 'The WordPress® trademark is the intellectual property of the WordPress Foundation.', 'wptv' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

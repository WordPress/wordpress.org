<?php
/**
 * Title: Front page
 * Slug: wptv/front-page
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"tagName":"main","align":"full","className":"is-flush wptv-home","layout":{"type":"default"}} -->
<main class="wp-block-group alignfull is-flush wptv-home"><!-- wp:group {"align":"full","className":"wptv-home__welcome","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-home__welcome"><!-- wp:columns {"align":"wide","verticalAlignment":"center","className":"wptv-home__hero"} -->
<div class="wp-block-columns alignwide are-vertically-aligned-center wptv-home__hero"><!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:heading {"level":1,"fontSize":"heading-1"} -->
<h1 class="wp-block-heading has-heading-1-font-size"><?php esc_html_e( 'Welcome to WordPress.tv', 'wptv' ); ?></h1>
<!-- /wp:heading -->
<!-- wp:paragraph {"fontSize":"large"} -->
<p class="has-large-font-size"><?php esc_html_e( 'WordPress-related videos, curated and moderated by the WordPress community.', 'wptv' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button -->
<div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="/category/wordcamptv/"><?php esc_html_e( 'Browse WordCamp talks', 'wptv' ); ?></a></div>
<!-- /wp:button -->
<!-- wp:button {"className":"is-style-outline"} -->
<div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="/submit-video/"><?php esc_html_e( 'Submit a video', 'wptv' ); ?></a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons --></div>
<!-- /wp:column -->
<!-- wp:column {"verticalAlignment":"center"} -->
<div class="wp-block-column is-vertically-aligned-center"><!-- wp:query {"namespace":"wptv/home-lead","query":{"wptvQuery":"wptv/home-lead","perPage":1,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,"sticky":"ignore"}} -->
<div class="wp-block-query"><!-- wp:post-template -->
<!-- wp:post-content {"layout":{"type":"default"}} /-->
<!-- wp:group {"className":"wptv-home__lead-caption","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-home__lead-caption"><!-- wp:post-title {"level":2,"isLink":true,"fontSize":"small","className":"wptv-home__lead-title"} /--></div>
<!-- /wp:group -->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:column --></div>
<!-- /wp:columns --></div>
<!-- /wp:group -->
<!-- wp:group {"align":"full","className":"wptv-home__featured","backgroundColor":"charcoal-2","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-home__featured has-white-color has-charcoal-2-background-color has-text-color has-background"><!-- wp:group {"align":"wide","className":"wptv-home__section-heading","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-group alignwide wptv-home__section-heading"><!-- wp:heading {"textColor":"white","fontSize":"heading-4"} -->
<h2 class="wp-block-heading has-white-color has-text-color has-heading-4-font-size"><?php esc_html_e( 'Featured videos', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><a href="/tag/featured/"><?php esc_html_e( 'All featured videos', 'wptv' ); ?> <span aria-hidden="true">→</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:query {"namespace":"wptv/featured","query":{"wptvQuery":"wptv/featured","perPage":4,"pages":1,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,"sticky":"ignore"},"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"className":"wptv-home__videos is-style-cards-grid","layout":{"type":"grid","columnCount":4}} -->
<!-- wp:post-featured-image {"aspectRatio":"16/9","className":"wptv-card__thumbnail"} /-->
<!-- wp:post-terms {"term":"speakers","suffix":":","className":"wptv-card__speakers"} /-->
<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"normal"} /-->
<!-- /wp:post-template -->
<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'No featured videos were found.', 'wptv' ); ?></p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->
<!-- wp:group {"align":"full","className":"wptv-home__recent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-home__recent"><!-- wp:heading {"align":"wide","fontSize":"heading-4"} -->
<h2 class="wp-block-heading alignwide has-heading-4-font-size"><?php esc_html_e( 'Recent videos', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:query {"queryId":1,"query":{"inherit":true},"enhancedPagination":true,"align":"wide"} -->
<div class="wp-block-query alignwide"><!-- wp:post-template {"className":"wptv-home__videos is-style-cards-grid","layout":{"type":"grid","columnCount":4}} -->
<!-- wp:post-featured-image {"aspectRatio":"16/9","className":"wptv-card__thumbnail"} /-->
<!-- wp:post-terms {"term":"speakers","suffix":":","className":"wptv-card__speakers"} /-->
<!-- wp:post-title {"level":3,"isLink":true,"fontSize":"normal"} /-->
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
<!-- /wp:query --></div>
<!-- /wp:group -->
<!-- wp:group {"align":"full","className":"wptv-home__categories","backgroundColor":"light-grey-2","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-home__categories has-light-grey-2-background-color has-background"><!-- wp:heading {"align":"wide","fontSize":"heading-4"} -->
<h2 class="wp-block-heading alignwide has-heading-4-font-size"><?php esc_html_e( 'Video categories', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:terms-query {"termQuery":{"perPage":0,"taxonomy":"category","order":"desc","orderBy":"count","include":[],"hideEmpty":true,"showNested":false,"inherit":false},"align":"wide"} -->
<div class="wp-block-terms-query alignwide"><!-- wp:term-template {"className":"wptv-home__categories-grid is-style-cards-grid","layout":{"type":"grid","columnCount":4}} -->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap","justifyContent":"space-between"}} -->
<div class="wp-block-group"><!-- wp:term-name {"level":3,"isLink":true,"fontSize":"normal"} /-->
<!-- wp:term-count /--></div>
<!-- /wp:group -->
<!-- /wp:term-template --></div>
<!-- /wp:terms-query --></div>
<!-- /wp:group -->
<!-- wp:group {"align":"full","className":"wptv-home__events","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-home__events"><!-- wp:group {"align":"wide","className":"wptv-home__section-heading","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-group alignwide wptv-home__section-heading"><!-- wp:heading {"fontSize":"heading-4"} -->
<h2 class="wp-block-heading has-heading-4-font-size"><?php esc_html_e( 'Recent events', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><a href="/category/wordcamptv/"><?php esc_html_e( 'All WordCamp Videos', 'wptv' ); ?> <span aria-hidden="true">→</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:terms-query {"termQuery":{"perPage":12,"taxonomy":"event","order":"desc","orderBy":"id","include":[],"hideEmpty":true,"showNested":false,"inherit":false},"align":"wide"} -->
<div class="wp-block-terms-query alignwide"><!-- wp:term-template {"className":"wptv-home__events-grid is-style-cards-grid","layout":{"type":"grid","columnCount":3}} -->
<!-- wp:term-name {"level":3,"isLink":true,"fontSize":"heading-6"} /-->
<!-- wp:term-count /-->
<!-- /wp:term-template --></div>
<!-- /wp:terms-query --></div>
<!-- /wp:group -->
<!-- wp:group {"align":"full","className":"wptv-home__involved","backgroundColor":"blueberry-1","textColor":"white","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-home__involved has-white-color has-blueberry-1-background-color has-text-color has-background"><!-- wp:heading {"align":"wide","className":"is-style-with-arrow","fontSize":"heading-2"} -->
<h2 class="wp-block-heading alignwide is-style-with-arrow has-heading-2-font-size"><a href="/get-involved/"><?php esc_html_e( 'Get involved', 'wptv' ); ?> <span aria-hidden="true">→</span></a></h2>
<!-- /wp:heading -->
<!-- wp:paragraph {"align":"wide","fontSize":"large"} -->
<p class="alignwide has-large-font-size"><?php esc_html_e( 'WordPress.tv is a volunteer-run project. Help us share knowledge from across the WordPress community.', 'wptv' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></main>
<!-- /wp:group -->

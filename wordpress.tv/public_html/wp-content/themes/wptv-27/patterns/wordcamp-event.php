<?php
/**
 * Title: WordCamp event videos (automatic prototype)
 * Slug: wptv/wordcamp-event
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"className":"wptv-wordcamps__event","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-wordcamps__event"><!-- wp:group {"className":"wptv-home__section-heading","layout":{"type":"flex","justifyContent":"space-between","flexWrap":"wrap"}} -->
<div class="wp-block-group wptv-home__section-heading"><!-- wp:heading {"level":3,"fontSize":"heading-5"} -->
<h3 class="wp-block-heading has-heading-5-font-size">WPTV_EVENT_NAME</h3>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><a href="/#wptv-event"><?php esc_html_e( 'View all', 'wptv' ); ?> <span aria-hidden="true">→</span></a></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group -->
<!-- wp:query {"query":{"perPage":4,"pages":0,"offset":0,"postType":"post","order":"desc","orderBy":"date","inherit":false,"wptvQuery":"wptv/wordcamp-event","wptvEvent":0},"className":"wptv-wordcamps__query"} -->
<div class="wp-block-query wptv-wordcamps__query"><!-- wp:post-template {"className":"wptv-listing__videos is-style-cards-grid","layout":{"type":"grid","columnCount":4}} -->
<!-- wp:post-featured-image {"aspectRatio":"16/9","className":"wptv-card__thumbnail"} /-->
<!-- wp:post-terms {"term":"speakers","suffix":":","className":"wptv-card__speakers"} /-->
<!-- wp:post-title {"level":4,"isLink":true,"fontSize":"normal"} /-->
<!-- wp:post-date /-->
<!-- /wp:post-template --></div>
<!-- /wp:query --></div>
<!-- /wp:group -->

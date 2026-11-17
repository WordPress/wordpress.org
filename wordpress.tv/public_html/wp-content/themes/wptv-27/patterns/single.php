<?php
/**
 * Title: Single video
 * Slug: wptv/single
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"tagName":"main","align":"full","className":"is-flush wptv-single","layout":{"type":"constrained"}} -->
<main class="wp-block-group alignfull is-flush wptv-single"><!-- wp:group {"align":"wide","className":"wptv-single__intro","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wptv-single__intro"><!-- wp:breadcrumbs {"showCurrentItem":false,"fontSize":"small"} /-->
<!-- wp:post-title {"level":1,"fontSize":"heading-2"} /-->
<!-- wp:post-terms {"term":"speakers","separator":", ","fontSize":"large"} /--></div>
<!-- /wp:group -->
<!-- wp:group {"align":"full","className":"wptv-single__player-band","backgroundColor":"charcoal-1","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-single__player-band has-charcoal-1-background-color has-background"><!-- wp:post-content {"align":"wide","className":"wptv-single__player","layout":{"type":"default"}} /--></div>
<!-- /wp:group -->
<!-- wp:group {"align":"wide","className":"wptv-single__reactions","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<div class="wp-block-group alignwide wptv-single__reactions"><!-- wp:jetpack/like /-->
<!-- wp:jetpack/sharing-buttons {"size":"has-small-icon-size","layout":{"type":"flex","justifyContent":"right"}} -->
<ul class="wp-block-jetpack-sharing-buttons has-small-icon-size jetpack-sharing-buttons__services-list" id="jetpack-sharing-serivces-list"><!-- wp:jetpack/sharing-button {"service":"x","label":"X"} /-->
<!-- wp:jetpack/sharing-button {"service":"facebook","label":"Facebook"} /-->
<!-- wp:jetpack/sharing-button {"service":"linkedin","label":"LinkedIn"} /-->
<!-- wp:jetpack/sharing-button {"service":"mail","label":"Mail"} /-->
<!-- wp:jetpack/sharing-button {"service":"share","label":"Share"} /--></ul>
<!-- /wp:jetpack/sharing-buttons --></div>
<!-- /wp:group -->
<!-- wp:group {"align":"wide","className":"wptv-single__information is-style-two-column-display","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wptv-single__information is-style-two-column-display"><!-- wp:group {"className":"wptv-single__details","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-single__details"><!-- wp:heading {"fontSize":"heading-4"} -->
<h2 class="wp-block-heading has-heading-4-font-size"><?php esc_html_e( 'Video details', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:group {"className":"wptv-single__published","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-single__published"><!-- wp:paragraph {"className":"wptv-single__label"} -->
<p class="wptv-single__label"><?php esc_html_e( 'Published', 'wptv' ); ?></p>
<!-- /wp:paragraph -->
<!-- wp:post-date /--></div>
<!-- /wp:group -->
<!-- wp:post-terms {"term":"event","separator":", ","prefix":"<?php esc_attr_e( 'Event: ', 'wptv' ); ?>"} /-->
<!-- wp:post-terms {"term":"speakers","separator":", ","prefix":"<?php esc_attr_e( 'Speakers: ', 'wptv' ); ?>"} /-->
<!-- wp:post-terms {"term":"post_tag","separator":", ","prefix":"<?php esc_attr_e( 'Tags: ', 'wptv' ); ?>"} /-->
<!-- wp:post-terms {"term":"language","separator":", ","prefix":"<?php esc_attr_e( 'Language: ', 'wptv' ); ?>"} /-->
<!-- wp:wptv/producer /-->
<!-- wp:wptv/video-downloads /-->
<!-- wp:group {"className":"wptv-single__license","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-single__license"><!-- wp:image {"sizeSlug":"full","linkDestination":"custom"} -->
<figure class="wp-block-image size-full"><a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="license"><img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/cc-by-sa.png' ) ); ?>" alt="<?php esc_attr_e( 'Creative Commons BY-SA License', 'wptv' ); ?>"/></a></figure>
<!-- /wp:image -->
<!-- wp:paragraph {"fontSize":"small"} -->
<p class="has-small-font-size"><?php echo wp_kses_post( __( 'This work is licensed under a <a href="https://creativecommons.org/licenses/by-sa/4.0/" rel="license">Creative Commons Attribution-ShareAlike 4.0 International License</a>.', 'wptv' ) ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- wp:group {"className":"wptv-single__description","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-single__description"><!-- wp:post-excerpt {"className":"wptv-single__excerpt","showMoreOnNewLine":false} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- wp:comments {"align":"wide","className":"wptv-single__comments is-style-two-column-display"} -->
<div class="wp-block-comments alignwide wptv-single__comments is-style-two-column-display"><!-- wp:comments-title {"showPostTitle":false,"fontSize":"heading-4"} /-->
<!-- wp:group {"className":"wptv-single__discussion","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-single__discussion"><!-- wp:comment-template -->
<!-- wp:group {"className":"wptv-single__comment","layout":{"type":"default"}} -->
<div class="wp-block-group wptv-single__comment"><!-- wp:group {"layout":{"type":"flex","flexWrap":"nowrap"}} -->
<div class="wp-block-group"><!-- wp:avatar {"size":40} /-->
<!-- wp:group {"layout":{"type":"default"}} -->
<div class="wp-block-group"><!-- wp:comment-author-name /-->
<!-- wp:comment-date {"fontSize":"small"} /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- wp:comment-content /-->
<!-- wp:group {"layout":{"type":"flex","flexWrap":"wrap"}} -->
<div class="wp-block-group"><!-- wp:comment-edit-link /-->
<!-- wp:comment-reply-link /--></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- /wp:comment-template -->
<!-- wp:comments-pagination {"layout":{"type":"flex","justifyContent":"space-between"}} -->
<!-- wp:comments-pagination-previous /-->
<!-- wp:comments-pagination-numbers /-->
<!-- wp:comments-pagination-next /-->
<!-- /wp:comments-pagination -->
<!-- wp:post-comments-form /--></div>
<!-- /wp:group --></div>
<!-- /wp:comments -->
<!-- wp:group {"tagName":"nav","align":"wide","className":"wptv-single__navigation","ariaLabel":"<?php esc_attr_e( 'Videos', 'wptv' ); ?>","layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
<nav class="wp-block-group alignwide wptv-single__navigation" aria-label="<?php esc_attr_e( 'Videos', 'wptv' ); ?>"><!-- wp:post-navigation-link {"type":"previous","label":"<?php esc_attr_e( 'Previous: ', 'wptv' ); ?>","showTitle":true,"linkLabel":false,"arrow":"arrow"} /-->
<!-- wp:post-navigation-link {"label":"<?php esc_attr_e( 'Next: ', 'wptv' ); ?>","showTitle":true,"linkLabel":false,"arrow":"arrow"} /--></nav>
<!-- /wp:group --></main>
<!-- /wp:group -->

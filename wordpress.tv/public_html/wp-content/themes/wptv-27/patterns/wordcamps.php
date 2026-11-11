<?php
/**
 * Title: Automatic WordCampTV event sections
 * Slug: wptv/wordcamps
 * Inserter: no
 *
 * @package WordPressTV
 */

?>
<!-- wp:group {"align":"full","className":"wptv-wordcamps__recent","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-wordcamps__recent"><!-- wp:heading {"align":"wide","fontSize":"heading-4"} -->
<h2 class="wp-block-heading alignwide has-heading-4-font-size"><?php esc_html_e( 'Latest WordCamps', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:group {"align":"wide","className":"wptv-wordcamps__recent-content","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wptv-wordcamps__recent-content"><!-- wp:paragraph -->
<p><?php esc_html_e( 'No WordCamp recordings were found for the past twelve months.', 'wptv' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->
<!-- wp:group {"align":"full","className":"wptv-wordcamps__directory","backgroundColor":"light-grey-2","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull wptv-wordcamps__directory has-light-grey-2-background-color has-background"><!-- wp:heading {"align":"wide","fontSize":"heading-4"} -->
<h2 class="wp-block-heading alignwide has-heading-4-font-size"><?php esc_html_e( 'All WordCamp videos', 'wptv' ); ?></h2>
<!-- /wp:heading -->
<!-- wp:group {"align":"wide","className":"wptv-wordcamps__directory-content","layout":{"type":"default"}} -->
<div class="wp-block-group alignwide wptv-wordcamps__directory-content"><!-- wp:paragraph -->
<p><?php esc_html_e( 'No WordCamp events with an identifiable year were found.', 'wptv' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:group -->

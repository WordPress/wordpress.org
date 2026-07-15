<?php
/**
 * Title: Front Page Header
 * Slug: wporg-plugins-2024/front-page-header
 * Inserter: no
 */

$count = wp_count_posts( 'plugin' )->publish;
$count = floor( $count / 1000 ) * 1000;
$description = sprintf(
	/* Translators: Total number of plugins, rounded to thousands (ex, 12,000). */
	_n(
		'Extend your WordPress experience! Browse over %s free plugin.',
		'Extend your WordPress experience! Browse over %s free plugins.',
		$count,
		'wporg-plugins'
	),
	number_format_i18n( $count )
);
?>

<!-- wp:pattern {"slug":"wporg-plugins-2024/front-page-nav"} /-->

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|edge-space","left":"var:preset|spacing|edge-space","bottom":"var:preset|spacing|40"}}},"backgroundColor":"charcoal-2","className":"has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color" style="padding-right:var(--wp--preset--spacing--edge-space);padding-left:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--40);">

	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--40);">
	
		<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"50px","fontStyle":"normal","fontWeight":"400"}},"fontFamily":"eb-garamond"} -->
		<h1 class="wp-block-heading has-eb-garamond-font-family" style="font-size:50px;font-style:normal;font-weight:400"><?php esc_html_e( 'Plugins', 'wporg-plugins' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.3"}},"textColor":"white"} -->
		<p class="has-white-color has-text-color" style="line-height:2.3"><?php echo esc_html( $description ); ?></p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:wporg/language-suggest {"align":"full","endpoint":"<?php echo esc_attr( rest_url( '/plugins/v2/locale-banner' ) ); ?>"} -->
<div class="wp-block-wporg-language-suggest alignfull" data-endpoint="<?php echo esc_attr( rest_url( '/plugins/v2/locale-banner' ) ); ?>"></div>
<!-- /wp:wporg/language-suggest -->

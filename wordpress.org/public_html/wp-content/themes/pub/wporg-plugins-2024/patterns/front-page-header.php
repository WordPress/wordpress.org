<?php
/**
 * Title: Front Page Header
 * Slug: wporg-plugins-2024/front-page-header
 * Inserter: no
 */

?>

<!-- wp:group {"align":"full","style":{"spacing":{"padding":{"right":"var:preset|spacing|edge-space","left":"var:preset|spacing|edge-space","bottom":"var:preset|spacing|30"}}},"backgroundColor":"charcoal-2","className":"has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color","layout":{"type":"constrained"}} -->
<div class="wp-block-group alignfull has-white-color has-charcoal-2-background-color has-text-color has-background has-link-color" style="padding-right:var(--wp--preset--spacing--edge-space);padding-left:var(--wp--preset--spacing--edge-space);padding-bottom:var(--wp--preset--spacing--30);">

	<!-- wp:group {"align":"wide","style":{"spacing":{"padding":{"top":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|30"}},"layout":{"type":"flex","flexWrap":"wrap","verticalAlignment":"bottom"}} -->
	<div class="wp-block-group alignwide" style="padding-top:var(--wp--preset--spacing--40);">
	
		<!-- wp:heading {"level":1,"style":{"typography":{"fontSize":"50px","fontStyle":"normal","fontWeight":"400"}},"fontFamily":"eb-garamond"} -->
		<h1 class="wp-block-heading has-eb-garamond-font-family" style="font-size:50px;font-style:normal;font-weight:400"><?php esc_html_e( 'Plugins', 'wporg' ); ?></h1>
		<!-- /wp:heading -->

		<!-- wp:paragraph {"style":{"typography":{"lineHeight":"2.3"}},"textColor":"white"} -->
		<p class="has-white-color has-text-color" style="line-height:2.3">
		<?php
			$plugin_count = wp_count_posts( 'plugin' )->publish;
			printf(
				/* Translators: Total number of plugins. */
				esc_html( _n( 'Extend your WordPress experience! Browse %s free plugin.', 'Extend your WordPress experience! Browse %s free plugins.', $plugin_count, 'wporg-plugins' ) ),
				esc_html( number_format_i18n( $plugin_count ) )
			);
			?>
		</p>
		<!-- /wp:paragraph -->
	</div>
	<!-- /wp:group -->
	<!-- wp:group {"align":"wide","style":{"spacing":{"bottom":"var:preset|spacing|10"}}}} -->
	<div class="wp-block-group alignwide" style=";padding-bottom:var(--wp--preset--spacing--10)">
		<!-- wp:search {"showLabel":false,"placeholder":"<?php esc_html_e( 'Search plugins...', 'wporg' ); ?>","width":250,"widthUnit":"px","buttonText":"<?php esc_html_e( 'Search', 'wporg' ); ?>","buttonPosition":"button-inside","buttonUseIcon":true,"className":"is-style-secondary-search-control"} /-->
	</div>
	<!-- /wp:group -->
</div>
<!-- /wp:group -->

<!-- wp:wporg/language-suggest {"align":"full","endpoint":"<?php echo esc_attr( rest_url( '/plugins/v2/locale-banner' ) ); ?>"} -->
<div class="wp-block-wporg-language-suggest alignfull" data-endpoint="<?php echo esc_attr( rest_url( '/plugins/v2/locale-banner' ) ); ?>"></div>
<!-- /wp:wporg/language-suggest -->

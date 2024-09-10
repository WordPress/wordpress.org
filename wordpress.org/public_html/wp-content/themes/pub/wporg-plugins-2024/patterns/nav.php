<?php
/**
 * Title: Local Nav
 * Slug: wporg-plugins-2024/nav
 * Inserter: no
 *
 * This nav bar also has the site title, so it should be used on interior pages.
 */

?>

<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->
	<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"textColor":"light-grey-1","layout":{"type":"flex","flexWrap":"nowrap"}} -->
	<div class="wp-block-group has-light-grey-1-color has-text-color">
		<!-- wp:site-title {"level":0,"fontSize":"small","textColor":"white"} /-->

		<?php if ( is_singular() ) : ?>
			<!-- wp:post-title {"level":0,"fontSize":"small","fontFamily":"inter","className":"wporg-local-navigation-bar__fade-in-scroll"} /-->
		<?php elseif ( is_archive() ) : ?>
			<!-- wp:query-title {"type":"archive","level":0,"fontSize":"small","fontFamily":"inter","className":"wporg-local-navigation-bar__fade-in-scroll"} /-->
		<?php elseif ( is_search() ) : ?>
			<!-- wp:query-title {"type":"search","level":0,"fontSize":"small","fontFamily":"inter","className":"wporg-local-navigation-bar__fade-in-scroll"} /-->
		<?php endif; ?>
	</div>
	<!-- /wp:group -->

	<!-- wp:navigation {"menuSlug":"plugins","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","icon":"menu","layout":{"type":"flex","orientation":"horizontal"},"style":{"spacing":{"blockGap":"24px"}},"fontSize":"small"} /-->
<!-- /wp:wporg/local-navigation-bar -->

<?php
	$lang_suggest_endpoint = rest_url( '/plugins/v2/locale-banner' );
	if ( is_singular( 'plugin' ) ) {
		$lang_suggest_endpoint = add_query_arg( 'plugin_slug', get_queried_object()->post_name, $lang_suggest_endpoint );
	}
?>
<!-- wp:wporg/language-suggest {"align":"full","endpoint":"<?php echo esc_attr( $lang_suggest_endpoint ); ?>"} -->
<div class="wp-block-wporg-language-suggest alignfull" data-endpoint="<?php echo esc_attr( $lang_suggest_endpoint ); ?>"></div>
<!-- /wp:wporg/language-suggest -->
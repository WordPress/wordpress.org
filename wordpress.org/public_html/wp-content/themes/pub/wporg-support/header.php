<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WPBBP
 */

namespace WordPressdotorg\Forums;

\WordPressdotorg\skip_to( '#content' );

echo do_blocks( '<!-- wp:wporg/global-header /-->' );

echo do_blocks(
	sprintf(
		'<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

			<!-- wp:paragraph {"fontSize":"small"} -->
			<p class="wp-block-site-title has-small-font-size"><a href="%s">Forums</a></p>
			<!-- /wp:paragraph -->

			<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"forums"} /-->

		<!-- /wp:wporg/local-navigation-bar -->',
		esc_url( home_url( '/forums/' ) )
	)
);

$search_field = do_blocks(
	sprintf(
		'<!-- wp:group {"layout":{"type":"constrained","wideSize":"1280px","contentSize":"680px"},"style":{"spacing":{"padding":{"left":"var:preset|spacing|edge-space","right":"var:preset|spacing|edge-space"}}}} -->
		<div class="wp-block-group alignfull" style="padding-left:var(--wp--preset--spacing--edge-space);padding-right:var(--wp--preset--spacing--edge-space)">

			<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20"}}}} -->
			<div id="wporg-search" class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--20);margin-bottom:var(--wp--preset--spacing--20)">

				<!-- wp:search {"label":"%1$s","showLabel":false,"placeholder":"%2$s","width":232,"widthUnit":"px","buttonText":"%1$s","buttonPosition":"button-inside","buttonUseIcon":true} /-->

			</div>
			<!-- /wp:group -->

		</div>
		<!-- /wp:group -->',
		esc_attr__( 'Search', 'wporg' ),
		esc_attr__( 'Search forums', 'wporg' ),
	)
);

?>

<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg-forums' ); ?></a>

	<div id="content" class="site-content">
		<?php if ( is_front_page() ) : ?>
			<header id="masthead" class="site-header <?php echo is_front_page() ? 'home' : ''; ?>" role="banner">
				<div class="site-branding">
					<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/forums/' ) ); ?>" rel="home"><?php _ex( 'WordPress Support', 'Site title', 'wporg-forums' ); ?></a></h1>

					<p class="site-description">
						<?php
						/* Translators: subhead */
						_e( 'We&#8217;ve got a variety of resources to help you get the most out of WordPress.', 'wporg-forums' );
						?>
					</p>
					<?php get_search_form(); ?>
				</div><!-- .site-branding -->
			</header><!-- #masthead -->
		<?php else : ?>
			<?php echo $search_field; ?>
		<?php endif; ?>
		<div id="lang-guess-wrap"></div>

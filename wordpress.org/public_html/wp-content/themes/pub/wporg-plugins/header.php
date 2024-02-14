<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

\WordPressdotorg\skip_to( '#main' );

$menu_items = array(
	'/browse/favorites/' => __( 'My Favorites', 'wporg-plugins' ),
	'/browse/beta/'      => __( 'Beta Testing', 'wporg-plugins' ),
	'/developers/'       => __( 'Developers', 'wporg-plugins' ),
);

$local_nav_items = array(
	'' => __( 'All', 'wporg-plugins' ),
	'community' => __( 'Community', 'wporg-plugins' ),
	'commercial' => __( 'Commercial', 'wporg-plugins' ),
);

global $wp_query;
$is_beta = 'beta' === $wp_query->get( 'browse' );
$is_favs = 'favorites' === $wp_query->get( 'browse' );
// The filter bar should not be shown on:
// - singular: not relevant on pages or individual plugins.
// - beta: likely unnecessary, these are probably all "community".
// - favorites: not necessary.
$show_filter_bar = ! ( is_singular() || $is_beta || $is_favs );

echo do_blocks( '<!-- wp:wporg/global-header /-->' ); // phpcs:ignore

?>
<div id="page" class="site">
	<div id="content" class="site-content">
		<header id="masthead" class="site-header <?php echo is_home() ? 'home' : ''; ?>" role="banner">
			<div class="site-branding">
				<?php if ( is_home() ) : ?>
					<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Plugins', 'Site title', 'wporg-plugins' ); ?></a></h1>

					<p class="site-description">
						<?php
						$plugin_count = wp_count_posts( 'plugin' )->publish;
						printf(
							/* Translators: Total number of plugins. */
							esc_html( _n( 'Extend your WordPress experience! Browse %s free plugin.', 'Extend your WordPress experience! Browse %s free plugins.', $plugin_count, 'wporg-plugins' ) ),
							esc_html( number_format_i18n( $plugin_count ) )
						);
						?>
					</p>
					<?php get_search_form(); ?>
				<?php else : ?>
					<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Plugins', 'Site title', 'wporg-plugins' ); ?></a></p>

					<nav id="site-navigation" class="main-navigation" role="navigation">
						<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg-plugins' ); ?>"></button>
						<div id="primary-menu" class="menu">
							<ul>
							<?php
							foreach ( $menu_items as $path => $text ) : // phpcs:ignore
								$class = false !== strpos( $_SERVER['REQUEST_URI'], $path ) ? 'active' : ''; // phpcs:ignore
								?>
								<li class="page_item"><a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( home_url( $path ) ); ?>"><?php echo esc_html( $text ); ?></a></li>
							<?php endforeach; ?>
							<li><?php get_search_form(); ?></li>
							</ul>
						</div>
					</nav><!-- #site-navigation -->
				<?php endif; ?>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->


		<?php

echo esc_html( $wp_query->request );

// Import global styles? TODO: Figure out which of these is needed... or if there's a global function to include them..
echo <<<CSS
<style>
body {
	--wp--preset--color--black: #000000;
	--wp--preset--color--cyan-bluish-gray: #abb8c3;
	--wp--preset--color--white: #ffffff;
	--wp--preset--color--pale-pink: #f78da7;
	--wp--preset--color--vivid-red: #cf2e2e;
	--wp--preset--color--luminous-vivid-orange: #ff6900;
	--wp--preset--color--luminous-vivid-amber: #fcb900;
	--wp--preset--color--light-green-cyan: #7bdcb5;
	--wp--preset--color--vivid-green-cyan: #00d084;
	--wp--preset--color--pale-cyan-blue: #8ed1fc;
	--wp--preset--color--vivid-cyan-blue: #0693e3;
	--wp--preset--color--vivid-purple: #9b51e0;
	--wp--preset--color--charcoal-0: #1a1919;
	--wp--preset--color--charcoal-1: #1e1e1e;
	--wp--preset--color--charcoal-2: #23282d;
	--wp--preset--color--charcoal-3: #40464d;
	--wp--preset--color--charcoal-4: #656a71;
	--wp--preset--color--charcoal-5: #979aa1;
	--wp--preset--color--light-grey-1: #d9d9d9;
	--wp--preset--color--light-grey-2: #f6f6f6;
	--wp--preset--color--white-opacity-15: #ffffff26;
	--wp--preset--color--black-opacity-15: #00000026;
	--wp--preset--color--dark-blueberry: #1d35b4;
	--wp--preset--color--deep-blueberry: #213fd4;
	--wp--preset--color--blueberry-1: #3858e9;
	--wp--preset--color--blueberry-2: #7b90ff;
	--wp--preset--color--blueberry-3: #c7d1ff;
	--wp--preset--color--blueberry-4: #eff2ff;
	--wp--preset--color--pomegrade-1: #e26f56;
	--wp--preset--color--pomegrade-2: #ffb7a7;
	--wp--preset--color--pomegrade-3: #ffe9de;
	--wp--preset--color--acid-green-1: #33f078;
	--wp--preset--color--acid-green-2: #c7ffdb;
	--wp--preset--color--acid-green-3: #e2ffed;
	--wp--preset--color--lemon-1: #fff972;
	--wp--preset--color--lemon-2: #fffcb5;
	--wp--preset--color--lemon-3: #fffdd6;
	--wp--preset--gradient--vivid-cyan-blue-to-vivid-purple: linear-gradient(135deg,rgba(6,147,227,1) 0%,rgb(155,81,224) 100%);
	--wp--preset--gradient--light-green-cyan-to-vivid-green-cyan: linear-gradient(135deg,rgb(122,220,180) 0%,rgb(0,208,130) 100%);
	--wp--preset--gradient--luminous-vivid-amber-to-luminous-vivid-orange: linear-gradient(135deg,rgba(252,185,0,1) 0%,rgba(255,105,0,1) 100%);
	--wp--preset--gradient--luminous-vivid-orange-to-vivid-red: linear-gradient(135deg,rgba(255,105,0,1) 0%,rgb(207,46,46) 100%);
	--wp--preset--gradient--very-light-gray-to-cyan-bluish-gray: linear-gradient(135deg,rgb(238,238,238) 0%,rgb(169,184,195) 100%);
	--wp--preset--gradient--cool-to-warm-spectrum: linear-gradient(135deg,rgb(74,234,220) 0%,rgb(151,120,209) 20%,rgb(207,42,186) 40%,rgb(238,44,130) 60%,rgb(251,105,98) 80%,rgb(254,248,76) 100%);
	--wp--preset--gradient--blush-light-purple: linear-gradient(135deg,rgb(255,206,236) 0%,rgb(152,150,240) 100%);
	--wp--preset--gradient--blush-bordeaux: linear-gradient(135deg,rgb(254,205,165) 0%,rgb(254,45,45) 50%,rgb(107,0,62) 100%);
	--wp--preset--gradient--luminous-dusk: linear-gradient(135deg,rgb(255,203,112) 0%,rgb(199,81,192) 50%,rgb(65,88,208) 100%);
	--wp--preset--gradient--pale-ocean: linear-gradient(135deg,rgb(255,245,203) 0%,rgb(182,227,212) 50%,rgb(51,167,181) 100%);
	--wp--preset--gradient--electric-grass: linear-gradient(135deg,rgb(202,248,128) 0%,rgb(113,206,126) 100%);
	--wp--preset--gradient--midnight: linear-gradient(135deg,rgb(2,3,129) 0%,rgb(40,116,252) 100%);
	--wp--preset--font-size--small: 14px;
	--wp--preset--font-size--medium: 20px;
	--wp--preset--font-size--large: 20px;
	--wp--preset--font-size--x-large: 42px;
	--wp--preset--font-size--extra-small: 12px;
	--wp--preset--font-size--normal: 16px;
	--wp--preset--font-size--extra-large: 24px;
	--wp--preset--font-size--huge: 32px;
	--wp--preset--font-size--heading-6: 22px;
	--wp--preset--font-size--heading-5: 26px;
	--wp--preset--font-size--heading-4: 30px;
	--wp--preset--font-size--heading-3: 36px;
	--wp--preset--font-size--heading-2: 50px;
	--wp--preset--font-size--heading-1: 70px;
	--wp--preset--font-size--heading-cta: 120px;
	--wp--preset--font-family--eb-garamond: 'EB Garamond', serif;
	--wp--preset--font-family--inter: 'Inter', sans-serif;
	--wp--preset--font-family--monospace: 'IBM Plex Mono', monospace;
	--wp--preset--font-family--ibm-plex-sans: 'IBM Plex Sans', san-serif;
	--wp--preset--spacing--20: 20px;
	--wp--preset--spacing--30: 30px;
	--wp--preset--spacing--40: clamp(30px, 5vw, 50px);
	--wp--preset--spacing--50: clamp(40px, calc(5vw + 10px), 60px);
	--wp--preset--spacing--60: clamp(20px, calc(10vw - 40px), 80px);
	--wp--preset--spacing--70: 100px;
	--wp--preset--spacing--80: clamp(80px, calc(6.67vw + 40px), 120px);
	--wp--preset--spacing--edge-space: 80px;
	--wp--preset--spacing--10: 10px;
	--wp--preset--spacing--90: clamp(80px, 13.33vw, 160px);
	--wp--preset--shadow--natural: 6px 6px 9px rgba(0, 0, 0, 0.2);
	--wp--preset--shadow--deep: 12px 12px 50px rgba(0, 0, 0, 0.4);
	--wp--preset--shadow--sharp: 6px 6px 0px rgba(0, 0, 0, 0.2);
	--wp--preset--shadow--outlined: 6px 6px 0px -3px rgba(255, 255, 255, 1), 6px 6px rgba(0, 0, 0, 1);
	--wp--preset--shadow--crisp: 6px 6px 0px rgba(0, 0, 0, 1);
	--wp--custom--alignment--aligned-max-width: 50%;
	--wp--custom--button--color--background: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--color--text: var(--wp--preset--color--white);
	--wp--custom--button--border--color: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--border--radius: 2px;
	--wp--custom--button--border--style: solid;
	--wp--custom--button--border--width: 1px;
	--wp--custom--button--hover--color--background: var(--wp--preset--color--deep-blueberry);
	--wp--custom--button--hover--color--text: var(--wp--preset--color--white);
	--wp--custom--button--focus--border--color: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--active--border--color: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--active--color--background: var(--wp--preset--color--charcoal-1);
	--wp--custom--button--active--color--text: var(--wp--preset--color--white);
	--wp--custom--button--outline--border--color: currentColor;
	--wp--custom--button--outline--color--background: transparent;
	--wp--custom--button--outline--color--text: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--outline--hover--border--color: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--outline--hover--color--background: var(--wp--preset--color--deep-blueberry);
	--wp--custom--button--outline--hover--color--text: var(--wp--preset--color--white);
	--wp--custom--button--outline--focus--border--color: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--outline--focus--color--background: var(--wp--preset--color--blueberry-1);
	--wp--custom--button--outline--focus--color--text: var(--wp--preset--color--white);
	--wp--custom--button--outline--active--border--color: var(--wp--preset--color--charcoal-1);
	--wp--custom--button--outline--active--color--background: var(--wp--preset--color--charcoal-1);
	--wp--custom--button--outline--active--color--text: var(--wp--preset--color--white);
	--wp--custom--button--small--spacing--padding--top: 7px;
	--wp--custom--button--small--spacing--padding--bottom: 7px;
	--wp--custom--button--small--spacing--padding--left: 12px;
	--wp--custom--button--small--spacing--padding--right: 12px;
	--wp--custom--button--small--typography--font-size: var(--wp--preset--font-size--small);
	--wp--custom--button--spacing--padding--top: 16px;
	--wp--custom--button--spacing--padding--bottom: 16px;
	--wp--custom--button--spacing--padding--left: 32px;
	--wp--custom--button--spacing--padding--right: 32px;
	--wp--custom--button--text--typography--font-weight: 400;
	--wp--custom--button--typography--font-size: var(--wp--preset--font-size--normal);
	--wp--custom--button--typography--font-weight: 600;
	--wp--custom--button--typography--line-height: var(--wp--custom--body--small--typography--line-height);
	--wp--custom--form--padding--inline: calc(var(--wp--preset--spacing--10) * 1.5);
	--wp--custom--form--padding--block: calc(var(--wp--preset--spacing--10) * 0.8);
	--wp--custom--form--border--color: transparent;
	--wp--custom--form--border--radius: 2px;
	--wp--custom--form--border--style: solid;
	--wp--custom--form--border--width: 0;
	--wp--custom--form--color--label: inherit;
	--wp--custom--form--color--background: var(--wp--preset--color--white);
	--wp--custom--form--color--text: var(--wp--preset--color--charcoal-1);
	--wp--custom--form--color--box-shadow: none;
	--wp--custom--form--typography--font-size: var(--wp--preset--font-size--small);
	--wp--custom--form--active--color--background: var(--wp--preset--color--white);
	--wp--custom--form--active--color--text: var(--wp--preset--color--charcoal-1);
	--wp--custom--form--search--color--label: var(--wp--preset--color--charcoal-4);
	--wp--custom--form--search--color--background: var(--wp--preset--color--light-grey-2);
	--wp--custom--form--search--color--text: var(--wp--preset--color--charcoal-1);
	--wp--custom--gallery--caption--font-size: var(--wp--preset--font-size--small);
	--wp--custom--body--typography--line-height: 1.875;
	--wp--custom--body--typography--text-wrap: pretty;
	--wp--custom--body--short-text--typography--line-height: 1.625;
	--wp--custom--body--extra-small--typography--line-height: 1.67;
	--wp--custom--body--small--typography--line-height: 1.714;
	--wp--custom--body--large--typography--line-height: 1.7;
	--wp--custom--body--extra-large--typography--line-height: 1.58;
	--wp--custom--body--extra-large--breakpoint--small-only--typography--font-size: 20px;
	--wp--custom--body--extra-large--breakpoint--small-only--typography--line-height: 1.5;
	--wp--custom--body--huge--typography--line-height: 1.5;
	--wp--custom--heading--typography--font-family: var(--wp--preset--font-family--eb-garamond);
	--wp--custom--heading--typography--font-weight: 400;
	--wp--custom--heading--typography--line-height: 1.3;
	--wp--custom--heading--typography--text-wrap: balance;
	--wp--custom--heading--cta--typography--line-height: 1;
	--wp--custom--heading--cta--breakpoint--small-only--typography--font-size: 52px;
	--wp--custom--heading--cta--breakpoint--small-only--typography--line-height: 1.08;
	--wp--custom--heading--level-1--typography--line-height: 1.05;
	--wp--custom--heading--level-1--breakpoint--small-only--typography--font-size: 52px;
	--wp--custom--heading--level-1--breakpoint--small-only--typography--line-height: 1.08;
	--wp--custom--heading--level-2--typography--line-height: 1.2;
	--wp--custom--heading--level-2--breakpoint--small-only--typography--font-size: 30px;
	--wp--custom--heading--level-2--breakpoint--small-only--typography--line-height: 1.07;
	--wp--custom--heading--level-3--typography--line-height: 1.28;
	--wp--custom--heading--level-3--breakpoint--small-only--typography--font-size: 26px;
	--wp--custom--heading--level-3--breakpoint--small-only--typography--line-height: 1.15;
	--wp--custom--heading--level-4--typography--line-height: 1.33;
	--wp--custom--heading--level-4--breakpoint--small-only--typography--font-size: 22px;
	--wp--custom--heading--level-4--breakpoint--small-only--typography--line-height: 1.09;
	--wp--custom--heading--level-5--typography--line-height: 1.3;
	--wp--custom--heading--level-5--breakpoint--small-only--typography--font-size: 20px;
	--wp--custom--heading--level-5--breakpoint--small-only--typography--line-height: 1.2;
	--wp--custom--heading--level-6--typography--line-height: 1.27;
	--wp--custom--heading--level-6--breakpoint--small-only--typography--font-size: 18px;
	--wp--custom--heading--level-6--breakpoint--small-only--typography--line-height: 1.22;
	--wp--custom--layout--content-size: 680px;
	--wp--custom--layout--wide-size: 1160px;
	--wp--custom--layout--content-meta-size: calc( var(--wp--custom--layout--wide-size) - var(--wp--custom--layout--content-size) );
	--wp--custom--link--color--text: var(--wp--preset--color--blueberry-1);
	--wp--custom--list--spacing--padding--left: var(--wp--custom--margin--horizontal);
	--wp--custom--margin--baseline: 10px;
	--wp--custom--margin--horizontal: 30px;
	--wp--custom--margin--vertical: 30px;
	--wp--custom--post-comment--typography--font-size: var(--wp--preset--font-size--normal);
	--wp--custom--post-comment--typography--line-height: var(--wp--custom--body--typography--line-height);
	--wp--custom--pullquote--breakpoint--medium--typography--font-size: 50px;
	--wp--custom--pullquote--citation--breakpoint--medium--typography--font-size: 30px;
	--wp--custom--pullquote--citation--typography--font-size: 20px;
	--wp--custom--pullquote--citation--typography--font-family: inherit;
	--wp--custom--pullquote--citation--typography--font-style: italic;
	--wp--custom--pullquote--citation--spacing--margin--top: var(--wp--custom--margin--vertical);
	--wp--custom--pullquote--spacing--min-height: 430px;
	--wp--custom--pullquote--typography--font-size: 40px;
	--wp--custom--pullquote--typography--line-height: 1.4;
	--wp--custom--pullquote--typography--text-align: left;
	--wp--custom--quote--citation--typography--font-size: 20px;
	--wp--custom--quote--citation--typography--font-family: inherit;
	--wp--custom--quote--citation--typography--font-style: normal;
	--wp--custom--quote--typography--text-align: left;
	--wp--custom--separator--opacity: 1;
	--wp--custom--separator--margin: var(--wp--custom--margin--vertical) auto;
	--wp--custom--separator--width: 150px;
	--wp--custom--latest-news--link--color: var(--wp--preset--color--charcoal-1);
	--wp--custom--latest-news--link--spacing: var(--wp--preset--spacing--10);
	--wp--custom--latest-news--link--details--font-size: var(--wp--preset--font-size--small);
	--wp--custom--latest-news--spacing: var(--wp--preset--spacing--40);
	--wp--custom--latest-news--title--font-family: var(--wp--preset--font-family--eb-garamond);
	--wp--custom--latest-news--title--font-size: var(--wp--preset--font-size--heading-5);
	--wp--custom--latest-news--title--line-height: var(--wp--custom--heading--level-3--typography--line-height);
	--wp--custom--brush-stroke--spacing--height: 16px;
	--wp--custom--wporg-site-screenshot--border--color: rgba(30, 30, 30, 0.1);
	--wp--custom--wporg-site-screenshot--border--width: 10px;
	--wp--custom--wporg-site-screenshot--border--radius: 20px;
}
</style>
<style>
.wporg-plugins__filters {
	--wporg--oldtheme--primary-color: #2371a6;
	--wp--custom--wporg-query-filters--toggle--active--color--background: var(--wporg--oldtheme--primary-color);
	--wp--custom--wporg-query-filters--toggle--hover--color--text: var(--wporg--oldtheme--primary-color);
	--wp--custom--button--color--background: var(--wporg--oldtheme--primary-color);
	--wp--custom--button--hover--color--background: var(--wporg--oldtheme--primary-color);
}
.wporg-plugins__filters .wporg-query-filter__modal-actions input.wporg-query-filter__modal-action-clear {
	color: var(--wporg--oldtheme--primary-color);
}
</style>
CSS;

echo do_blocks( <<<BLOCKS

	<!-- wp:group {"align":"wide","className":"wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count","style":{"spacing":{"margin":{"top":"40px","bottom":"40px"}}},"layout":{"type":"flex","flexWrap":"wrap","justifyContent":"space-between"}} -->
	<div class="wp-block-group alignwide wporg-filter-bar wporg-plugins__filters wporg-plugins__filters__no-count" style="margin-top:40px;margin-bottom:40px">
		<!-- wp:group {"className":"wporg-plugins__filters__search","layout":{"type":"flex","flexWrap":"wrap"}} -->
		<div class="wp-block-group wporg-plugins__filters__search">
			<!-- wp:search {"showLabel":false,"placeholder":"Search plugins...","width":100,"widthUnit":"%","buttonText":"Search","buttonPosition":"button-inside","buttonUseIcon":true,"className":"is-style-secondary-search-control"} /-->
			<!-- wp:wporg/query-total /-->
		</div> <!-- /wp:group -->

		<!-- wp:group {"style":{"spacing":{"blockGap":"0"}},"className":"wporg-query-filters","layout":{"type":"flex","flexWrap":"nowrap"}} -->
		<div class="wp-block-group wporg-query-filters">
			<!-- wp:wporg/query-filter {"key":"plugin_category"} /-->
			<!-- wp:wporg/query-filter {"key":"rating","multiple":false} /-->
			<!-- wp:wporg/query-filter {"key":"business_model","multiple":false} /-->
			<!-- wp:wporg/query-filter {"key":"sort","multiple":false} /-->
		</div>
		<!-- /wp:group -->
	</div>
	<!-- /wp:group -->
BLOCKS
);
?>

		<?php if ( false && $show_filter_bar ) : ?>
		<div class="wporg-filter-bar">
			<nav class="wporg-filter-bar__navigation" aria-label="<?php esc_html_e( 'Plugin filters', 'wporg-plugins' ); ?>">
				<ul>
				<?php
				foreach ( $local_nav_items as $slug => $label ) {
					$class = '';
					if (
						// URL contains this filter.
						( $slug === ( $_GET['plugin_business_model'] ?? false ) ) ||
						// Set the All item active if no business model is selected.
						( ! $slug && empty( $_GET['plugin_business_model'] ) )
					) {
						$class = 'is-active';
					}

					if ( $slug ) {
						$url = add_query_arg( array( 'plugin_business_model' => $slug ) );
					} else {
						$url = remove_query_arg( 'plugin_business_model' );
					}

					// Reset pagination.
					$url = remove_query_arg( 'paged', $url );
					$url = preg_replace( '!/page/\d+/?!i', '/', $url );

					printf(
						'<li class="page_item"><a class="%1$s" href="%2$s">%3$s</a></li>',
						esc_attr( $class ),
						esc_url( $url ),
						esc_html( $label )
					);
				}
				?>
				</ul>
			</nav>
		</div>
		<?php endif; ?>

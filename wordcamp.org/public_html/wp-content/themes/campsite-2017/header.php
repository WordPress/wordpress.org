<?php
/**
 * The header for our theme
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js no-svg">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="http://gmpg.org/xfn/11">

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<div id="page" class="site">
		<a class="skip-link screen-reader-text" href="#content">
			<?php esc_html_e( 'Skip to content', 'wordcamporg' ); ?>
		</a>

		<header id="masthead" class="site-header" role="banner">
			<?php if ( has_nav_menu( 'secondary' ) ) : ?>
				<nav id="header-navigation" class="secondary-navigation page-navigation-container" role="navigation">
					<button class="menu-toggle" aria-controls="secondary-menu" aria-expanded="false">
						<?php esc_html_e( 'Secondary Menu', 'wordcamporg' ); ?>
					</button>

					<?php wp_nav_menu( array(
						'theme_location' => 'secondary',
						'menu_id'        => 'secondary-menu',
					) ); ?>
				</nav>
			<?php endif; ?>

			<?php get_template_part( 'template-parts/header/header', 'image' ); ?>

			<nav id="site-navigation" class="main-navigation page-navigation-container" role="navigation">
				<button class="menu-toggle" aria-controls="primary-menu" aria-expanded="false">
					<?php esc_html_e( 'Primary Menu', 'wordcamporg' ); ?>
				</button>

				<?php wp_nav_menu( array(
					'theme_location' => 'primary',
					'menu_id'        => 'primary-menu',
				) ); ?>
			</nav>

			<?php get_sidebar( 'header' ); ?>
		</header>

		<div id="content" class="site-content">
			<?php get_sidebar( 'before-content' ); ?>

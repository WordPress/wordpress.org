<?php

use WP15\Locales;

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
			<?php esc_html_e( 'Skip to content', 'twentyseventeen' ); ?>
		</a>

		<header id="masthead" class="site-header" role="banner">
			<?php get_template_part( 'template-parts/header/header', 'image' ); ?>

			<?php if ( has_nav_menu( 'top' ) ) : ?>
			<div class="navigation-top-container">
				<div class="navigation-top">
					<div class="wrap">
						<?php get_template_part( 'template-parts/navigation/navigation', 'top' ); ?>
					</div>
				</div>

				<?php Locales\locale_switcher(); ?>
			</div>
			<?php endif; ?>
		</header>

		<?php Locales\locale_notice(); ?>

		<?php if ( ( is_single() || ( is_page() && ! twentyseventeen_is_frontpage() ) ) && has_post_thumbnail( get_queried_object_id() ) ) : ?>
			<div class="single-featured-image-header">
				<?php echo get_the_post_thumbnail( get_queried_object_id(), 'twentyseventeen-featured-image' ); ?>
			</div>
		<?php endif; ?>

		<div class="site-content-contain">
			<div id="content" class="site-content">

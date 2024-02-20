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

$menu_items = array(
	/* translators: relative link to the forums home page */
	_x( '/forums/', 'header menu', 'wporg-forums' )                                     => _x( 'Forums', 'header menu', 'wporg-forums' ),
	_x( 'https://wordpress.org/support/guidelines/', 'header menu', 'wporg-forums' )    => _x( 'Guidelines', 'header menu', 'wporg-forums' ),
	_x( 'https://wordpress.org/documentation/', 'header menu', 'wporg-forums' )         => _x( 'Documentation', 'header menu', 'wporg-forums' ),
	_x( 'https://make.wordpress.org/support/handbook/', 'header menu', 'wporg-forums' ) => _x( 'Get Involved', 'header menu', 'wporg-forums' ),
);

\WordPressdotorg\skip_to( '#content' );

echo do_blocks( '<!-- wp:wporg/global-header /-->' );

echo do_blocks( '<!-- wp:wporg/local-navigation-bar {"className":"has-display-contents","backgroundColor":"charcoal-2","style":{"elements":{"link":{"color":{"text":"var:preset|color|white"},":hover":{"color":{"text":"var:preset|color|white"}}}}},"textColor":"white","fontSize":"small"} -->

	<!-- wp:site-title {"level":0,"fontSize":"small"} /-->

	<!-- wp:navigation {"icon":"menu","overlayBackgroundColor":"charcoal-2","overlayTextColor":"white","layout":{"type":"flex","orientation":"horizontal"},"fontSize":"small","menuSlug":"subnav"} /-->

<!-- /wp:wporg/local-navigation-bar -->' );

?>

<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#main"><?php esc_html_e( 'Skip to content', 'wporg-forums' ); ?></a>

	<div id="content" class="site-content">
		<header id="masthead" class="site-header <?php echo is_front_page() ? 'home' : ''; ?>" role="banner">
			<div class="site-branding">
				<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/forums/' ) ); ?>" rel="home"><?php _ex( 'Forums', 'Site title', 'wporg-forums' ); ?></a></h1>
				<?php if ( is_front_page() ) : ?>
					<p class="site-description">
						<?php
						/* Translators: subhead */
						_e( 'We&#8217;ve got a variety of resources to help you get the most out of WordPress.', 'wporg-forums' );
						?>
					</p>
					<?php get_search_form(); ?>
				<?php else : ?>
					<nav id="site-navigation" class="main-navigation" role="navigation">
						<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg-forums' ); ?>"></button>
						<div id="primary-menu" class="menu">
							<ul>
								<?php
								foreach ( $menu_items as $path => $text ) :
									$url = parse_url( $path );

									// Check both host and path (if available).
									$is_same_host = ! empty( $url['host'] ) ? $url['host'] === $_SERVER['HTTP_HOST'] : true;
									$is_same_path = ! empty( $url['path'] ) && false !== strpos( $_SERVER['REQUEST_URI'], $url['path'] );

									$class = ( $is_same_host && $is_same_path ) ? 'class="active" ' : '';

									if ( ! empty( $url['host' ] ) ) {
										$url = esc_url( $path );
									} else {
										$url = esc_url( home_url( $path ) );
									}
								?>
								<li class="page_item"><a <?php echo $class; ?>href="<?php echo $url; ?>"><?php echo esc_html( $text ); ?></a></li>
								<?php endforeach; ?>
								<li><?php get_search_form(); ?></li>
							</ul>
						</div>
					</nav><!-- #site-navigation -->
				<?php endif; ?>
			</div><!-- .site-branding -->
		</header><!-- #masthead -->
		<div id="lang-guess-wrap"></div>

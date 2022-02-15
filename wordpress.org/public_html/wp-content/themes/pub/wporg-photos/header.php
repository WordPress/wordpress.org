<?php
/**
 * The header for our theme.
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;

\WordPressdotorg\skip_to( '#main' );

$menu_items = array(
	'/submit/' => __( 'Submit', 'wporg-photos' ),
	'/guidelines/' => __( 'Guidelines', 'wporg-photos' ),
	'/faq/'    => __( 'FAQ', 'wporg-photos' ),
	'/license/'    => __( 'License', 'wporg-photos' ),
);

if ( FEATURE_2021_GLOBAL_HEADER_FOOTER ) {
	echo do_blocks( '<!-- wp:wporg/global-header /-->' );
} else {
	require WPORGPATH . 'header.php';
}

$show_full_header = is_home() && ! is_paged();
?>

<div id="page" class="site">
	<div id="content" class="site-content">
		<header id="masthead" class="site-header <?php echo $show_full_header ? 'home' : ''; ?>" role="banner">
			<div class="site-branding">
				<?php if ( $show_full_header ) : ?>
					<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Photos', 'Site title', 'wporg-photos' ); ?></a></h1>

					<p class="site-description">
						<?php
						$photo_count = get_photos_count();
						printf(
							/* translators: %s: total number of photos. */
							esc_html( _n( 'Enhance your content! Browse %s free photo.', 'Enhance your content! Browse %s free photos.', $photo_count, 'wporg-photos' ) ),
							number_format_i18n( $photo_count )
						);
						?>
					</p>
					<?php get_search_form(); ?>
				<?php else : ?>
					<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Photos', 'Site title', 'wporg-photos' ); ?></a></p>

					<nav id="site-navigation" class="main-navigation" role="navigation">
						<button class="menu-toggle dashicons dashicons-arrow-down-alt2" aria-controls="primary-menu" aria-expanded="false" aria-label="<?php esc_attr_e( 'Primary Menu', 'wporg-photos' ); ?>"></button>
						<div id="primary-menu" class="menu">
							<ul>
								<?php
								foreach ( $menu_items as $path => $text ) :
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

		<div class="banner"><?php printf(
	/* translators: 1: URL for submitting a photo, 2: URL for reporting an issue. */
	__( 'You&#39;re a bit early to the party! This directory hasn&#39;t yet fully launched. However, you&#39;re welcome to <a href="%1$s">submit photos</a> or <a href="%2$s">report any issues</a> in the meantime. Stay tuned for more.', 'wporg-photos' ),
	'https://wordpress.org/photos/submit/',
	'https://meta.trac.wordpress.org/newticket?component=Photo%20Directory'
); ?></div>

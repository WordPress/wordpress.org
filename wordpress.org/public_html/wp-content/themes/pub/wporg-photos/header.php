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
	'/random/'     => __( 'Random', 'wporg-photos' ),
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
				<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Photos', 'Site title', 'wporg-photos' ); ?></a></h1>

				<?php if ( $show_full_header ) : ?>
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

					<p class="site-contribute">
						<?php
							printf(
								__( 'Or <a href="%s">contribute</a> your own photos!', 'wporg-photos' ),
								esc_url( get_permalink( get_page_by_path( 'submit' ) ) )
							);
						?>
					</p>
				<?php else : ?>
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

<?php
/**
 * The header for our theme.
 *
 * @package wporg-themes
 */

\WordPressdotorg\skip_to( '#themes' );

echo do_blocks( '<!-- wp:wporg/global-header /-->' );

$menu_links = [
	home_url( '/commercial/' ) => __( 'Commercial Themes', 'wporg-themes' ),
];
?>
<header id="masthead" class="site-header <?php echo is_home() ? 'home' : ''; ?>" role="banner">
	<div class="site-branding">
		<?php if ( is_home() ) : ?>
			<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Themes', 'Site title', 'wporg-themes' ); ?></a></h1>

			<p class="site-description">
				<?php
				$theme_count = wp_count_posts( 'repopackage' )->publish;
				printf(
					/* Translators: Total number of themes. */
					esc_html( _n( 'Add style to your WordPress site! Browse %s free theme.', 'Add style to your WordPress site! Browse %s free themes.', $theme_count, 'wporg-themes' ) ),
					esc_html( number_format_i18n( $theme_count ) )
				);
				?>
			</p>

			<form class="search-form"></form>
		<?php else : ?>
			<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Themes', 'Site title', 'wporg-themes' ); ?></a></p>
			<form class="search-form"></form>
		<?php endif; ?>
	</div>
</header>

<?php if ( ! is_page( 'upload' ) ) : ?>
<nav id="site-navigation" class="main-navigation" role="navigation">
	<ul id="menu-theme-directory" class="menu">
		<?php
		foreach ( $menu_links as $url => $text ) :
			$is_current_page = 0 === strpos( "https://{$_SERVER['SERVER_NAME']}{$_SERVER['REQUEST_URI']}", $url );
			$class = $is_current_page ? ' class="current-menu-item"' : '';
		?>
		<li<?php echo $class; ?>><a href="<?php echo esc_url( $url ); ?>"><?php echo $text; ?></a></li>
		<?php endforeach; ?>
	</ul>
</nav>
<?php endif; ?>

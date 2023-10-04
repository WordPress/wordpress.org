<?php
/**
 * The header for our theme.
 *
 * @package wporg-themes
 */

\WordPressdotorg\skip_to( '#themes' );

echo do_blocks( '<!-- wp:wporg/global-header /-->' );

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
<nav id="site-navigation" class="main-navigation" aria-label="<?php _e( 'Main menu', 'wporg-themes' ); ?>" role="navigation">
	<ul id="menu-theme-directory" class="menu">
		<li><a href="<?php echo home_url( '/commercial/' ); ?>"><?php _e( 'Commercial Themes', 'wporg-themes' ); ?></a></li>
	</ul>
</nav>
<?php endif; ?>

<?php
/**
 * The header for our theme.
 *
 * @package wporg-themes
 */

$GLOBALS['pagetitle'] = wp_get_document_title();
global $wporg_global_header_options;
if ( !isset( $wporg_global_header_options['in_wrapper'] ) )
	$wporg_global_header_options['in_wrapper'] = '';
$wporg_global_header_options['in_wrapper'] .= '<a class="skip-link screen-reader-text" href="#themes">' . esc_html__( 'Skip to content', 'wporg-themes' ) . '</a>';

require WPORGPATH . 'header.php';
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
					esc_html( _n( 'Add style to your WordPress site with %s theme.', 'Add style to your WordPress site with %s themes.', $theme_count, 'wporg-themes' ) ),
					esc_html( number_format_i18n( $theme_count ) )
				);
				?>
			</p>
		<?php else : ?>
			<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Themes', 'Site title', 'wporg-themes' ); ?></a></p>
		<?php endif; ?>
	</div>
</header>
<nav id="site-navigation" class="main-navigation" role="navigation">
	<ul id="menu-theme-directory" class="menu">
		<li><a href="<?php echo home_url( '/commercial/' ); ?>"><?php _e( 'Commercial Themes', 'wporg-themes' ); ?></a></li>
	</ul>
</nav>

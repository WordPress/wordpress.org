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
<header id="masthead" class="site-header" role="banner">
	<div class="site-branding">
		<?php if ( is_home() ) : ?>
		<h1 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Theme Directory', 'Site title', 'wporg-themes' ); ?></a></h1>
		<?php else : ?>
			<p class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php echo esc_html_x( 'Theme Directory', 'Site title', 'wporg-themes' ); ?></a></p>
		<?php endif; ?>
	</div>
</header>
<nav id="site-navigation" class="main-navigation" role="navigation">
	<ul id="menu-theme-directory" class="menu">
		<li><a href="<?php echo home_url( '/commercial/' ); ?>"><?php _e( 'Commercial Themes', 'wporg-themes' ); ?></a></li>
		<li><a href="<?php echo home_url( '/getting-started/' ); ?>"><?php _e( 'Upload Your Theme', 'wporg-themes' ); ?></a></li>
	</ul>
</nav>

<?php
/**
 * The header for our theme.
 *
 * @package wporg-themes
 */

$GLOBALS['themes']    = wporg_themes_get_themes_for_query();
$GLOBALS['pagetitle'] = __( 'Theme Directory &laquo; Free WordPress Themes', 'wporg-themes' );

require WPORGPATH . 'header.php';
?>

<div id="headline">
	<div class="wrapper">
		<h2 class="site-title"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" rel="home"><?php _e( 'Theme Directory', 'wporg-themes' ); ?></a></h2>
	</div>
</div>
<nav id="site-navigation" class="main-navigation" role="navigation">
	<ul id="menu-theme-directory" class="menu">
		<li><a href="<?php echo home_url( '/commercial/' ); ?>"><?php _e( 'Commercial Themes', 'wporg-themes' ); ?></a></li>
		<li><a href="<?php echo home_url( '/getting-started/' ); ?>"><?php _e( 'Upload Your Theme', 'wporg-themes' ); ?></a></li>
	</ul>
</nav>

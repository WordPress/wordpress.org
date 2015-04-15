<?php

function rosetta_after_setup_theme() {
	add_theme_support( 'automatic-feed-links' );

	add_theme_support( 'custom-header', array(
		'default-image' => false,
		'header-text' => false,
		'width' => 466,
		'height' => 303,
	) );

	register_nav_menu( 'rosetta_main', __( 'Main Menu', 'rosetta' ) );

	remove_action( 'wp_head', 'locale_stylesheet' );
}
add_action( 'after_setup_theme', 'rosetta_after_setup_theme' );

function rosetta_wp_page_menu_args( $args ) {
	$args['show_home'] = true;
	return $args;
}
add_filter( 'wp_page_menu_args', 'rosetta_wp_page_menu_args' );

function rosetta_admin_footer_nav_menus() {
	echo '<script> wpNavMenu.options.globalMaxDepth = 0; </script>';
}
add_action( 'admin_footer-nav-menus.php', 'rosetta_admin_footer_nav_menus' );

function release_row( $release, $alt_class=false, $first_of_branch_class=false, $reset = false) {
	static $even = true;
	static $last_branch='';

	if ($reset) {
		$even = true;
		$last_branch = '';
		return;
	}
	$classes = array();
	if (!$even && $alt_class) {
		$classes[] = $alt_class;
	}
	$even = !$even;
	if ($release['branch'] != $last_branch && $first_of_branch_class) {
		$classes[] = $first_of_branch_class;
	}
	$last_branch = $release['branch'];
	$classes_str = implode(' ', $classes);
	print "<tr class='$classes_str'>";
	print "\t<td>".$release['version']."</td>";
	print "\t<td>".date_i18n(__('Y-M-d', 'rosetta'), $release['builton'])."</td>";
	print "\t<td><a href='".$release['zip_url']."'>zip</a> <small>(<a href='".$release['zip_url'].".md5'>md5</a>)</small></td>";
	print "\t<td><a href='".$release['targz_url']."'>tar.gz</a> <small>(<a href='".$release['targz_url'].".md5'>md5</a>)</small></td>";
	print "</tr>";

}

function is_locale_css() {
	global $rosetta;
	return file_exists( WP_LANG_DIR . '/css/' . $rosetta->locale . '.css' );
}

function get_locale_css_url() {
	global $rosetta;
	return set_url_scheme( WP_LANG_URL . '/css/' . $rosetta->locale . '.css?' . filemtime( WP_LANG_DIR . '/css/' . $rosetta->locale . '.css' ) );
}

// Makes final space a non-breaking one, to prevent orphaned word.
function rosetta_orphan_control( $string ) {
	return substr_replace( $string, '&nbsp;', strrpos( $string, ' ' ), 1 );
}
add_filter( 'no_orphans', 'rosetta_orphan_control' );

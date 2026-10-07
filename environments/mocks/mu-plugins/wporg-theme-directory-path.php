<?php
/**
 * Plugin Name: WordPress.org Theme Directory Theme Path
 * Description: Registers the theme root inside the WordPress/wporg-theme-directory
 *              repo (mounted at wp-content/wporg-theme-directory by the
 *              theme-directory environment) so WordPress discovers wporg-themes-2024.
 *              A no-op in environments that don't mount that repo.
 *
 * @package theme-directory-env
 */

$wporg_theme_directory_root = ABSPATH . 'wp-content/wporg-theme-directory/source/wp-content/themes';

if ( is_dir( $wporg_theme_directory_root ) ) {
	register_theme_directory( $wporg_theme_directory_root );
}

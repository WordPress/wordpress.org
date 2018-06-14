<?php
/**
 * Plugin Name: HelpHub Contributors
 * Plugin URI:  https://github.com/zzap/HelpHub-Contributors.git
 * Description: WordPress plugin for tracking contributors to wordpress.org Documenation team's HelpHub project.
 * Version:     1.0.0
 * Author:      Milana Cap
 * Author URI:  http://developerka.org/
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: helphub-contributors
 * Domain Path: /languages
 *
 * @package HelpHub_Contributors
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-helphub-contributors.php';

/**
 * Returns instance of HelpHub_Contributors.
 *
 * @since 1.0.0
 * @return object HelpHub_Contributors
 */
function helphub_contributors() {
	return HelpHub_Contributors::instance();
}
add_action( 'plugins_loaded', 'helphub_contributors' );

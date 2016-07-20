<?php
/**
 * Plugin Name: bbPress: User Moderation
 * Description: Mark posts by flagged users for moderation.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * Based on Bozo Users 1.1 by Michael Adams.
 */

/**
 *	This program is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License, version 2, as
 *	published by the Free Software Foundation.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program; if not, see <http://www.gnu.org/licenses/>.
 */

namespace WordPressdotorg\Forums\User_Moderation;

// Store the root plugin file for usage with functions which use the plugin basename
define( __NAMESPACE__ . '\PLUGIN_FILE', __FILE__ );

// Includes
include( dirname( __FILE__ ) . '/inc/class-plugin.php' );

// Instantiate the Plugin
Plugin::get_instance();

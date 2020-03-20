<?php
/**
 * Plugin Name: bbPress: Code blocks formatter
 * Description: Convert wiki markup links into HTML WordPress Codex links.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
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

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'WPORG_bbPress_Code_Blocks' ) ) {
class WPORG_bbPress_Code_Blocks {
	public function __construct() {
		wp_enqueue_script( 'wporg-bbp-code-blocks-expand-contract', plugins_url( 'wporg-bbp-code-blocks-expand-contract.js', __FILE__ ), [ 'jquery' ], 1, true );
		wp_enqueue_style( 'wporg-bbp-code-blocks-expand-contract', plugins_url( 'wporg-bbp-code-blocks-expand-contract.css', __FILE__ ), [], 1 );
	}
} }

new WPORG_bbPress_Code_Blocks;

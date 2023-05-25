<?php
namespace WordPressdotorg\BBP_Code_Blocks_Expand_Contract;
/**
 * Plugin Name: bbPress: Code blocks Expand/Contract
 * Description: Adds an Expander to code blocks which are higher than the default code block size.
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

function wp_head() {
	wp_enqueue_script(
		'wporg-bbp-code-blocks-expand-contract',
		plugins_url( 'wporg-bbp-code-blocks-expand-contract.js', __FILE__ ),
		[ 'jquery' ],
		filemtime( __DIR__ . '/wporg-bbp-code-blocks-expand-contract.js' ),
		true
	);
	wp_localize_script( 'wporg-bbp-code-blocks-expand-contract', 'bbpCodeBlocksExpandContract', [
		'expand'   => __( 'Expand', 'wporg-forums' ),
		'contract' => __( 'Contract', 'wporg-forums' ),
	] );
	wp_enqueue_style(
		'wporg-bbp-code-blocks-expand-contract', 
		plugins_url( 'wporg-bbp-code-blocks-expand-contract.css', __FILE__ ),
		[],
		filemtime( __DIR__ . '/wporg-bbp-code-blocks-expand-contract.css' ),
	);
}
add_action( 'wp_head', __NAMESPACE__ . '\wp_head' );
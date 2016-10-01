<?php
/**
 * Plugin Name: Codexify
 * Description: Convert wiki markup links into HTML WordPress Codex links.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * Based on Codify 0.1 by Michael Adams.
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

if ( ! class_exists( 'WPORG_Codexify' ) ) {
class WPORG_Codexify {
	const BASE_URI = 'https://codex.wordpress.org/';

	public function __construct() {
		add_filter( 'content_save_pre', array( $this, 'convert_wiki_links' ) );
	}

	function convert_wiki_links( $content ) {
		if ( strpos( $content, '[[' ) !== false && strpos( $content, ']]' ) !== false ) {
			$content = preg_replace_callback( '%\[\[([ #:\/\w]+)\]\]%', array( $this, 'convert_base_wiki_link' ), $content );
			$content = preg_replace_callback( '%\[\[([ #:\/\w]+)\|([^\]]+)\]\]%', array( $this, 'convert_vbar_wiki_link' ), $content );
		}
		return $content;
	}

	/**
	 * Replace simple Wiki markup links ( [[ path ]] ) with Codex links
	 *
	 * @param array $matches Matches path of Wiki-formatted links
	 * @return string
	 */
	public static function convert_base_wiki_link( $matches ) {
		$path = preg_replace( '/[\s]+/', '_', trim( $matches[1] ) );
		return '<a href="' . esc_url( self::BASE_URI . $path ) . '">' . esc_html( $matches[1] ) . '</a>';
	}

	/**
	 * Replace extended Wiki markup links ( [[ path | title ]] ) with Codex
	 * links
	 *
	 * @param array $matches Matches path and title of Wiski-formatted links
	 * @return string
	 */
	public static function convert_vbar_wiki_link( $matches ) {
		$path = preg_replace( '/[\s]+/', '_', trim( $matches[1] ) );
		return '<a href="' . esc_url( self::BASE_URI . $path ) . '">' . esc_html( $matches[2] ) . '</a>';
	}
} }

new WPORG_Codexify;

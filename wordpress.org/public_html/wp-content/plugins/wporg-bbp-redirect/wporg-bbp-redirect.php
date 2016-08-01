<?php
/**
 * Plugin Name: bbPress: Redirect
 * Description: Redirect bbPress 1 id-based slugs to new title-based slugs.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
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

if ( ! class_exists( 'WPORG_bbPress_Old_Id_Redirect' ) ) {
class WPORG_bbPress_Old_Id_Redirect {
	public function __construct() {
		// Add action to template redirect after wp_old_slug_redirect.
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 11 );
	}

	public function maybe_redirect() {
		global $wp_query;

		if ( is_404() && '' !== $wp_query->query_vars['name'] ) {
			global $wpdb;

			if ( get_query_var( 'post_type' ) ) {
				$post_type = get_query_var( 'post_type' );
			}

			if ( is_array( $post_type ) ) {
				if ( count( $post_type ) > 1 ) {
					return;
				}
				$post_type = reset( $post_type );
			}

			if ( in_array( get_query_var( 'post_type' ), array( 'forum', 'topic' ) ) ) {
				$maybe_id = get_query_var( 'name' );
				if ( is_numeric( $maybe_id ) ) {
					if ( absint( $maybe_id ) != $maybe_id ) {
						return;
					}
				}
				$post_id = $wpdb->get_var( $wpdb->prepare( "
					SELECT post_id
					FROM $wpdb->postmeta, $wpdb->posts
					WHERE ID = post_id
						AND post_type = %s
						AND meta_key = %s
						AND meta_value = %d
					LIMIT 1",
					$post_type,
					sprintf( '_bbp_old_%s_id', $post_type ),
					$maybe_id ) );
				if ( $post_id ) {
					$link = get_permalink( $post_id );
					if ( $link ) {
						wp_safe_redirect( $link, 301 );
						exit;
					}
				}
			}
		}
	}
} }

new WPORG_bbPress_Old_Id_Redirect;

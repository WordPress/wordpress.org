<?php
/**
 * Plugin Name: bbPress: User Mention Autocomplete
 * Description: Add User Mention Autocompletion to WordPress.org forums.
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

if ( ! class_exists( 'WPORG_bbPress_User_Mention_Autocomplete' ) ) {
class WPORG_bbPress_User_Mention_Autocomplete {

	public function __construct() {
		add_action( 'bbp_head', [ $this, 'wp_head' ] );
	}

	public function wp_head() {

		if (
			! is_user_logged_in() ||
			! bbp_current_user_can_access_create_reply_form() ||
			! bbp_is_single_topic()
		) {
			return;
		}

		wp_enqueue_script( 'wporg-bbp-user-mention-autocomplete', plugins_url( 'wporg-bbp-user-mention-autocomplete.js', __FILE__ ), [ 'jquery', 'jquery-atwho' ], 1, true );
		wp_localize_script( 'wporg-bbp-user-mention-autocomplete', 'wporgUserMentionAutocompleteData', [
			'currentUser' => wp_get_current_user()->user_login,
		]);
		wp_enqueue_style( 'wporg-bbp-user-mention-autocomplete', plugins_url( 'wporg-bbp-user-mention-autocomplete.css', __FILE__ ), [], 1 );

		wp_register_script( 'jquery-atwho', plugins_url( 'jquery.atwho.min.js', __FILE__ ), [ 'jquery', 'jquery-caret' ], '1.5.0', true );
		wp_register_script( 'jquery-caret', plugins_url( 'jquery.caret.min.js', __FILE__ ), [ 'jquery' ], '2016-02-27', true );
		// wp_enqueue_style( 'jquery-atwho', plugins_url( 'jquery.atwho.min.css', __FILE__ ), [], '1.5.0' );
	}
} }

new WPORG_bbPress_User_Mention_AutoComplete;


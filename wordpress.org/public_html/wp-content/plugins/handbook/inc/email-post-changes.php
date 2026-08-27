<?php
/**
 * Email handbook changes to users who opt-in on a per-page basis. Requires the Email Post Changes plugin.
 * Author: Nacin
 */

class WPorg_Handbook_Email_Post_Changes {

	public static function init() {
		add_filter( 'email_post_changes_emails', array( __CLASS__, 'email_post_changes_emails' ), 10, 3 );
		add_action( 'admin_post_wporg_watchlist', array( __CLASS__, 'update_watchlist' ) );
		add_action( 'option_email_post_changes', array( __CLASS__, 'option_email_post_changes' ) );
	}

	public static function option_email_post_changes( $values ) {
		$values['post_types'] = apply_filters( 'wporg_email_changes_for_post_types', $values['post_types'] );
		return $values;
	}

	public static function email_post_changes_emails( $emails, $post_before, $post_after ) {
		$post = get_post( $post_after );
		$users = get_post_meta( $post->ID, '_wporg_watchlist', true );
		if ( ! $users )
			return $emails;

		cache_users( $users );
		$users = array_filter( array_map( 'get_userdata', $users ) );
		foreach ( $users as $user )
			$emails[] = $user->user_email;
		return $emails;
	}

	public static function update_watchlist() {
		$post_id = absint( $_GET['post_id'] );
		if ( ! $post_id || ! $post = get_post( $post_id ) ) {
			wp_redirect( home_url( '/' ) );
			exit;
		}

		if ( ! is_user_logged_in() || ! wporg_is_handbook_post_type( $post->post_type ) ) {
			wp_redirect( get_permalink( $post_id ) );
			exit;
		}

		$watch = ! empty( $_GET['watch'] );
		$verify = wp_verify_nonce( $_GET['_wpnonce'], ( $watch ? 'watch-' : 'unwatch-' ) . $post_id );

		if ( $verify ) {
			$user_id = get_current_user_id();
			$users = $_users = get_post_meta( $post_id, '_wporg_watchlist', true ) ?: array();
			if ( $watch ) {
				if ( ! in_array( $user_id, $users, true ) ) {
					$users[] = $user_id;
				}
			} else {
				// Remove by value; an array_search() result used as an index deletes $users[0] on a miss (false => 0).
				$users = array_values( array_diff( $users, array( $user_id ) ) );
			}
			update_post_meta( $post_id, '_wporg_watchlist', $users, $_users );
		}
		wp_redirect( get_permalink( $post_id ) );
		exit;
	}
}
WPorg_Handbook_Email_Post_Changes::init();


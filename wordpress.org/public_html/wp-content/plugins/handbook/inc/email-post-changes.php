<?php
/**
 * Email handbook changes to users who opt-in on a per-page basis. Requires the Email Post Changes plugin.
 * Author: Nacin
 */

class WPorg_Handbook_Email_Post_Changes {

	function init() {
		add_action( 'widgets_init', array( __CLASS__, 'handbook_sidebar' ), 11 ); // After P2
		add_filter( 'email_post_changes_emails', array( __CLASS__, 'email_post_changes_emails' ), 10, 3 );
		add_action( 'admin_post_wporg_watchlist', array( __CLASS__, 'update_watchlist' ) );
		add_action( 'option_email_post_changes', array( __CLASS__, 'option_email_post_changes' ) );
	}

	function handbook_sidebar() {

		require_once dirname( __FILE__ ) . '/widgets.php';
		register_widget( 'WPorg_Handbook_Widget' );
	}

	function option_email_post_changes( $values ) {
		$values['post_types'] = apply_filters( 'wporg_email_changes_for_post_types', $values['post_types'] );
		return $values;
	}

	function email_post_changes_emails( $emails, $post_before, $post_after ) {
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

	function update_watchlist() {
		$post_id = absint( $_GET['post_id'] );
		if ( ! $post_id || ! $post = get_post( $post_id ) ) {
			wp_redirect( home_url( '/' ) );
			exit;
		}

		$watch = ! empty( $_GET['watch'] );
		$verify = wp_verify_nonce( $_GET['_wpnonce'], ( $watch ? 'watch-' : 'unwatch-' ) . $post_id );

		if ( $verify ) {
			$users = $_users = get_post_meta( $post_id, '_wporg_watchlist', true );
			if ( $watch )
				$users[] = get_current_user_id();
			else
				unset( $users[ array_search( get_current_user_id(), $users ) ] );
			update_post_meta( $post_id, '_wporg_watchlist', $users, $_users );
		}
		wp_redirect( get_permalink( $post_id ) );
		exit;
	}
}
WPorg_Handbook_Email_Post_Changes::init();


<?php

require_once __DIR__ . '/class-wporg-two-factor-email.php';

class WPORG_Two_Factor_Slack extends WPORG_Two_Factor_Email {

	/**
	 * The user meta token key.
	 *
	 * @type string
	 */
	const TOKEN_META_KEY = '_two_factor_slack_token';

	/**
	 * Ensures only one instance of this class exists in memory at any one time.
	 *
	 * @since 0.1-dev
	 */
	static function get_instance() {
		static $instance;
		$class = __CLASS__;
		if ( ! is_a( $instance, $class ) ) {
			$instance = new $class;
		}
		return $instance;
	}

	public function get_label() {
		return 'Slack'; // Not marked for translation as this shouldn't be called/displayed.
	}

	protected function get_slack_details( $user_id ) {
		global $wpdb;

		static $cached_details = [];
		if ( isset( $cached_details[ $user_id ] ) ) {
			return $cached_details[ $user_id ];
		}

		// TODO abstract this? memcache it?
		$user_details = $wpdb->get_var( $wpdb->prepare( "SELECT profiledata FROM slack_users WHERE user_id = %d LIMIT 1", $user_id ) );
		$user_details = $user_details ? json_decode( $user_details ) : false;

		$cached_details[ $user_id ] = $user_details;

		return $user_details;
	}

	public function is_available_for_user( $user ) {
		$user_details = $this->get_slack_details( $user->ID );

		// Require the Slack account to exist, and for the user to have 2FA enabled on Slack.
		return $user_detauls && empty( $user_details->deleted ) && ! empty( $user_details->has_2fa );
	}

	public function generate_and_email_token( $user ) {
		return $this->generate_and_slack_token( $user );
	}

	public function generate_and_slack_token( $user ) {
		$token = $this->generate_token( $user->ID );

		$message = "Please enter the following verification code on WordPress.org to complete your login:\n{$token}";

		$slack_details = $this->get_slack_details( $user->ID );

		if ( $slack_details->id ) {
			// TODO: Replace this with a named Slack Bot.
			return slack_dm( $message, $slack_details->id );
		}

		return false;
	}

}

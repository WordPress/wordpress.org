<?php
namespace WordPressdotorg\Plugin_Directory\Email;

use WP_User;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

const PLUGIN_TEAM_EMAIL = 'plugins@wordpress.org';

abstract class Base {
	// Emails should use the following properties to customise content:
	protected $plugin = false; // The plugin this email relates to.
	protected $user   = false; // The user this email is being sent to - Always singular.
	protected $who    = false; // The user triggering this email, who performed the action, which may not be the currently logged in user.
	protected $args   = [];    // The arguments passed for this email.

	// Email subclass should set this if needed - Not an error, only prevents the email from being sent.
	protected $required_args = [];

	// These must be set by the email.
	abstract public function subject();
	abstract public function body();

	// Internal use only.
	protected $users = false;

	/**
	 * @param $plugin The plugin this email relates to.
	 * @param $users[] A list of users to email.
	 * @param $args[] A list of args that the email requires.
	 */
	public function __construct( $plugin, $users, $args = array() ) {
		$this->plugin = Plugin_Directory::get_plugin_post( $plugin );

		// Don't cast an object to an array, but rather an array of object.
		if ( is_object( $users ) ) {
			$users = [ $users ];
		}

		foreach ( (array) $users as $user ) {
			if ( is_string( $user ) && is_email( $user ) ) {
				$user = get_user_by( 'email', $user );
			} elseif ( ! ( $user instanceof WP_User ) ) {
				$user = new WP_User( $user );
			}

			if ( $user->exists() ) {
				$this->users[] = $user;
			}

			// TODO: Email non-account holders?
		}
		$this->user = $this->users[0] ?? false;

		$this->args = $args;
		$this->who = $this->args['who'] ?? wp_get_current_user();
	}

	/**
	 * Trigger the sending process for each email.
	 */
	public function send() {
		$success = [];

		// Process `body()` and `subject()` for each user, so that the email can be personalised.
		foreach ( $this->users as $u ) {
			// TODO: Set user locale?
			$this->user = $u;

			$success[] = $this->_send();
		}

		// As long as one email succeeds, it's truthful.
		return in_array( true, $success, true );
	}

	/**
	 * Send an individual email.
	 */
	protected function _send() {
		if ( ! $this->should_send() ) {
			return false;
		}

		$subject = sprintf(
			/* translators: Email subject prefix. 1: The email subject. */
			__( '[WordPress Plugin Directory] %1$s', 'wporg-plugins' ),
			$this->subject()
		);

		$email = $this->user->user_email;
		$body  = $this->body();
		$body .= "\n\n" . $this->get_email_signature();

		return wp_mail(
			$email,
			$subject,
			$body,
			sprintf(
				'From: "%s" <%s>',
				'WordPress Plugin Directory',
				PLUGIN_TEAM_EMAIL
			)
		);
	}

	/**
	 * Checks that an email should be sent.
	 */
	public function should_send() {
		// Check all the required datas are set.
		if ( ! $this->have_required_data() ) {
			return false;
		}

		// Blocked users don't need emails.
		if ( ! empty( $this->user->caps[ 'bbp_blocked'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Validate that all the required states are set before sending an email.
	 */
	public function have_required_data() {
		if ( ! $this->plugin || ! $this->users ) {
			return false;
		}

		foreach ( $this->required_args as $arg ) {
			if ( ! isset( $this->args[ $arg ] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * A common email signature to attach to the bottom of all emails.
	 */
	public function get_email_signature() {
		/* translators: This is an email signature. Do not translate the URL. */
		return __( '--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins' );
	}

	/**
	 * A simple way to convert a WP_User object to a displayable username.
	 * This shouldn't be needed, but unfortunately often is on WordPress.org.
	 */
	public function user_text( $user ) {
		if ( ! $user instanceof WP_User ) {
			$user = new WP_User( $user );
		}
		if ( ! $user || ! $user->exists() ) {
			return 'Unknown';
		}

		return $user->display_name ?: $user->user_login;
	}
}

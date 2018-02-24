<?php
/**
 * Plugin Name: WP.org Two Factor
 * Description: WordPress.org-specific Two Factor authentication tweaks.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  https://wordpress.org/
 * License:     GPLv2 or later
 *
 * @package WordPressdotorg\TwoFactor
 */

class WPORG_Two_Factor {
	public function __construct() {
		add_filter( 'two_factor_providers', [ $this, 'two_factor_providers' ] );
	}

	public function two_factor_providers( $providers ) {
		// Limit 2FA to certain users during Development.
		if ( ! is_user_logged_in() || ! in_array( get_current_user_id(), [ 148148, 196012, 8772187 ] ) ) {
			return array();
		}

		$wporg_providers = array(
			'WPORG_Two_Factor_Email'        => __DIR__ . '/providers/class-wporg-two-factor-email.php',
			'WPORG_Two_Factor_Totp'         => __DIR__ . '/providers/class-wporg-two-factor-totp.php',
			'WPORG_Two_Factor_Backup_Codes' => __DIR__ . '/providers/class-wporg-two-factor-backup-codes.php',
			'WPORG_Two_Factor_Slack'        => __DIR__ . '/providers/class-wporg-two-factor-slack.php'
		);

		return $wporg_providers;
	}
}
new WPORG_Two_Factor();

<?php
/**
 * Tests for the account activity the SSO records.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Covers the last-login and last-password-change stamps, which the support and
 * security teams read when working out what happened to an account.
 */
class WPOrg_SSO_User_Records_Test extends WPOrg_SSO_TestCase {

	/**
	 * The SSO instance under test.
	 *
	 * @var WPOrg_SSO_Test_Double
	 */
	protected WPOrg_SSO_Test_Double $sso;

	/**
	 * The account being recorded against.
	 *
	 * @var WP_User
	 */
	protected WP_User $user;

	/**
	 * Puts the SSO on the login host with an account to record against.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		$this->sso  = $this->make_sso( 'login.wordpress.org', '/wp-login.php', '/wp-login.php' );
		$this->user = new WP_User( $this->factory->user->create() );

		// The instance hooks `wp_set_password` itself, so the fixtures below would write the stamp under test.
		remove_action( 'wp_set_password', array( $this->sso, 'record_last_password_change_reset' ), 10 );
	}

	/**
	 * Logging in is stamped on the account, in UTC.
	 *
	 * @return void
	 */
	public function test_logging_in_is_recorded(): void {
		$this->sso->record_last_logged_in( $this->user->user_login, $this->user );

		$this->assertSame( gmdate( 'Y-m-d' ), substr( (string) get_user_meta( $this->user->ID, 'last_logged_in', true ), 0, 10 ) );
	}

	/**
	 * Changing the password on a profile is stamped on the account.
	 *
	 * @return void
	 */
	public function test_password_change_is_recorded(): void {
		$this->sso->record_last_password_change(
			$this->user->ID,
			$this->user,
			array( 'user_pass' => 'a-different-hash' )
		);

		$this->assertNotEmpty( get_user_meta( $this->user->ID, 'last_password_change', true ) );
	}

	/**
	 * A profile update that leaves the password alone records nothing.
	 *
	 * @return void
	 */
	public function test_other_profile_changes_are_not_recorded(): void {
		$this->sso->record_last_password_change(
			$this->user->ID,
			$this->user,
			array( 'user_pass' => $this->user->user_pass )
		);

		$this->assertSame( '', get_user_meta( $this->user->ID, 'last_password_change', true ) );
	}

	/**
	 * A password reset is stamped on the account too.
	 *
	 * @return void
	 */
	public function test_password_reset_is_recorded(): void {
		$old_user_data = clone $this->user;

		wp_set_password( 'a-brand-new-password', $this->user->ID );
		clean_user_cache( $this->user->ID );

		$this->sso->record_last_password_change_reset( 'a-brand-new-password', $this->user->ID, $old_user_data );

		$this->assertNotEmpty( get_user_meta( $this->user->ID, 'last_password_change', true ) );
	}

	/**
	 * Core passes the old user data as an array on some paths.
	 *
	 * @see https://core.trac.wordpress.org/ticket/22114#comment:32
	 *
	 * @return void
	 */
	public function test_password_reset_handles_array_shaped_old_data(): void {
		$old_user_data = array( 'user_pass' => $this->user->user_pass );

		wp_set_password( 'a-brand-new-password', $this->user->ID );
		clean_user_cache( $this->user->ID );

		$this->sso->record_last_password_change_reset( 'a-brand-new-password', $this->user->ID, $old_user_data );

		$this->assertNotEmpty( get_user_meta( $this->user->ID, 'last_password_change', true ) );
	}

	/**
	 * A reset that did not change the hash records nothing.
	 *
	 * @return void
	 */
	public function test_unchanged_password_is_not_recorded_as_a_reset(): void {
		$this->sso->record_last_password_change_reset( 'unused', $this->user->ID, $this->user );

		$this->assertSame( '', get_user_meta( $this->user->ID, 'last_password_change', true ) );
	}

	/**
	 * Bulk password work can opt out of the stamp.
	 *
	 * @return void
	 */
	public function test_recording_can_be_switched_off(): void {
		add_filter( 'wporg_record_last_password_change', '__return_false' );

		$this->sso->record_last_password_change(
			$this->user->ID,
			$this->user,
			array( 'user_pass' => 'a-different-hash' )
		);

		$this->assertSame( '', get_user_meta( $this->user->ID, 'last_password_change', true ) );
	}
}

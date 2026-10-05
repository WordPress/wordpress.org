<?php
/**
 * Tests for confirming a mailbox's deletion with its name.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Tests;

use App\Mailbox;
use App\User;
use Illuminate\Foundation\Testing\TestResponse;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider;
use Modules\WPOrgSSO\Services\Passwords;

require_once __DIR__ . '/SsoTestCase.php';

/**
 * Covers the delete dialog and the server's name check, which stands in for core's password check.
 */
final class DeleteMailboxTest extends SsoTestCase {

	/**
	 * Mailbox to delete.
	 *
	 * @var Mailbox
	 */
	private $mailbox;

	/**
	 * Administrator without a password, as everyone who logs in through WordPress.org.
	 *
	 * @var User
	 */
	private $admin;

	/**
	 * Creates the mailbox and the administrator.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->mailbox = $this->create_mailbox( 'Plugin Review' );

		// The settings page escapes these, and PHP 8 deprecates escaping null, which tests turn into an exception.
		foreach ( array( 'aliases', 'auto_bcc', 'before_reply', 'from_name_custom', 'signature' ) as $field ) {
			$this->mailbox->$field = '';
		}
		$this->mailbox->save();

		$this->admin = $this->create_user( User::ROLE_ADMIN );
		Passwords::clear( $this->admin );
		$this->admin->save();
	}

	/**
	 * The dialog asks for the mailbox's name, not a password.
	 *
	 * @return void
	 */
	public function test_dialog_asks_for_name_not_password(): void {
		$html = $this->settings_page( $this->admin );

		$this->assertStringContainsString( 'wporgsso-mailbox-name', $html );
		$this->assertStringContainsString( 'data-name="Plugin Review"', $html );
		$this->assertStringNotContainsString( 'delete-mailbox-pass', $html );
	}

	/**
	 * Administrators with a break-glass password get core's password field too.
	 *
	 * @return void
	 */
	public function test_dialog_asks_break_glass_administrators_for_both(): void {
		$admin = $this->create_user( User::ROLE_ADMIN );
		\Artisan::call( 'wporgsso:password', array( 'email' => $admin->email ) );

		$html = $this->settings_page( $admin->refresh() );

		$this->assertStringContainsString( 'wporgsso-mailbox-name', $html );
		$this->assertStringContainsString( 'delete-mailbox-pass', $html );
	}

	/**
	 * Without the name, or with another one, the mailbox stays.
	 *
	 * @return void
	 */
	public function test_refuses_without_the_name(): void {
		foreach ( array( null, '', 'Plugins', 'plugin review', array( 'Plugin Review' ) ) as $name ) {
			$response = $this->delete_mailbox( $this->admin, $name )->assertStatus( 200 );

			$this->assertSame(
				array(
					'status' => 'error',
					'msg'    => 'Type the mailbox’s name, Plugin Review, to confirm.',
				),
				$response->decodeResponseJson()
			);

			$this->assertNotNull( Mailbox::find( $this->mailbox->id ) );
		}
	}

	/**
	 * With its name, the mailbox is deleted without a password; spaces around the name don't matter.
	 *
	 * @return void
	 */
	public function test_deletes_with_the_name(): void {
		$this->assertSame( 'success', $this->delete_mailbox( $this->admin, ' Plugin Review ' )->decodeResponseJson()['status'] );

		$this->assertNull( Mailbox::find( $this->mailbox->id ) );
	}

	/**
	 * Administrators with a break-glass password need both the name and the password.
	 *
	 * @return void
	 */
	public function test_break_glass_administrators_need_name_and_password(): void {
		$admin = $this->create_user( User::ROLE_ADMIN );
		\Artisan::call( 'wporgsso:password', array( 'email' => $admin->email ) );
		$password = trim( \Artisan::output() );
		$admin->refresh();

		$this->assertSame( 'Type the mailbox’s name, Plugin Review, to confirm.', $this->delete_mailbox( $admin, '', $password )->decodeResponseJson()['msg'] );
		$this->assertSame( 'Please double check your password, and try again', $this->delete_mailbox( $admin, 'Plugin Review', 'wrong' )->decodeResponseJson()['msg'] );
		$this->assertNotNull( Mailbox::find( $this->mailbox->id ) );

		$this->assertSame( 'success', $this->delete_mailbox( $admin, 'Plugin Review', $password )->decodeResponseJson()['status'] );
		$this->assertNull( Mailbox::find( $this->mailbox->id ) );
	}

	/**
	 * Those who may not delete the mailbox get core's answer, not its name.
	 *
	 * @return void
	 */
	public function test_others_get_cores_answer(): void {
		$user = $this->create_user( User::ROLE_USER );

		$this->assertSame( 'Not enough permissions', $this->delete_mailbox( $user, '' )->decodeResponseJson()['msg'] );
		$this->assertNotNull( Mailbox::find( $this->mailbox->id ) );
	}

	/**
	 * Renders the mailbox's settings page.
	 *
	 * @param User $user Logged-in user.
	 * @return string HTML.
	 */
	private function settings_page( User $user ): string {
		return (string) $this->as( $user )->get( route( 'mailboxes.update', array( 'id' => $this->mailbox->id ) ) )->assertStatus( 200 )->getContent();
	}

	/**
	 * Asks to delete the mailbox, like core's dialog with this module's field.
	 *
	 * @param User              $user     Logged-in user.
	 * @param string|array|null $name     Name typed in, or null to leave the field out.
	 * @param string|null       $password Password typed in, or null to leave the field out.
	 * @return TestResponse
	 */
	private function delete_mailbox( User $user, $name, ?string $password = null ): TestResponse {
		$data = array(
			'_token'     => csrf_token(),
			'action'     => 'delete_mailbox',
			'mailbox_id' => $this->mailbox->id,
		);
		if ( null !== $name ) {
			$data['mailbox_name'] = $name;
		}
		if ( null !== $password ) {
			$data['password'] = $password;
		}

		return $this->as( $user )->post( route( 'mailboxes.ajax' ), $data );
	}

	/**
	 * Acts as a user who logged in through WordPress.org.
	 *
	 * @param User $user User.
	 * @return self
	 */
	private function as( User $user ): self {
		$username = 'user' . $user->id;
		Account::connect( (int) $user->id, $username );

		return $this->actingAs( $user )->withSession(
			array(
				WPOrgSSOServiceProvider::SESSION_USERNAME => $username,
				WPOrgSSOServiceProvider::SESSION_CHECKED_AT => time(),
			)
		);
	}
}

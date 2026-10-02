<?php
/**
 * Tests for copying WordPress.org account details to FreeScout users.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Tests;

use App\User;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Services\UserSync;
use Modules\WPOrgSSO\Services\WordPressOrgUser;

require_once __DIR__ . '/SsoTestCase.php';

/**
 * Covers name, email, and avatar updates.
 */
final class UserSyncTest extends SsoTestCase {

	/**
	 * User connected to the "rita" account.
	 *
	 * @var User
	 */
	private $user;

	/**
	 * Creates a connected user.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->user = $this->create_user( User::ROLE_USER );
		Account::connect( (int) $this->user->id, 'rita' );
	}

	/**
	 * Logging in brings name, email, and avatar up to date.
	 *
	 * @return void
	 */
	public function test_login_updates_user(): void {
		$this->complete( $this->post_to_acs( $this->start_login( 'rita' ) ) );

		$this->user->refresh();
		$this->assertSame( 'Rita', $this->user->first_name );
		$this->assertSame( 'Reviewer', $this->user->last_name );
		$this->assertSame( 'rita@example.org', $this->user->email );
		$this->assertNotEmpty( $this->user->photo_url );
		$this->assertNotEmpty( Account::for_user( (int) $this->user->id )->avatar_hash );
	}

	/**
	 * Photos are saved at twice the size they're shown at.
	 *
	 * @return void
	 */
	public function test_saves_photo_at_double_size(): void {
		$shown = (int) config( 'app.user_photo_size' );

		$this->sync();

		$size = getimagesize( \Storage::disk( 'local' )->path( User::PHOTO_DIRECTORY . '/' . $this->user->refresh()->photo_url ) );
		$this->assertSame( 2 * $shown, $size[0] );
		$this->assertSame( $shown, (int) config( 'app.user_photo_size' ) );
	}

	/**
	 * An unchanged avatar isn't saved again.
	 *
	 * @return void
	 */
	public function test_keeps_photo_of_unchanged_avatar(): void {
		$this->sync();
		$photo = $this->user->refresh()->photo_url;

		$this->sync();

		$this->assertSame( $photo, $this->user->refresh()->photo_url );
	}

	/**
	 * A changed avatar replaces the photo taken from the old one.
	 *
	 * @return void
	 */
	public function test_replaces_photo_of_changed_avatar(): void {
		$this->sync();
		$hash = Account::for_user( (int) $this->user->id )->avatar_hash;

		$this->avatars['https://secure.gravatar.com/avatar/rita?s=256&d=mm'] = self::image( 0, 0, 255 );
		$this->sync();

		$this->assertNotSame( $hash, Account::for_user( (int) $this->user->id )->avatar_hash );
	}

	/**
	 * A photo from before the module was switched on gives way to the avatar.
	 *
	 * @return void
	 */
	public function test_replaces_earlier_photo(): void {
		$this->user->photo_url = 'uploaded.jpg';
		$this->user->save();

		$this->sync();

		$this->assertNotSame( 'uploaded.jpg', $this->user->refresh()->photo_url );
		$this->assertNotEmpty( $this->user->photo_url );
	}

	/**
	 * An email address another user has isn't taken from them.
	 *
	 * @return void
	 */
	public function test_keeps_email_another_user_has(): void {
		$email        = $this->user->email;
		$other        = $this->create_user();
		$other->email = 'rita@example.org';
		$other->save();

		$this->sync();

		$this->assertSame( $email, $this->user->refresh()->email );
		$this->assertSame( 'Rita', $this->user->first_name );
	}

	/**
	 * An avatar FreeScout can't read doesn't stop the rest.
	 *
	 * @return void
	 */
	public function test_unreadable_avatar_is_logged_not_thrown(): void {
		$this->avatars['https://secure.gravatar.com/avatar/rita?s=256&d=mm'] = 'not an image';

		$this->sync();

		$this->assertSame( 'Rita', $this->user->refresh()->first_name );
		$this->assertEmpty( $this->user->photo_url );
	}

	/**
	 * Only Gravatar's avatars are downloaded, as the app server fetches them.
	 *
	 * @return void
	 */
	public function test_ignores_avatar_off_gravatar(): void {
		$this->accounts['rita']['avatar_url']                = 'https://169.254.169.254/avatar.png';
		$this->avatars['https://169.254.169.254/avatar.png'] = self::image( 0, 255, 0 );

		$this->sync();

		$this->assertSame( 'Rita', $this->user->refresh()->first_name );
		$this->assertEmpty( $this->user->photo_url );
	}

	/**
	 * Syncs the user with the "rita" account.
	 *
	 * @return void
	 */
	private function sync(): void {
		UserSync::sync( $this->user->refresh(), new WordPressOrgUser( $this->accounts['rita'] ) );
	}
}

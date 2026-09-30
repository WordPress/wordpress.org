<?php
/**
 * Tests for saving senders' WordPress.org avatars.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Tests;

use App\Customer;
use Modules\WPOrgSidebar\Jobs\SyncSenderAvatar;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers which avatars are fetched, and which photos they replace.
 */
final class SyncSenderAvatarTest extends TestCase {

	/**
	 * Only Gravatar's HTTPS URLs are fetched.
	 *
	 * @return void
	 */
	public function test_fetches_only_gravatar_urls(): void {
		$this->assertTrue( SyncSenderAvatar::is_avatar_url( 'https://secure.gravatar.com/avatar/abc?s=256&d=404' ) );
		$this->assertFalse( SyncSenderAvatar::is_avatar_url( 'http://secure.gravatar.com/avatar/abc' ) );
		$this->assertFalse( SyncSenderAvatar::is_avatar_url( 'https://gravatar.com.example.org/avatar/abc' ) );
		$this->assertFalse( SyncSenderAvatar::is_avatar_url( 'https://127.0.0.1/avatar/abc' ) );
		$this->assertFalse( SyncSenderAvatar::is_avatar_url( '' ) );
	}

	/**
	 * A photo an agent uploaded isn't replaced.
	 *
	 * @return void
	 */
	public function test_keeps_an_uploaded_photo(): void {
		$sender             = $this->create_sender();
		$sender->photo_url  = 'uploaded.png';
		$sender->photo_type = Customer::PHOTO_TYPE_UKNOWN;
		$sender->save();

		( new SyncSenderAvatar( (int) $sender->id, 'https://secure.gravatar.com/avatar/abc?d=404' ) )->handle();

		$sender->refresh();
		$this->assertSame( 'uploaded.png', $sender->photo_url );
		$this->assertEquals( Customer::PHOTO_TYPE_UKNOWN, $sender->photo_type );
	}
}

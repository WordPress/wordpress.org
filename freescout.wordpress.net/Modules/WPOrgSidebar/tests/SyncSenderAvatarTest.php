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
	 * Web server answering 404 to everything, when a test needs one.
	 *
	 * @var resource|null
	 */
	private $server;

	/**
	 * Stops the web server.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		if ( $this->server ) {
			proc_terminate( $this->server );
			proc_close( $this->server );
			$this->server = null;
		}

		parent::tearDown();
	}

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

	/**
	 * A synced photo goes once the account's avatar is removed.
	 *
	 * @return void
	 */
	public function test_removes_a_synced_photo_once_the_avatar_is_gone(): void {
		$sender             = $this->create_sender();
		$sender->photo_url  = 'synced.jpg';
		$sender->photo_type = Customer::PHOTO_TYPE_GRAVATAR;
		$sender->save();

		( new SyncSenderAvatar( (int) $sender->id, $this->serve_not_found() . '/avatar/abc?d=404' ) )->handle();

		$sender->refresh();
		$this->assertEmpty( $sender->photo_url );
		$this->assertNull( $sender->photo_type );
	}

	/**
	 * A synced photo stays while Gravatar can't be reached.
	 *
	 * @return void
	 */
	public function test_keeps_a_synced_photo_while_gravatar_is_unreachable(): void {
		$sender             = $this->create_sender();
		$sender->photo_url  = 'synced.jpg';
		$sender->photo_type = Customer::PHOTO_TYPE_GRAVATAR;
		$sender->save();

		( new SyncSenderAvatar( (int) $sender->id, 'http://127.0.0.1:9/avatar/abc?d=404' ) )->handle();

		$sender->refresh();
		$this->assertSame( 'synced.jpg', $sender->photo_url );
		$this->assertEquals( Customer::PHOTO_TYPE_GRAVATAR, $sender->photo_type );
	}

	/**
	 * Starts a local web server that answers 404 to everything: this directory holds no static files.
	 *
	 * @return string Base URL.
	 */
	private function serve_not_found(): string {
		$port    = random_int( 20000, 60000 );
		$silence = array( 'file', '/dev/null', 'w' );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_proc_open
		$this->server = proc_open( array( PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', __DIR__ ), array( $silence, $silence, $silence ), $pipes );

		// FreeScout turns the warning of a refused connection into an exception.
		for ( $tries = 0; $tries < 50; $tries++ ) {
			try {
				fclose( fsockopen( '127.0.0.1', $port ) );
				return 'http://127.0.0.1:' . $port;
			} catch ( \ErrorException $e ) {
				usleep( 100000 );
			}
		}

		$this->fail( 'The web server did not start.' );
	}
}

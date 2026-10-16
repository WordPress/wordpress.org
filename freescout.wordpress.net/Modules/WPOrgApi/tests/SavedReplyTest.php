<?php
/**
 * Tests for the saved reply endpoint.
 *
 * @package WordPressdotorg\FreeScout\WPOrgApi
 */

declare( strict_types = 1 );

namespace Modules\WPOrgApi\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestResponse;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgApi\Providers\WPOrgApiServiceProvider;
use Modules\WPOrgApi\Services\SignedRequest;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers SavedReplyController, through its route.
 */
final class SavedReplyTest extends TestCase {

	/**
	 * Creates the Saved Replies module's table before the test's transaction starts, as creating it inside one would
	 * commit it; the module is a paid one, so it isn't installed for tests.
	 *
	 * @return void
	 */
	protected function refreshApplication(): void {
		parent::refreshApplication();

		Schema::dropIfExists( 'saved_replies' );
		Schema::create(
			'saved_replies',
			static function ( Blueprint $table ): void {
				$table->increments( 'id' );
				$table->integer( 'mailbox_id' );
				$table->string( 'name', 75 );
				$table->longText( 'text' )->nullable();
				$table->integer( 'user_id' );
				$table->timestamps();
			}
		);
	}

	/**
	 * Registers the module, and switches the Saved Replies module on.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgApiServiceProvider::class );
		$this->app['router']->getRoutes()->refreshNameLookups();

		\App\Module::clearModulesCache();
		\App\Module::setActive( 'savedreplies', true );
		\App\Module::clearModulesCache();

		// After DatabaseTransactions' rollback, which was registered first.
		$this->beforeApplicationDestroyed(
			static function (): void {
				Schema::dropIfExists( 'saved_replies' );
				\App\Module::clearModulesCache();
			}
		);
	}

	/**
	 * The reply is the named one of the mailbox asked about, not another mailbox's of the same name.
	 *
	 * @return void
	 */
	public function test_hands_over_the_mailboxs_saved_reply(): void {
		$plugins = $this->create_mailbox( 'Plugins' );
		$themes  = $this->create_mailbox( 'Themes' );
		$this->create_reply( (int) $themes->id, 'Closed: Security Exploit', '<p>Themes’ text.</p>' );
		$this->create_reply( (int) $plugins->id, 'Closed: Security Exploit', '<p>Your plugin PLUGIN_LINK was closed.</p>' );

		$response = $this->request(
			array(
				'mailbox_id' => $plugins->id,
				'name'       => 'Closed: Security Exploit',
			)
		);

		$response->assertStatus( 200 );
		$this->assertSame(
			array(
				'name' => 'Closed: Security Exploit',
				'text' => '<p>Your plugin PLUGIN_LINK was closed.</p>',
			),
			$response->json()
		);
	}

	/**
	 * Requests WordPress.org didn't sign, for this endpoint, just now, are refused.
	 *
	 * @dataProvider data_unsigned_requests
	 *
	 * @param string $secret   Secret they're signed with.
	 * @param string $endpoint Endpoint they name.
	 * @param int    $age      How long ago they were sent, in seconds.
	 * @return void
	 */
	public function test_refuses_requests_wordpress_org_did_not_sign( string $secret, string $endpoint, int $age ): void {
		$mailbox = $this->create_mailbox();
		$this->create_reply( (int) $mailbox->id, 'Welcome', '<p>Hi</p>' );

		$response = $this->request(
			array(
				'mailbox_id' => $mailbox->id,
				'name'       => 'Welcome',
			),
			$secret,
			$endpoint,
			$age
		);

		$response->assertStatus( 403 );
	}

	/**
	 * Requests to refuse.
	 *
	 * @return array[]
	 */
	public static function data_unsigned_requests(): array {
		return array(
			'another secret'   => array( 'not-the-secret', 'saved-reply', 0 ),
			'another endpoint' => array( 'test-secret', 'something-else', 0 ),
			'too old'          => array( 'test-secret', 'saved-reply', 301 ),
		);
	}

	/**
	 * Adds a saved reply.
	 *
	 * @param int    $mailbox_id Mailbox ID.
	 * @param string $name       Name.
	 * @param string $text       HTML.
	 * @return void
	 */
	private function create_reply( int $mailbox_id, string $name, string $text ): void {
		\DB::table( 'saved_replies' )->insert(
			array(
				'mailbox_id' => $mailbox_id,
				'name'       => $name,
				'text'       => $text,
				'user_id'    => 1,
			)
		);
	}

	/**
	 * Sends a request the way WordPress.org does.
	 *
	 * @param array  $payload  Payload.
	 * @param string $secret   Secret to sign it with; the configured one by default.
	 * @param string $endpoint Endpoint it names.
	 * @param int    $age      How long ago it was sent, in seconds.
	 * @return TestResponse
	 */
	private function request( array $payload, string $secret = 'test-secret', string $endpoint = 'saved-reply', int $age = 0 ): TestResponse {
		$payload['endpoint'] = $endpoint;
		$payload['sent_at']  = time() - $age;
		$body                = (string) json_encode( $payload );

		return $this->call(
			'POST',
			route( 'wporgapi.saved_reply' ),
			array(),
			array(),
			array(),
			array(
				'CONTENT_TYPE'           => 'application/json',
				'HTTP_X_WPORG_SIGNATURE' => SignedRequest::sign( $body, $secret ),
			),
			$body
		);
	}
}

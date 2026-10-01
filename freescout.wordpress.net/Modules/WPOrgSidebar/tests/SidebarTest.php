<?php
/**
 * Tests for the sidebar panels and their endpoint.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Tests;

use App\Conversation;
use App\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\RateLimiter;
use Modules\WPOrgSidebar\Http\Controllers\PanelController;
use Modules\WPOrgSidebar\Providers\WPOrgSidebarServiceProvider;
use Modules\WPOrgSidebar\Services\Client;
use Psr\Http\Message\RequestInterface;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers panel rendering and access control on the panel endpoint.
 */
final class SidebarTest extends TestCase {

	/**
	 * Conversation under test.
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * Registers the module and creates a conversation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSidebarServiceProvider::class );

		// Routes registered after boot aren't in the name index yet.
		$this->app['router']->getRoutes()->refreshNameLookups();

		$this->conversation = $this->create_conversation( $this->create_mailbox(), $this->create_sender() );
	}

	/**
	 * Each configured panel gets a placeholder pointing at its endpoint.
	 *
	 * @return void
	 */
	public function test_renders_a_placeholder_per_panel(): void {
		ob_start();
		\Eventy::action( 'conversation.after_customer_sidebar', $this->conversation );
		$html = (string) ob_get_clean();

		foreach ( array_keys( (array) config( 'wporgsidebar.panels' ) ) as $panel ) {
			$this->assertStringContainsString( '/wporgsidebar/' . $this->conversation->id . '/' . $panel . '"', $html );
		}
	}

	/**
	 * Without a secret, panels are left out rather than failing on every load.
	 *
	 * @return void
	 */
	public function test_renders_nothing_without_secret(): void {
		config( array( 'wporgsidebar.secret' => '' ) );

		ob_start();
		\Eventy::action( 'conversation.after_customer_sidebar', $this->conversation );

		$this->assertSame( '', trim( (string) ob_get_clean() ) );
	}

	/**
	 * Only configured panels are proxied.
	 *
	 * @return void
	 */
	public function test_unknown_panel_is_not_found(): void {
		$this->actingAs( $this->create_user() )
			->get( '/wporgsidebar/' . $this->conversation->id . '/unknown' )
			->assertStatus( 404 );
	}

	/**
	 * Agents can't read panels for conversations in mailboxes they can't access.
	 *
	 * @return void
	 */
	public function test_requires_access_to_the_conversation(): void {
		$this->actingAs( $this->create_user( User::ROLE_USER ) )
			->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )
			->assertStatus( 403 );
	}

	/**
	 * An unreachable API yields an error the panel can show, not an exception.
	 *
	 * @return void
	 */
	public function test_unreachable_api_is_a_bad_gateway(): void {
		$this->actingAs( $this->create_user() )
			->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )
			->assertStatus( 502 )
			->assertExactJson( array( 'blocks' => array() ) );
	}

	/**
	 * The account a bounce or Slack notification names is only asked for when the agent asks; each answer is reused briefly.
	 *
	 * @return void
	 */
	public function test_asks_for_the_related_account_on_request(): void {
		$payloads = array();
		$this->app->instance(
			Client::class,
			new Client(
				'https://api.wordpress.test/',
				'test-secret',
				5,
				static function ( RequestInterface $request ) use ( &$payloads ): PromiseInterface {
					$payloads[] = json_decode( (string) $request->getBody(), true );

					return ( new MockHandler( array( new Response( 200, array(), '{"blocks":[{"type":"meta","text":"Panel"}]}' ) ) ) )( $request, array() );
				}
			)
		);
		$user = $this->create_user();

		$this->actingAs( $user )
			->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )
			->assertExactJson(
				array(
					'blocks' => array(
						array(
							'type' => 'meta',
							'text' => 'Panel',
						),
					),
				)
			);
		$this->actingAs( $user )->get( '/wporgsidebar/' . $this->conversation->id . '/profile?related=1' )->assertStatus( 200 );

		$this->assertArrayNotHasKey( 'related', $payloads[0] );
		$this->assertTrue( $payloads[1]['related'] );

		// Loaded again, both come from the cache.
		$this->actingAs( $user )->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )->assertStatus( 200 );
		$this->actingAs( $user )->get( '/wporgsidebar/' . $this->conversation->id . '/profile?related=1' )->assertStatus( 200 );
		$this->assertCount( 2, $payloads );
	}

	/**
	 * Panels have a limit of their own, so loading many doesn't use up core's limit on uploads.
	 *
	 * @return void
	 */
	public function test_limits_panels_on_their_own_counter(): void {
		$user    = $this->create_user();
		$limiter = app( RateLimiter::class );
		for ( $i = 0; $i < PanelController::MAX_PER_MINUTE; $i++ ) {
			$limiter->hit( 'wporgsidebar.panels.' . $user->id );
		}

		$this->actingAs( $user )->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )->assertStatus( 429 );
		$this->assertSame( 0, $limiter->attempts( sha1( (string) $user->id ) ) );
	}
}

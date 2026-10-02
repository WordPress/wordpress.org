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
use Modules\WPOrgSidebar\Services\Panels;
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
		$this->forget_options();
	}

	/**
	 * The profile panel shows in every mailbox; the others only in the mailboxes chosen for them.
	 *
	 * @return void
	 */
	public function test_renders_panels_chosen_for_the_mailbox(): void {
		$this->assertSame( array( 'profile' ), $this->rendered_panels() );

		$this->choose_mailboxes( 'dpo', array( (string) $this->conversation->mailbox_id ) );
		$this->choose_mailboxes( 'forums', array( (string) ( $this->conversation->mailbox_id + 1 ) ) );

		$this->assertSame( array( 'profile', 'dpo' ), $this->rendered_panels() );
	}

	/**
	 * Chooses the mailboxes a panel shows in.
	 *
	 * @param string   $panel_id    Panel ID.
	 * @param string[] $mailbox_ids Mailbox IDs, as the settings form sends them.
	 * @return void
	 */
	private function choose_mailboxes( string $panel_id, array $mailbox_ids ): void {
		\Option::set( Panels::option( $panel_id ), $mailbox_ids );
		$this->forget_options();
	}

	/**
	 * Empties FreeScout's option cache, which Option::set() doesn't update; a real save redirects to a new request.
	 *
	 * @return void
	 */
	private function forget_options(): void {
		\Option::$cache = array();
	}

	/**
	 * Gets the panels the conversation's sidebar has placeholders for, in order.
	 *
	 * @return string[]
	 */
	private function rendered_panels(): array {
		ob_start();
		\Eventy::action( 'conversation.after_customer_sidebar', $this->conversation );
		preg_match_all( '#/wporgsidebar/' . $this->conversation->id . '/([a-z-]+)"#', (string) ob_get_clean(), $matches );

		return $matches[1];
	}

	/**
	 * A panel switched off for the mailbox isn't sent its conversations.
	 *
	 * @return void
	 */
	public function test_panel_off_for_the_mailbox_is_not_found(): void {
		$user = $this->create_user();

		$this->actingAs( $user )->get( '/wporgsidebar/' . $this->conversation->id . '/plugins-themes' )->assertStatus( 404 );

		$this->choose_mailboxes( 'plugins-themes', array( (string) $this->conversation->mailbox_id ) );

		// On, it's asked for, and the test API is unreachable.
		$this->actingAs( $user )->get( '/wporgsidebar/' . $this->conversation->id . '/plugins-themes' )->assertStatus( 502 );
	}

	/**
	 * Each panel chosen per mailbox has its own settings section, listing every mailbox with what's checked.
	 *
	 * @return void
	 */
	public function test_settings_section_lists_mailboxes(): void {
		$other = $this->create_mailbox();
		$this->choose_mailboxes( 'forums', array( (string) $other->id ) );

		$sections = \Eventy::filter( 'settings.sections', array() );
		$this->assertSame( array( 'wporgsidebar-forums', 'wporgsidebar-plugins-themes', 'wporgsidebar-dpo' ), array_keys( $sections ) );

		$html = (string) $this->actingAs( $this->create_user() )->get( '/app-settings/wporgsidebar-forums' )->assertStatus( 200 )->getContent();

		$name = 'settings[' . Panels::option( 'forums' ) . '][]';
		$this->assertStringContainsString( 'name="' . $name . '" value="' . $this->conversation->mailbox_id . '" >', $html );
		$this->assertStringContainsString( 'name="' . $name . '" value="' . $other->id . '"  checked >', $html );
	}

	/**
	 * Saving a section stores the mailboxes checked; none checked switches the panel off everywhere.
	 *
	 * @return void
	 */
	public function test_settings_section_saves_mailboxes(): void {
		$user       = $this->create_user();
		$mailbox_id = (int) $this->conversation->mailbox_id;
		$option     = Panels::option( 'dpo' );

		$this->actingAs( $user )
			->post( '/app-settings/wporgsidebar-dpo', array( 'settings' => array( $option => array( (string) $mailbox_id ) ) ) )
			->assertRedirect();
		$this->forget_options();
		$this->assertSame( array( $mailbox_id ), Panels::mailbox_ids( 'dpo' ) );
		$this->assertTrue( Panels::shows( 'dpo', $mailbox_id ) );

		$this->actingAs( $user )->post( '/app-settings/wporgsidebar-dpo', array() )->assertRedirect();
		$this->forget_options();
		$this->assertSame( array(), Panels::mailbox_ids( 'dpo' ) );
		$this->assertFalse( Panels::shows( 'dpo', $mailbox_id ) );
	}

	/**
	 * The profile panel has no settings section: it's always on.
	 *
	 * @return void
	 */
	public function test_profile_has_no_settings_section(): void {
		$this->actingAs( $this->create_user() )->get( '/app-settings/wporgsidebar-profile' )->assertStatus( 404 );
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

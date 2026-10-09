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
use Illuminate\Contracts\Bus\Dispatcher;
use Modules\WPOrgSidebar\Http\Controllers\PanelController;
use Modules\WPOrgSidebar\Jobs\SyncSenderAvatar;
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
	 * A sender without a photo gets the page an address to ask for it, once the queue has saved their avatar.
	 *
	 * @return void
	 */
	public function test_shows_a_new_sender_photo_once_saved(): void {
		\Queue::fake();
		$this->fake_profile_panel( 'https://secure.gravatar.com/avatar/abc?d=404' );
		$user   = $this->create_user();
		$sender = $this->conversation->customer;

		$panel = $this->actingAs( $user )->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )->decodeResponseJson();
		$url   = route( 'wporgsidebar.sender_photo', array( 'conversation_id' => $this->conversation->id ) );
		$this->assertSame( $url, $panel['sender_photo'] );
		\Queue::assertPushed( SyncSenderAvatar::class );

		$this->actingAs( $user )->get( $url )->assertExactJson(
			array(
				'url'     => null,
				'pending' => true,
				'sender'  => $sender->url(),
			)
		);

		// What the job does once the queue gets to it.
		$sender->photo_url = 'avatar.png';
		$sender->save();
		\Cache::forget( SyncSenderAvatar::pending_key( (int) $sender->id ) );
		$this->actingAs( $user )->get( $url )->assertExactJson(
			array(
				'url'     => $sender->getPhotoUrl(),
				'pending' => false,
				'sender'  => $sender->url(),
			)
		);
	}

	/**
	 * A page loaded again before the queue gets to the avatar waits for the same job, and stops once it's done.
	 *
	 * @return void
	 */
	public function test_waits_for_a_pending_sender_photo_on_reload(): void {
		\Queue::fake();
		$this->fake_profile_panel( 'https://secure.gravatar.com/avatar/abc?d=404' );
		$user = $this->create_user();
		$url  = '/wporgsidebar/' . $this->conversation->id . '/profile';

		$this->actingAs( $user )->get( $url );
		$panel = $this->actingAs( $user )->get( $url )->decodeResponseJson();

		$this->assertArrayHasKey( 'sender_photo', $panel );
		\Queue::assertPushed( SyncSenderAvatar::class, 1 );

		// The job is done, and the account had no avatar to save.
		\Cache::forget( SyncSenderAvatar::pending_key( (int) $this->conversation->customer_id ) );
		$this->assertArrayNotHasKey( 'sender_photo', $this->actingAs( $user )->get( $url )->decodeResponseJson() );
	}

	/**
	 * When the job can't be queued, no page waits for it, and the next panel load tries again.
	 *
	 * @return void
	 */
	public function test_tries_again_when_the_avatar_cannot_be_queued(): void {
		$this->fake_profile_panel( 'https://secure.gravatar.com/avatar/abc?d=404' );
		$user = $this->create_user();
		$url  = '/wporgsidebar/' . $this->conversation->id . '/profile';
		$bus  = app( Dispatcher::class );

		$this->app->instance(
			Dispatcher::class,
			new class() implements Dispatcher {
				/**
				 * Fails, like a queue that's down.
				 *
				 * @param mixed $command Job.
				 * @return void
				 * @throws \RuntimeException Always.
				 */
				public function dispatch( $command ) {
					throw new \RuntimeException( 'The queue is down.' );
				}

				/**
				 * Fails, like a queue that's down.
				 *
				 * @param mixed $command Job.
				 * @param mixed $handler Handler.
				 * @return void
				 * @throws \RuntimeException Always.
				 */
				public function dispatchNow( $command, $handler = null ) {
					throw new \RuntimeException( 'The queue is down.' );
				}

				/**
				 * Ignores the pipes.
				 *
				 * @param array $pipes Pipes.
				 * @return $this
				 */
				public function pipeThrough( array $pipes ) {
					return $this;
				}
			}
		);

		$this->assertArrayNotHasKey( 'sender_photo', $this->actingAs( $user )->get( $url )->decodeResponseJson() );
		$this->assertFalse( \Cache::has( SyncSenderAvatar::pending_key( (int) $this->conversation->customer_id ) ) );

		$this->app->instance( Dispatcher::class, $bus );
		\Queue::fake();

		$this->assertArrayHasKey( 'sender_photo', $this->actingAs( $user )->get( $url )->decodeResponseJson() );
		\Queue::assertPushed( SyncSenderAvatar::class, 1 );
	}

	/**
	 * A sender's photo is refreshed in the background, without the page asking for it.
	 *
	 * @return void
	 */
	public function test_refreshes_an_existing_sender_photo_quietly(): void {
		\Queue::fake();
		$this->fake_profile_panel( 'https://secure.gravatar.com/avatar/abc?d=404' );
		$sender            = $this->conversation->customer;
		$sender->photo_url = 'avatar.png';
		$sender->save();

		$panel = $this->actingAs( $this->create_user() )->get( '/wporgsidebar/' . $this->conversation->id . '/profile' )->decodeResponseJson();

		$this->assertArrayNotHasKey( 'sender_photo', $panel );
		\Queue::assertPushed( SyncSenderAvatar::class );
	}

	/**
	 * Agents can't see the photo of a sender in a mailbox they can't access.
	 *
	 * @return void
	 */
	public function test_sender_photo_requires_access_to_the_conversation(): void {
		$this->actingAs( $this->create_user( User::ROLE_USER ) )
			->get( '/wporgsidebar/sender-photo/' . $this->conversation->id )
			->assertStatus( 403 );
	}

	/**
	 * A conversation without a sender has no photo to wait for.
	 *
	 * @return void
	 */
	public function test_sender_photo_needs_a_sender(): void {
		$this->conversation->customer_id = null;
		$this->conversation->save();

		$this->actingAs( $this->create_user() )
			->get( '/wporgsidebar/sender-photo/' . $this->conversation->id )
			->assertStatus( 404 );
	}

	/**
	 * Checking for photos has a limit of its own, so it doesn't use up the limit on panels.
	 *
	 * @return void
	 */
	public function test_limits_photo_checks_on_their_own_counter(): void {
		$user    = $this->create_user();
		$limiter = app( RateLimiter::class );
		for ( $i = 0; $i < PanelController::MAX_PER_MINUTE; $i++ ) {
			$limiter->hit( 'wporgsidebar.photos.' . $user->id );
		}

		$this->actingAs( $user )->get( '/wporgsidebar/sender-photo/' . $this->conversation->id )->assertStatus( 429 );
		$this->assertSame( 0, $limiter->attempts( 'wporgsidebar.panels.' . $user->id ) );
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

	/**
	 * Answers panel requests like the profile panel, with the sender's avatar.
	 *
	 * @param string $avatar_url Avatar URL the panel sends.
	 * @return void
	 */
	private function fake_profile_panel( string $avatar_url ): void {
		$body = json_encode(
			array(
				'blocks'     => array(
					array(
						'type' => 'meta',
						'text' => 'Panel',
					),
				),
				'avatar_url' => $avatar_url,
			)
		);

		$this->app->instance(
			Client::class,
			new Client(
				'https://api.wordpress.test/',
				'test-secret',
				5,
				static function ( RequestInterface $request ) use ( $body ): PromiseInterface {
					return ( new MockHandler( array( new Response( 200, array(), $body ) ) ) )( $request, array() );
				}
			)
		);
	}
}

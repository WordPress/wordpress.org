<?php
/**
 * Tests for the Plugin Review panel and its ownership check.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\Conversation;
use App\Thread;
use App\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use Modules\WPOrgPluginReview\Services\Dns;
use Modules\WPOrgPluginReview\Services\FlagReplies;
use Modules\WPOrgPluginReview\Services\Reviewers;
use Modules\WPOrgSidebar\Services\Client;
use Psr\Http\Message\RequestInterface;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers what the sidebar shows, and what it asks WordPress.org and DNS.
 */
final class PanelTest extends TestCase {

	/**
	 * Conversation under test.
	 *
	 * @var Conversation
	 */
	private $conversation;

	/**
	 * Reviewer.
	 *
	 * @var User
	 */
	private $reviewer;

	/**
	 * Registers the module and creates a review conversation.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );

		// Routes registered after boot aren't in the name index yet.
		$this->app['router']->getRoutes()->refreshNameLookups();

		$this->reviewer     = $this->create_user();
		$this->conversation = $this->create_conversation( $this->create_mailbox( 'Plugins', 'plugins@wordpress.test' ), $this->create_sender() );
	}

	/**
	 * A conversation without a review email gets no panel.
	 *
	 * @return void
	 */
	public function test_no_panel_without_a_review(): void {
		$this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, '<p>Hello</p>', $this->reviewer, '2026-08-01 10:00:00' );

		$this->assertSame( '', $this->panel() );
	}

	/**
	 * The panel shows the review's details, flags, issues, and owner, with everything from the email escaped.
	 *
	 * @return void
	 */
	public function test_panel_shows_the_latest_review(): void {
		$this->conversation->update( array( 'subject' => '[WordPress Plugin Directory] Review in Progress: My <Plugin>' ) );
		$this->review( '<ul><li>Plugin URI: https://example.org/p</li></ul><h3>🔴 Use wp_enqueue &lt;script&gt; commands</h3><p>Review ID: R ❗OWN my-plugin/jane/1Aug26/T2 8Aug26/4.3 (P0TDX42HGN)</p>' );

		$html = $this->panel();

		$this->assertStringContainsString( 'post.php?post=42&amp;action=edit', $html );
		$this->assertStringNotContainsString( 'wporg-review-slug-name', $html );
		$this->assertStringContainsString( 'https://profiles.wordpress.org/jane/', $html );
		$this->assertStringContainsString( 'data-flags="UPD OWN"', $html );
		$this->assertStringContainsString( 'data-username="jane"', $html );
		$this->assertStringContainsString( 'data-short-subject="R: My &lt;Plugin&gt;"', $html );
		$this->assertStringContainsString( 'wporg-review-title-toggle', $html );
		$this->assertStringContainsString( 'data-title="Use wp_enqueue &lt;script&gt; commands"', $html );
		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '/wporgpluginreview/' . $this->conversation->id . '/plugin', $html );
		$this->assertStringContainsString( '>example.org<', $html );
	}

	/**
	 * Without a plugin ID, the slug waits for WordPress.org to link it; a subject that isn't the plugin directory's isn't
	 * shortened.
	 *
	 * @return void
	 */
	public function test_panel_links_the_slug_once_the_plugin_is_found(): void {
		$this->conversation->update( array( 'subject' => 'Question about my plugin' ) );
		$this->review( '<p>Review ID: R my-plugin/jane 8Aug26/4.3</p>' );

		$html = $this->panel();

		$this->assertStringContainsString( '<span class="wporg-review-slug-name">my-plugin</span>', $html );
		$this->assertStringContainsString( 'data-short-subject=""', $html );
		$this->assertStringNotContainsString( 'wporg-review-title-toggle', $html );
	}

	/**
	 * Flags with a reply get a button that copies it, escaped as an attribute.
	 *
	 * @return void
	 */
	public function test_panel_offers_the_replies_for_its_flags(): void {
		$this->review( '<p>Review ID: R ❗OWN my-plugin/jane 8Aug26/4.3</p>' );

		$html = $this->panel();

		$this->assertStringContainsString( 'data-reply="' . e( FlagReplies::REPLIES['OWN'] ) . '"', $html );
		$this->assertStringContainsString( 'data-reply="' . e( FlagReplies::REPLIES['UPD'] ) . '"', $html );
		$this->assertStringNotContainsString( e( FlagReplies::REPLIES['TRM'] ), $html );
	}

	/**
	 * The plugin comes from WordPress.org by the Review ID's plugin ID, with the ownership check; the account's username
	 * comes from the submitter when the Review ID doesn't say.
	 *
	 * @return void
	 */
	public function test_plugin_endpoint_returns_the_plugin_and_ownership(): void {
		$this->review( '<ul><li>Author URI: https://author.example</li><li>Plugin URI: <a href="https://plugin.example">p</a></li></ul><p>Review ID: R ❗OWN my-plugin 8Aug26/4.3 (P0TDX42HGN)</p>' );
		$this->answer_dns();
		$plugin   = array(
			'id'        => 42,
			'name'      => 'Example Kit',
			'slug'      => 'example-kit',
			'status'    => 'pending',
			'submitter' => array(
				'username' => 'Jane',
				'email'    => 'jane@plugin.example',
			),
		);
		$payloads = $this->answer_api( new Response( 200, array(), (string) json_encode( array( 'plugin' => $plugin ) ) ) );

		$this->actingAs( $this->reviewer )
			->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )
			->assertStatus( 200 )
			->assertExactJson(
				array(
					'plugin' => $plugin,
					'owner'  => array(
						'author' => false,
						'plugin' => true,
						'email'  => true,
					),
				)
			);

		$this->assertCount( 1, $payloads );
		$this->assertSame( 'plugin-review.php', $payloads[0]['endpoint'] );
		$this->assertSame( 42, $payloads[0]['plugin_id'] );
		$this->assertSame( '', $payloads[0]['slug'] );
		$this->assertSame( $this->conversation->mailbox->email, $payloads[0]['mailbox']['email'] );

		$this->actingAs( $this->create_user( User::ROLE_USER ) )
			->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )
			->assertStatus( 403 );
	}

	/**
	 * Without an answer from WordPress.org, the ownership check still runs, with the Review ID's username.
	 *
	 * @return void
	 */
	public function test_plugin_endpoint_works_without_wordpress_org(): void {
		$this->review( '<ul><li>Plugin URI: <a href="https://plugin.example">p</a></li></ul><p>Review ID: R ❗OWN my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		$this->answer_dns();
		$this->answer_api( new Response( 500 ) );

		$this->actingAs( $this->reviewer )
			->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )
			->assertStatus( 200 )
			->assertExactJson(
				array(
					'plugin' => null,
					'owner'  => array(
						'author' => false,
						'plugin' => true,
						'email'  => null,
					),
				)
			);
	}

	/**
	 * The submitter's email address counts as the plugin's at one of its domains, but not at a subdomain of them.
	 *
	 * @dataProvider data_email_domains
	 *
	 * @param string $email   The submitter's email address.
	 * @param bool   $matches Whether it's at one of the plugin's domains.
	 * @return void
	 */
	public function test_plugin_endpoint_matches_the_submitters_email( string $email, bool $matches ): void {
		$this->review( '<ul><li>Author URI: https://shop.author.example/about</li></ul><p>Review ID: R ❗OWN my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		$this->answer_dns();
		$this->answer_api(
			new Response(
				200,
				array(),
				(string) json_encode(
					array(
						'plugin' => array(
							'id'        => 42,
							'submitter' => array(
								'username' => 'jane',
								'email'    => $email,
							),
						),
					)
				)
			)
		);

		$this->actingAs( $this->reviewer )
			->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )
			->assertStatus( 200 )
			->assertJsonFragment( array( 'email' => $matches ) );
	}

	/**
	 * Submitters' email addresses, and whether they're at the plugin's domains, whose Author URI is at author.example.
	 *
	 * @return array[]
	 */
	public static function data_email_domains(): array {
		return array(
			'at the domain'     => array( 'jane@Author.example', true ),
			'at a subdomain'    => array( 'jane@mail.author.example', false ),
			'at another domain' => array( 'jane@example.org', false ),
		);
	}

	/**
	 * A review without a plugin ID is asked about by its slug; one without either isn't asked about, and one without the
	 * OWN flag has no ownership check.
	 *
	 * @return void
	 */
	public function test_plugin_endpoint_asks_only_what_the_review_needs(): void {
		$this->review( '<p>Review ID: R 8Aug26/4.3</p>', '2026-08-08 10:00:00' );
		$payloads = $this->answer_api( new Response( 200, array(), '{"plugin":null}' ) );

		$this->actingAs( $this->reviewer )
			->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )
			->assertStatus( 200 )
			->assertExactJson(
				array(
					'plugin' => null,
					'owner'  => null,
				)
			);
		$this->assertCount( 0, $payloads );

		$this->review( '<p>Review ID: R my-plugin/jane 9Aug26/4.3</p>', '2026-08-09 10:00:00' );

		$this->actingAs( $this->reviewer )->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )->assertStatus( 200 );
		$this->assertCount( 1, $payloads );
		$this->assertSame( array( 0, 'my-plugin' ), array( $payloads[0]['plugin_id'], $payloads[0]['slug'] ) );
	}

	/**
	 * A conversation without a review has nothing to ask about.
	 *
	 * @return void
	 */
	public function test_plugin_endpoint_needs_a_review(): void {
		$this->actingAs( $this->reviewer )->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )->assertStatus( 404 );
	}

	/**
	 * With the Teams module, only members of the plugins team's teams get the panel, and what it loads.
	 *
	 * @return void
	 */
	public function test_panel_is_only_for_the_plugin_teams(): void {
		$this->review( '<p>Review ID: R my-plugin/jane 8Aug26/4.3 (P0TDX42HGN)</p>' );
		$member = $this->reviewer;
		$this->answer_teams( array( (int) $member->id => array( 'Themes', 'Plugin Security' ) ) );

		\App\Module::clearModulesCache();
		\App\Module::setActive( Reviewers::TEAMS_MODULE, true );
		\App\Module::clearModulesCache();

		try {
			$this->assertNotSame( '', $this->panel() );

			$this->reviewer = $this->create_user();
			$this->assertSame( '', $this->panel() );
			$this->actingAs( $this->reviewer )->get( '/wporgpluginreview/' . $this->conversation->id . '/plugin' )->assertStatus( 403 );
		} finally {
			// The modules' cache outlives the test's database.
			\App\Module::clearModulesCache();
		}
	}

	/**
	 * Without the Teams module, everyone who can see the conversation is a reviewer.
	 *
	 * @return void
	 */
	public function test_without_teams_everyone_is_a_reviewer(): void {
		$this->answer_teams( array() );

		$this->assertTrue( app( Reviewers::class )->includes( $this->create_user() ) );
		$this->assertFalse( app( Reviewers::class )->includes( null ) );
	}

	/**
	 * Answers for the Teams module.
	 *
	 * @param array $teams Names of each user's teams, by user ID.
	 * @return void
	 */
	private function answer_teams( array $teams ): void {
		$this->app->instance(
			Reviewers::class,
			new class( $teams ) extends Reviewers {
				/**
				 * Names of each user's teams, by user ID.
				 *
				 * @var array
				 */
				private $teams;

				/**
				 * Constructor.
				 *
				 * @param array $teams Names of each user's teams, by user ID.
				 */
				public function __construct( array $teams ) {
					$this->teams = $teams;
				}

				/**
				 * The names of the teams a user is a member of.
				 *
				 * @param User $user User.
				 * @return string[]
				 */
				protected function team_names( User $user ): array {
					return $this->teams[ (int) $user->id ] ?? array();
				}
			}
		);
	}

	/**
	 * Answers for DNS: only plugin.example has the record, for jane.
	 *
	 * @return void
	 */
	private function answer_dns(): void {
		$this->app->instance(
			Dns::class,
			new class() extends Dns {
				/**
				 * A domain's TXT records.
				 *
				 * @param string $domain Domain.
				 * @return string[]
				 */
				protected function txt_records( string $domain ): array {
					return 'plugin.example' === $domain ? array( 'v=spf1 -all', 'wordpressorg-jane-verification' ) : array();
				}
			}
		);
	}

	/**
	 * Answers requests to api.wordpress.org.
	 *
	 * @param Response $response What it answers.
	 * @return \ArrayObject The payloads it was sent, as they arrive.
	 */
	private function answer_api( Response $response ): \ArrayObject {
		$payloads = new \ArrayObject();
		$this->app->instance(
			Client::class,
			new Client(
				'https://api.wordpress.test/',
				'test-secret',
				5,
				static function ( RequestInterface $request ) use ( $payloads, $response ): PromiseInterface {
					$payloads[] = json_decode( (string) $request->getBody(), true );

					return ( new MockHandler( array( $response ) ) )( $request, array() );
				}
			)
		);

		return $payloads;
	}

	/**
	 * Adds a review email from the reviewer.
	 *
	 * @param string $body       Body.
	 * @param string $created_at Creation time.
	 * @return void
	 */
	private function review( string $body, string $created_at = '2026-08-08 10:00:00' ): void {
		$this->create_thread( $this->conversation, Thread::TYPE_MESSAGE, $body, $this->reviewer, $created_at );
	}

	/**
	 * Renders the conversation's sidebar, as far as this module adds to it.
	 *
	 * @return string
	 */
	private function panel(): string {
		$this->actingAs( $this->reviewer );
		ob_start();
		\Eventy::action( 'conversation.after_customer_sidebar', $this->conversation );

		return trim( (string) ob_get_clean() );
	}
}

<?php
/**
 * Tests for guideline labels.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Guideline_Labels as Guideline_Labels_Metabox;
use WordPressdotorg\Plugin_Directory\Guideline_Labels;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;

/**
 * Covers storing guideline labels and exposing them.
 *
 * Extends the plain PHPUnit TestCase: WP_UnitTestCase is not compatible with
 * the PHPUnit 11 runner used by this suite.
 *
 * @group guideline-labels
 */
#[Group( 'guideline-labels' )]
class Guideline_Labels_Test extends TestCase {

	/**
	 * An internal note that must never leave the admin.
	 *
	 * @var string
	 */
	const NOTE = 'Internal: reported by a reviewer.';

	/**
	 * Counter to give every test plugin and user a unique slug.
	 *
	 * @var int
	 */
	private static int $count = 0;

	/**
	 * The plugin post under test.
	 *
	 * @var WP_Post
	 */
	private WP_Post $plugin;

	/**
	 * The author of the plugin under test.
	 *
	 * @var WP_User
	 */
	private WP_User $author;

	/**
	 * User IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	private array $user_ids = array();

	/**
	 * The REMOTE_ADDR the test overwrote, restored on tear down.
	 *
	 * @var string|null
	 */
	private ?string $remote_addr = null;

	/**
	 * Creates a published plugin.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Tools::audit_log() reads it unguarded.
		$this->remote_addr      = isset( $_SERVER['REMOTE_ADDR'] ) ? (string) $_SERVER['REMOTE_ADDR'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Captured verbatim to restore in tearDown, not used as input.
		$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

		++self::$count;
		$this->author = $this->create_user( 'label-author-' . self::$count );

		$plugin = Plugin_Directory::create_plugin_post(
			array(
				'post_name'   => 'label-test-' . self::$count,
				'post_title'  => 'Guideline Label Test Plugin',
				'post_status' => 'publish',
				'post_author' => $this->author->ID,
			)
		);
		$this->assertInstanceOf( WP_Post::class, $plugin );
		$this->plugin = $plugin;

		update_post_meta( $this->plugin->ID, 'version', '2.0.0' );
	}

	/**
	 * Removes every fixture the test created.
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		unset( $_POST['guideline_labels_nonce'], $_POST['guideline_labels'] );

		wp_delete_post( $this->plugin->ID, true );

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();

		if ( null === $this->remote_addr ) {
			unset( $_SERVER['REMOTE_ADDR'] );
		} else {
			$_SERVER['REMOTE_ADDR'] = $this->remote_addr;
		}

		parent::tearDown();
	}

	/**
	 * Adding and removing a label writes the term and the meta entry together.
	 */
	public function test_set_and_remove_keep_term_and_meta_in_sync(): void {
		$this->assertTrue( Guideline_Labels::set( $this->plugin, 'external-installs', array( 'note' => self::NOTE ) ) );

		$this->assertTrue( has_term( 'external-installs', Guideline_Labels::TAXONOMY, $this->plugin->ID ) );
		$this->assertSame( array( 'external-installs' ), array_keys( get_post_meta( $this->plugin->ID, Guideline_Labels::META_KEY, true ) ) );

		$this->assertTrue( Guideline_Labels::remove( $this->plugin, 'external-installs' ) );

		$this->assertFalse( has_term( 'external-installs', Guideline_Labels::TAXONOMY, $this->plugin->ID ) );
		$this->assertSame( '', get_post_meta( $this->plugin->ID, Guideline_Labels::META_KEY, true ) );
	}

	/**
	 * A reason outside the registry is rejected without creating a term or meta entry.
	 */
	public function test_set_rejects_unknown_reason(): void {
		$this->assertFalse( Guideline_Labels::set( $this->plugin, 'not-a-reason' ) );

		$this->assertSame( array(), wp_get_object_terms( $this->plugin->ID, Guideline_Labels::TAXONOMY ) );
		$this->assertSame( '', get_post_meta( $this->plugin->ID, Guideline_Labels::META_KEY, true ) );
	}

	/**
	 * The plugin info route lists the labels in their documented shape, without the internal note.
	 */
	public function test_api_lists_labels_without_internal_note(): void {
		Guideline_Labels::set(
			$this->plugin,
			'external-installs',
			array(
				'since_version' => '1.9.0',
				'note'          => self::NOTE,
			)
		);
		$labeled_at = get_post_meta( $this->plugin->ID, Guideline_Labels::META_KEY, true )['external-installs']['labeled_at'];

		$response = rest_do_request( new WP_REST_Request( 'GET', '/plugins/v1/plugin/' . $this->plugin->post_name ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				array(
					'guideline' => 8,
					'reason'    => 'external-installs',
					'message'   => 'Installs or updates software from servers other than WordPress.org.',
					'url'       => 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/#8-plugins-may-not-send-executable-code-via-third-party-systems',
					'since'     => '1.9.0',
					'date'      => gmdate( 'Y-m-d', $labeled_at ),
				),
			),
			$data['guideline_labels']
		);
		$this->assertStringNotContainsString( self::NOTE, (string) wp_json_encode( $data ) );
		$this->assertStringNotContainsString( self::NOTE, (string) wp_json_encode( Guideline_Labels::get( $this->plugin ) ) );
	}

	/**
	 * Users with and without the labeling capability.
	 *
	 * @return array<string, array{0: bool}>
	 */
	public static function data_label_capability(): array {
		return array(
			'reviewer without plugin_label' => array( false ),
			'admin with plugin_label'       => array( true ),
		);
	}

	/**
	 * The metabox only saves labels for users who can label plugins.
	 *
	 * @dataProvider data_label_capability
	 *
	 * @param bool $can_label Whether the saving user has the plugin_label capability.
	 */
	#[DataProvider( 'data_label_capability' )]
	public function test_metabox_save_requires_label_capability( bool $can_label ): void {
		$user = $this->create_user( 'label-reviewer-' . self::$count );
		$user->add_cap( 'plugin_review' );
		if ( $can_label ) {
			$user->add_cap( 'plugin_label' );
		}
		wp_set_current_user( $user->ID );

		$_POST['guideline_labels_nonce'] = wp_create_nonce( 'guideline-labels-' . $this->plugin->ID );
		$_POST['guideline_labels']       = array(
			'external-installs' => array(
				'enabled'       => '1',
				'since_version' => '2.0.0',
				'note'          => '',
			),
		);

		Guideline_Labels_Metabox::save_post( $this->plugin );

		$this->assertSame( $can_label, has_term( 'external-installs', Guideline_Labels::TAXONOMY, $this->plugin->ID ) );
	}

	/**
	 * Creates a user and registers it for removal on tear down.
	 *
	 * @param string $user_login Login for the new account.
	 * @return WP_User The user.
	 */
	private function create_user( string $user_login ): WP_User {
		$user_id = wp_create_user( $user_login, wp_generate_password(), $user_login . '@example.com' );
		$this->assertIsInt( $user_id );

		$this->user_ids[] = $user_id;

		return new WP_User( $user_id );
	}
}

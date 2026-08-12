<?php
/**
 * Tests that Capabilities::map_meta_cap() matches committer logins exactly.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * @group capabilities
 */
class Capabilities_Committer_Identity_Test extends TestCase {

	/**
	 * Slug of the plugin under test.
	 *
	 * @var string
	 */
	const PLUGIN_SLUG = 'committer-identity-fixture';

	/**
	 * Login recorded as the plugin's committer.
	 *
	 * @var string
	 */
	const COMMITTER_LOGIN = '42';

	/**
	 * Capabilities that committer status alone is expected to grant.
	 *
	 * @var string[]
	 */
	const COMMITTER_CAPS = array(
		'plugin_admin_view',
		'plugin_admin_edit',
		'plugin_add_committer',
		'plugin_remove_committer',
		'plugin_manage_releases',
	);

	/**
	 * The plugin post that capabilities are checked against.
	 *
	 * @var int
	 */
	protected int $plugin_id = 0;

	/**
	 * User IDs created by the running test, removed on tear down.
	 *
	 * @var int[]
	 */
	protected array $user_ids = array();

	/**
	 * Creates the plugin post and committer row shared by each test.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->plugin_id = $this->create_plugin_post();
	}

	/**
	 * Removes every fixture the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		global $wpdb;

		wp_set_current_user( 0 );

		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();

		if ( $this->plugin_id ) {
			wp_delete_post( $this->plugin_id, true );
			$this->plugin_id = 0;
		}

		$wpdb->delete(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array( 'path' => '/' . self::PLUGIN_SLUG )
		);
		wp_cache_delete( self::PLUGIN_SLUG, 'plugin-committers' );

		parent::tearDown();
	}

	/**
	 * Inserts a published plugin post.
	 *
	 * `Plugin_Directory::filter_wp_insert_post_data()` dereferences
	 * `post_modified` unconditionally, so it must be supplied explicitly or the
	 * insert is silently rejected and every assertion passes vacuously.
	 *
	 * @param int $author_id Optional. Post author. Default 0.
	 * @return int Plugin post ID.
	 */
	protected function create_plugin_post( int $author_id = 0 ): int {
		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_title'        => 'Committer Identity Fixture',
				'post_name'         => self::PLUGIN_SLUG,
				'post_status'       => 'publish',
				'post_author'       => $author_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertNotInstanceOf( WP_Error::class, $plugin_id, 'The plugin fixture could not be created.' );

		return (int) $plugin_id;
	}

	/**
	 * Creates a subscriber and registers it for removal on tear down.
	 *
	 * @param string $user_login Login for the new account.
	 * @return int User ID.
	 */
	protected function create_user( string $user_login ): int {
		$user_id = wp_insert_user(
			array(
				'user_login' => $user_login,
				'user_pass'  => wp_generate_password( 24 ),
				'user_email' => $user_login . '@example.invalid',
				'role'       => 'subscriber',
			)
		);

		$this->assertNotInstanceOf( WP_Error::class, $user_id, "Could not create the user '{$user_login}'." );

		$this->user_ids[] = (int) $user_id;

		return (int) $user_id;
	}

	/**
	 * Grants a login commit access to the plugin under test.
	 *
	 * @param string $user_login Login to record as a committer.
	 * @return void
	 */
	protected function add_committer( string $user_login ): void {
		global $wpdb;

		$wpdb->insert(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array(
				'path'   => '/' . self::PLUGIN_SLUG,
				'user'   => $user_login,
				'access' => 'rw',
			)
		);
		wp_cache_delete( self::PLUGIN_SLUG, 'plugin-committers' );
	}

	/**
	 * Asserts the committer capabilities all resolve to an expected verdict.
	 *
	 * @param bool   $expected Whether the capabilities should be granted.
	 * @param int    $user_id  User to test.
	 * @param string $message  Assertion message.
	 * @return void
	 */
	protected function assert_caps_for_user( bool $expected, int $user_id, string $message ): void {
		wp_set_current_user( $user_id );

		foreach ( self::COMMITTER_CAPS as $cap ) {
			$this->assertSame(
				$expected,
				current_user_can( $cap, $this->plugin_id ),
				sprintf( '%s (%s)', $message, $cap )
			);
		}
	}

	/**
	 * The committer list is read back as the strings it was stored as.
	 *
	 * Strict comparison is only correct while both operands are strings; if this
	 * fails, the strict checks would lock out legitimate committers.
	 *
	 * @return void
	 */
	public function test_committer_list_contains_only_strings(): void {
		$this->add_committer( self::COMMITTER_LOGIN );

		$this->assertSame( array( self::COMMITTER_LOGIN ), Tools::get_plugin_committers( self::PLUGIN_SLUG ) );
	}

	/**
	 * A committer whose login is a numeric string keeps their capabilities.
	 *
	 * @return void
	 */
	public function test_numeric_committer_is_granted(): void {
		$committer = $this->create_user( self::COMMITTER_LOGIN );
		$this->add_committer( self::COMMITTER_LOGIN );

		$this->assert_caps_for_user( true, $committer, 'The genuine committer lost access.' );
	}

	/**
	 * An ordinary committer keeps their capabilities.
	 *
	 * @return void
	 */
	public function test_ordinary_committer_is_granted(): void {
		$committer = $this->create_user( 'realcommitter' );
		$this->add_committer( 'realcommitter' );

		$this->assert_caps_for_user( true, $committer, 'An ordinary committer lost access.' );
	}

	/**
	 * A login that PHP considers numerically equal to a committer's is refused.
	 *
	 * Each of these passes `wpmu_validate_user_signup()` — four or more
	 * characters, lowercase alphanumerics, and not all digits — so each is
	 * registrable while still comparing loosely equal to the committer login.
	 *
	 * @dataProvider data_colliding_logins
	 *
	 * @param string $attacker_login A login that loosely equals the committer's.
	 * @return void
	 */
	public function test_numeric_string_collision_is_denied( string $attacker_login ): void {
		$this->create_user( self::COMMITTER_LOGIN );
		$this->add_committer( self::COMMITTER_LOGIN );

		$attacker = $this->create_user( $attacker_login );

		$this->assert_caps_for_user( false, $attacker, 'A colliding login was granted committer access.' );
	}

	/**
	 * Logins that compare loosely equal to the committer login.
	 *
	 * @return array<string, string[]>
	 */
	public static function data_colliding_logins(): array {
		return array(
			'scientific notation'  => array( '42e0' ),
			'zero padded exponent' => array( '042e0' ),
			'decimal exponent'     => array( '4.2e1' ),
			'negative exponent'    => array( '420e-1' ),
		);
	}

	/**
	 * An unrelated account is refused, confirming the fixture denies by default.
	 *
	 * @return void
	 */
	public function test_unrelated_user_is_denied(): void {
		$this->create_user( self::COMMITTER_LOGIN );
		$this->add_committer( self::COMMITTER_LOGIN );

		$stranger = $this->create_user( 'stranger' );

		$this->assert_caps_for_user( false, $stranger, 'An unrelated user was granted committer access.' );
	}

	/**
	 * The author fallback for a plugin with no committers also matches exactly.
	 *
	 * When a published plugin has no committer rows the post author's login is
	 * used as the committer list, so that path needs the same guarantee.
	 *
	 * @return void
	 */
	public function test_author_fallback_rejects_colliding_login(): void {
		$author = $this->create_user( self::COMMITTER_LOGIN );

		wp_update_post(
			array(
				'ID'                => $this->plugin_id,
				'post_author'       => $author,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			)
		);

		$attacker = $this->create_user( '42e0' );

		$this->assert_caps_for_user( true, $author, 'The plugin author lost access through the fallback.' );
		$this->assert_caps_for_user( false, $attacker, 'A colliding login was granted through the author fallback.' );
	}
}

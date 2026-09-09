<?php
/**
 * Tests the format check of the slug change route, `POST plugins/v1/upload/<ID>/slug`.
 *
 * The route stores the requested slug as `post_name` through `wp_update_post()`, which
 * sanitizes it once more on the way in. The format check has to accept only values that
 * pass through that unchanged, which is the character set its own message names.
 *
 * @package plugin-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\API\Routes\Plugin_Upload;

/**
 * Tests for `Plugin_Upload::perform_slug_change()`.
 *
 * @group api
 */
class Plugin_Slug_Change_Format_Test extends TestCase {

	/**
	 * The slug the fixture plugin starts with.
	 *
	 * @var string
	 */
	const ORIGINAL_SLUG = 'fixture-sample';

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected $post_ids = array();

	/**
	 * IDs of users created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected $user_ids = array();

	/**
	 * Sets up the request context the route's audit log reads.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		if ( empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
		}
	}

	/**
	 * Removes the posts and users the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		require_once ABSPATH . 'wp-admin/includes/user.php';
		foreach ( $this->user_ids as $user_id ) {
			wp_delete_user( $user_id );
		}
		$this->user_ids = array();

		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * Creates a newly submitted plugin owned by a fresh user, and acts as that user.
	 *
	 * @return WP_Post
	 */
	protected function create_new_plugin(): WP_Post {
		$name    = 'slug_format_' . wp_generate_password( 8, false );
		$user_id = wp_create_user( $name, wp_generate_password(), "{$name}@example.org" );

		$this->assertIsInt( $user_id );
		$this->user_ids[] = $user_id;

		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_title'        => 'Fixture Sample',
				'post_name'         => self::ORIGINAL_SLUG,
				'post_status'       => 'new',
				'post_author'       => $user_id,
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertIsInt( $plugin_id );
		$this->post_ids[] = $plugin_id;

		wp_set_current_user( $user_id );

		return get_post( $plugin_id );
	}

	/**
	 * Asks the route to change a plugin's slug.
	 *
	 * The constructor registers REST routes, which is not wanted here.
	 *
	 * @param WP_Post $plugin The plugin post.
	 * @param string  $slug   The requested slug.
	 * @return true|WP_Error
	 */
	protected function change_slug( WP_Post $plugin, string $slug ) {
		$route  = ( new ReflectionClass( Plugin_Upload::class ) )->newInstanceWithoutConstructor();
		$method = new ReflectionMethod( Plugin_Upload::class, 'perform_slug_change' );

		return $method->invoke( $route, $plugin, $slug );
	}

	/**
	 * Requested slugs outside the character set the route accepts.
	 *
	 * @return array
	 */
	public static function data_refused_slugs(): array {
		return array(
			'underscore'            => array( 'fixture_sample' ),
			'upper case'            => array( 'Fixture-Sample' ),
			'space'                 => array( 'fixture sample' ),
			'percent-encoded octet' => array( 'fixture%20sample' ),
			'leading hyphen'        => array( '-fixture-sample' ),
			'trailing hyphen'       => array( 'fixture-sample-' ),
			'double hyphen'         => array( 'fixture--sample' ),
		);
	}

	/**
	 * A slug outside the character set is refused, and the stored slug is left alone.
	 *
	 * @dataProvider data_refused_slugs
	 *
	 * @param string $slug The requested slug.
	 * @return void
	 */
	public function test_route_refuses_a_slug_outside_its_character_set( string $slug ): void {
		$plugin = $this->create_new_plugin();
		$result = $this->change_slug( $plugin, $slug );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_slug', $result->get_error_code() );
		$this->assertSame( self::ORIGINAL_SLUG, get_post( $plugin->ID )->post_name );
	}

	/**
	 * The audit log entries on a plugin, oldest first.
	 *
	 * @param int $post_id The plugin post ID.
	 * @return string[]
	 */
	protected function audit_log( int $post_id ): array {
		$notes = get_comments(
			array(
				'post_id' => $post_id,
				'type'    => 'internal-note',
				'status'  => 'all',
				'order'   => 'ASC',
			)
		);

		return wp_list_pluck( $notes, 'comment_content' );
	}

	/**
	 * A slug longer than `post_name` can hold is refused rather than stored truncated.
	 *
	 * @return void
	 */
	public function test_route_refuses_a_slug_longer_than_post_name_can_hold(): void {
		$plugin = $this->create_new_plugin();
		$result = $this->change_slug( $plugin, str_repeat( 'a', 201 ) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'too_long', $result->get_error_code() );
		$this->assertSame( self::ORIGINAL_SLUG, get_post( $plugin->ID )->post_name );
	}

	/**
	 * A slug within the character set is stored exactly as requested, and logged as stored.
	 *
	 * @return void
	 */
	public function test_route_stores_a_slug_within_its_character_set_unchanged(): void {
		$plugin = $this->create_new_plugin();
		$result = $this->change_slug( $plugin, 'fixture-renamed' );

		$this->assertTrue( $result );
		$this->assertSame( 'fixture-renamed', get_post( $plugin->ID )->post_name );
		$this->assertSame( array( 'Changed slug from fixture-sample to fixture-renamed.' ), $this->audit_log( $plugin->ID ) );
	}

	/**
	 * A slug that core suffixes on the way in is logged as the slug the plugin got.
	 *
	 * The suffix comes from a slug taken between the availability check and the update,
	 * which cannot be staged in a single-threaded test, so the slug is marked as taken on
	 * the filter core offers for it.
	 *
	 * @return void
	 */
	public function test_route_logs_the_slug_the_plugin_got(): void {
		$plugin = $this->create_new_plugin();

		$slug_is_taken = static function ( bool $is_bad, string $slug ): bool {
			return 'fixture-contested' === $slug ? true : $is_bad;
		};
		add_filter( 'wp_unique_post_slug_is_bad_flat_slug', $slug_is_taken, 10, 2 );

		try {
			$result = $this->change_slug( $plugin, 'fixture-contested' );
		} finally {
			remove_filter( 'wp_unique_post_slug_is_bad_flat_slug', $slug_is_taken, 10 );
		}

		$this->assertTrue( $result );
		$this->assertSame( 'fixture-contested-2', get_post( $plugin->ID )->post_name );
		$this->assertSame(
			array( "Changed slug from fixture-sample to fixture-contested-2. The requested slug, 'fixture-contested', was not available." ),
			$this->audit_log( $plugin->ID )
		);
	}
}

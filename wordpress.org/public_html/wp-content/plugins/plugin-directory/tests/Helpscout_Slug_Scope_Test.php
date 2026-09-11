<?php
/**
 * Tests that the rejected-slug namespace stays tied to the plugin that owns it.
 *
 * A rejected plugin's slug is wrapped as `rejected-{slug}-rejected` to free up the
 * original slug for another submission. `Helpscout` unwraps it again to find the
 * emails recorded before the rename, which is only the right slug for as long as the
 * original is still unclaimed.
 *
 * @package plugin-directory
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Shortcodes\Upload_Handler;
use WordPressdotorg\Plugin_Directory\Tools\Helpscout;

/**
 * Tests for `Helpscout::get_unwrapped_slug()` and `Upload_Handler::has_reserved_slug()`.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Helpscout_Slug_Scope_Test extends TestCase {

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected $post_ids = array();

	/**
	 * Removes the posts the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_cache_delete( get_post_field( 'post_name', $post_id ), 'plugin-slugs' );
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		parent::tearDown();
	}

	/**
	 * Creates a plugin holding a given slug.
	 *
	 * @param string $slug The slug to give it.
	 * @return WP_Post
	 */
	protected function create_plugin( string $slug ): WP_Post {
		$plugin_id = wp_insert_post(
			array(
				'post_type'   => 'plugin',
				'post_title'  => $slug,
				'post_name'   => $slug,
				'post_status' => 'new',
			),
			true
		);

		$this->assertIsInt( $plugin_id );
		$this->post_ids[] = $plugin_id;

		return get_post( $plugin_id );
	}

	/**
	 * Asks `Helpscout` which slug a post's emails may also be recorded against.
	 *
	 * @param string $post_name The post slug.
	 * @param int    $post_id   The post ID.
	 * @return string
	 */
	protected function unwrap( string $post_name, int $post_id ): string {
		$method = new ReflectionMethod( Helpscout::class, 'get_unwrapped_slug' );

		return $method->invoke( null, $post_name, $post_id );
	}

	/**
	 * A unique slug, so one test's `plugin-slugs` cache entries can't reach another.
	 *
	 * @return string
	 */
	protected function unique_slug(): string {
		return 'fixture-' . strtolower( wp_generate_password( 8, false ) );
	}

	/**
	 * A slug outside the namespace is left alone.
	 *
	 * @return void
	 */
	public function test_a_slug_outside_the_namespace_is_unchanged(): void {
		$plugin = $this->create_plugin( $this->unique_slug() );

		$this->assertSame( $plugin->post_name, $this->unwrap( $plugin->post_name, $plugin->ID ) );
	}

	/**
	 * A wrapped slug unwraps while nothing holds the slug inside it.
	 *
	 * @return void
	 */
	public function test_a_wrapped_slug_unwraps_when_the_slug_is_unclaimed(): void {
		$slug   = $this->unique_slug();
		$plugin = $this->create_plugin( "rejected-{$slug}-rejected" );

		$this->assertSame( $slug, $this->unwrap( $plugin->post_name, $plugin->ID ) );
	}

	/**
	 * A wrapped slug unwraps when the post asking is the one holding the slug inside it,
	 * which is the state a restored plugin is in by the time the rename is recorded.
	 *
	 * @return void
	 */
	public function test_a_wrapped_slug_unwraps_for_the_post_that_holds_the_slug(): void {
		$slug   = $this->unique_slug();
		$plugin = $this->create_plugin( $slug );

		$this->assertSame( $slug, $this->unwrap( "rejected-{$slug}-rejected", $plugin->ID ) );
	}

	/**
	 * A wrapped slug stops unwrapping once a different plugin holds the slug inside it.
	 *
	 * @return void
	 */
	public function test_a_wrapped_slug_does_not_unwrap_onto_another_plugin(): void {
		$slug  = $this->unique_slug();
		$owner = $this->create_plugin( $slug );

		$wrapped = $this->create_plugin( "rejected-{$slug}-rejected" );

		$this->assertNotSame( $owner->ID, $wrapped->ID );
		$this->assertSame( $wrapped->post_name, $this->unwrap( $wrapped->post_name, $wrapped->ID ) );
	}

	/**
	 * Slugs the unwrapping would rewrite, which are therefore not free to be given out.
	 *
	 * @return array
	 */
	public static function data_namespaced_slugs(): array {
		return array(
			'wrapped'          => array( 'rejected-fixture-sample-rejected' ),
			'prefix only'      => array( 'rejected-fixture-sample' ),
			'suffix only'      => array( 'fixture-sample-rejected' ),
			'suffixed with -1' => array( 'fixture-sample-rejected-1' ),
			'mixed case'       => array( 'Rejected-Fixture-Sample' ),
		);
	}

	/**
	 * A slug in the namespace is reserved, so no upload or rename can take one.
	 *
	 * @dataProvider data_namespaced_slugs
	 *
	 * @param string $slug The slug to check.
	 * @return void
	 */
	#[DataProvider( 'data_namespaced_slugs' )]
	public function test_a_namespaced_slug_is_reserved( string $slug ): void {
		$upload_handler              = new Upload_Handler();
		$upload_handler->plugin_slug = $slug;

		$this->assertTrue( $upload_handler->has_reserved_slug() );
	}

	/**
	 * An ordinary slug is still allowed.
	 *
	 * @return void
	 */
	public function test_an_ordinary_slug_is_not_reserved(): void {
		$upload_handler              = new Upload_Handler();
		$upload_handler->plugin_slug = 'fixture-sample';

		$this->assertFalse( $upload_handler->has_reserved_slug() );
	}
}

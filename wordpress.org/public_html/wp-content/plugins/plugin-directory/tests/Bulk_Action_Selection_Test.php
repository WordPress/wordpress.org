<?php
/**
 * Tests that bulk actions only query explicitly selected plugins.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Admin\Customizations;

/**
 * Covers bulk-selection validation before any database writes or redirects.
 *
 * @group admin
 */
class Bulk_Action_Selection_Test extends TestCase {

	/**
	 * Empty or invalid selections must never become an unrestricted query.
	 */
	public function test_empty_selection_does_not_query_plugins(): void {
		// phpcs:ignore WordPress.Security.NonceVerification -- Back up request fixtures for restoration after the test.
		$saved_request  = $_REQUEST;
		$customizations = ( new ReflectionClass( Customizations::class ) )->newInstanceWithoutConstructor();
		$reject_query   = function () {
			$this->fail( 'An empty bulk selection must not query any plugins.' );
		};
		add_action( 'pre_get_posts', $reject_query );

		try {
			foreach ( [ 'plugin_open', 'plugin_close', 'plugin_disable', 'plugin_reject', 'plugin_assign' ] as $action ) {
				foreach ( [ null, [], [ '0', 'invalid', '' ] ] as $selection ) {
					$_REQUEST = [
						'action'    => $action,
						'action2'   => '-1',
						'post_type' => 'plugin',
						'reviewer'  => '1',
						'_wpnonce'  => wp_create_nonce( 'bulk-posts' ),
					];
					if ( null !== $selection ) {
						$_REQUEST['post'] = $selection;
					}

					$this->assertNull( $customizations->bulk_action_plugins() );
				}
			}
		} finally {
			remove_action( 'pre_get_posts', $reject_query );
			$_REQUEST = $saved_request;
		}
	}

	/**
	 * Uses the same sanitized selection for the query and its result limit.
	 */
	public function test_selected_plugins_constrain_the_query(): void {
		// phpcs:ignore WordPress.Security.NonceVerification -- Back up request fixtures for restoration after the test.
		$saved_request  = $_REQUEST;
		$customizations = ( new ReflectionClass( Customizations::class ) )->newInstanceWithoutConstructor();
		$capture_query  = function ( $query ) {
			$this->assertSame( [ 12, 34 ], array_values( $query->get( 'post__in' ) ) );
			$this->assertSame( 2, $query->get( 'posts_per_page' ) );

			// Stop before the query executes or the bulk action modifies any posts.
			throw new RuntimeException( 'Captured the selected plugins query.' );
		};
		add_action( 'pre_get_posts', $capture_query );

		try {
			$_REQUEST = [
				'action'    => 'plugin_close',
				'action2'   => '-1',
				'post_type' => 'plugin',
				'post'      => [ '12', 'invalid', '34', '0' ],
				'_wpnonce'  => wp_create_nonce( 'bulk-posts' ),
			];
			$this->expectException( RuntimeException::class );
			$this->expectExceptionMessage( 'Captured the selected plugins query.' );
			$customizations->bulk_action_plugins();
		} finally {
			remove_action( 'pre_get_posts', $capture_query );
			$_REQUEST = $saved_request;
		}
	}
}

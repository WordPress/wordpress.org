<?php
/**
 * Tests the design lockdown.
 *
 * @package WordPressTV
 */

declare( strict_types = 1 );

namespace WordPressTV\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;

/**
 * The design lives in the theme code, so not even administrators change it.
 */
class Lockdown_Test extends TestCase {

	/**
	 * Logs in an administrator, who may otherwise edit the design.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$user = get_user_by( 'login', 'wptv-admin' );
		$id   = $user ? $user->ID : wp_insert_user(
			array(
				'user_login' => 'wptv-admin',
				'user_pass'  => wp_generate_password(),
				'role'       => 'administrator',
			)
		);
		wp_set_current_user( $id );
	}

	/**
	 * Logs the administrator out.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		wp_set_current_user( 0 );

		parent::tearDown();
	}

	/**
	 * REST requests, with the status each gets.
	 *
	 * @return array[]
	 */
	public static function data_requests(): array {
		return array(
			'create a template'      => array( 'POST', '/wp/v2/templates', 403 ),
			'update a template part' => array( 'PUT', '/wp/v2/template-parts/wptv-27//header', 403 ),
			'update global styles'   => array( 'POST', '/wp/v2/global-styles/1', 403 ),
			'list templates'         => array( 'GET', '/wp/v2/templates', 200 ),
		);
	}

	/**
	 * Writes to templates, template parts and global styles are rejected; reads are not.
	 *
	 * @param string $method Request method.
	 * @param string $route  REST route.
	 * @param int    $status Expected response status.
	 * @return void
	 */
	#[DataProvider( 'data_requests' )]
	public function test_design_writes_are_rejected( string $method, string $route, int $status ): void {
		$response = rest_do_request( new WP_REST_Request( $method, $route ) );

		$this->assertSame( $status, $response->get_status() );
	}
}

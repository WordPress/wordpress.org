<?php
/**
 * Tests that a failed GitHub request never reaches the stored PR data.
 *
 * GitHub answers a failed request, such as one over the rate limit, with an error
 * object where a list was asked for. Read as a list, it gives a PR no reviews and
 * no tests, which would then replace the PR's stored data.
 *
 * @package trac-pr
 *
 * @phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Trac\GithubPRs;

use PHPUnit\Framework\TestCase;

/**
 * Tests for fetching a PR's details from GitHub.
 */
class Trac_Fetch_PR_Data_Test extends TestCase {

	/**
	 * GitHub's answer to a request over the rate limit.
	 */
	const RATE_LIMITED = '{"message":"API rate limit exceeded for installation.","documentation_url":"https://docs.github.com/rest/overview/rate-limits-for-the-rest-api","status":"403"}';

	/**
	 * GitHub's answer to a request for a PR that does not exist.
	 */
	const NOT_FOUND = '{"message":"Not Found","status":"404"}';

	/**
	 * A gateway's answer when GitHub is down.
	 */
	const SERVER_ERROR = '<html><body>502 Bad Gateway</body></html>';

	/**
	 * Serves GitHub requests from the fake.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		Fake_GitHub_Stream::register();
	}

	/**
	 * Restores real `https` requests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Fake_GitHub_Stream::unregister();
		parent::tearDown();
	}

	/**
	 * Registers a GitHub response for each request a fetch makes.
	 *
	 * @param array $overrides Bodies replacing the successful ones, keyed by request.
	 * @return void
	 */
	private function respond( array $overrides = array() ): void {
		$bodies = array_merge(
			array(
				'pr'         => array(
					'number'          => 1,
					'head'            => array(
						'sha'   => 'abc123',
						'label' => 'contributor:add-tests',
						'ref'   => 'add-tests',
					),
					'base'            => array( 'repo' => array( 'full_name' => 'WordPress/wordpress-develop' ) ),
					'html_url'        => 'https://github.com/WordPress/wordpress-develop/pull/1',
					'state'           => 'open',
					'title'           => 'Tests: Add coverage',
					'created_at'      => '2026-10-20T08:00:00Z',
					'updated_at'      => '2026-10-20T08:00:00Z',
					'closed_at'       => null,
					'mergeable_state' => 'clean',
					'body'            => 'Trac ticket: https://core.trac.wordpress.org/ticket/65819',
					'user'            => array(
						'html_url' => 'https://github.com/contributor',
						'login'    => 'contributor',
					),
					'additions'       => 1,
					'deletions'       => 1,
					'diff_url'        => 'https://github.com/WordPress/wordpress-develop/pull/1.diff',
				),
				'check_runs' => array(
					'total_count' => 1,
					'check_runs'  => array(
						array(
							'status'     => 'completed',
							'conclusion' => 'success',
							'app'        => array( 'name' => 'GitHub Actions' ),
						),
					),
				),
				'reviews'    => array(
					array(
						'state' => 'APPROVED',
						'user'  => array( 'login' => 'reviewer' ),
					),
				),
				'files'      => array(
					array( 'filename' => 'tests/phpunit/tests/example.php' ),
				),
			),
			$overrides
		);

		$encode = function ( mixed $body ): string {
			return is_string( $body ) ? $body : json_encode( $body, JSON_THROW_ON_ERROR );
		};

		Fake_GitHub_Stream::$responses = array(
			'!/pulls/1$!'         => $encode( $bodies['pr'] ),
			'!/check-runs$!'      => $encode( $bodies['check_runs'] ),
			'!/pulls/1/reviews$!' => $encode( $bodies['reviews'] ),
			'!/pulls/1/files\?!'  => $encode( $bodies['files'] ),
		);
	}

	/**
	 * A PR whose requests all succeed has its checks, reviews, and tests read.
	 *
	 * @return void
	 */
	public function test_a_pr_is_fetched(): void {
		$this->respond();

		$pr = fetch_pr_data( 'WordPress/wordpress-develop', 1 );

		$this->assertIsObject( $pr );
		$this->assertSame( array( 'GitHub Actions' => 'success' ), $pr->check_runs );
		$this->assertSame( array( 'APPROVED' => array( 'reviewer' ) ), $pr->reviews );
		$this->assertTrue( $pr->touches_tests );
		$this->assertFalse( property_exists( $pr, 'partial' ) );
	}

	/**
	 * Partial details that are complete say so.
	 *
	 * @return void
	 */
	public function test_complete_details_are_not_partial(): void {
		$this->respond();

		$pr = fetch_partial_pr_data( 'WordPress/wordpress-develop', 1 );

		$this->assertIsObject( $pr );
		$this->assertFalse( $pr->partial );
	}

	/**
	 * A PR with no checks, reviews, or files is still fetched.
	 *
	 * @return void
	 */
	public function test_a_pr_with_empty_lists_is_fetched(): void {
		$this->respond(
			array(
				'check_runs' => array(
					'total_count' => 0,
					'check_runs'  => array(),
				),
				'reviews'    => array(),
				'files'      => array(),
			)
		);

		$pr = fetch_pr_data( 'WordPress/wordpress-develop', 1 );

		$this->assertIsObject( $pr );
		$this->assertSame( array(), $pr->check_runs );
		$this->assertSame( array(), $pr->reviews );
		$this->assertFalse( $pr->touches_tests );
	}

	/**
	 * Any failed request fails the whole fetch, so the stored data is kept.
	 *
	 * @dataProvider data_failed_requests
	 *
	 * @param string $request The request that fails.
	 * @param string $body    What GitHub answers it with.
	 * @return void
	 */
	public function test_a_failed_request_fails_the_fetch( string $request, string $body ): void {
		$this->respond( array( $request => $body ) );

		$this->assertFalse( fetch_pr_data( 'WordPress/wordpress-develop', 1 ) );
	}

	/**
	 * Supplies each request a fetch makes, with a failed answer to it.
	 *
	 * @return array Requests and the bodies they fail with.
	 */
	public static function data_failed_requests(): array {
		return array(
			'pr not found'            => array( 'pr', self::NOT_FOUND ),
			'check runs rate limited' => array( 'check_runs', self::RATE_LIMITED ),
			'reviews rate limited'    => array( 'reviews', self::RATE_LIMITED ),
			'files rate limited'      => array( 'files', self::RATE_LIMITED ),
			'reviews server error'    => array( 'reviews', self::SERVER_ERROR ),
			'files server error'      => array( 'files', self::SERVER_ERROR ),
		);
	}

	/**
	 * Partial details still have the PR, with the failed list left empty.
	 *
	 * @dataProvider data_failed_lists
	 *
	 * @param string $request  The request that fails.
	 * @param string $property The detail it leaves empty.
	 * @param mixed  $expected The empty value of that detail.
	 * @return void
	 */
	public function test_a_failed_list_leaves_partial_details( string $request, string $property, mixed $expected ): void {
		$this->respond( array( $request => self::RATE_LIMITED ) );

		$pr = fetch_partial_pr_data( 'WordPress/wordpress-develop', 1 );

		$this->assertIsObject( $pr );
		$this->assertTrue( $pr->partial );
		$this->assertSame( $expected, $pr->$property );
		$this->assertSame( 'Tests: Add coverage', $pr->title );
	}

	/**
	 * Supplies each list a fetch requests, with the detail it fills.
	 *
	 * @return array Requests, details, and their empty values.
	 */
	public static function data_failed_lists(): array {
		return array(
			'check runs' => array( 'check_runs', 'check_runs', array() ),
			'reviews'    => array( 'reviews', 'reviews', array() ),
			'files'      => array( 'files', 'touches_tests', false ),
		);
	}

	/**
	 * Without the PR itself there are no details, partial or not.
	 *
	 * @return void
	 */
	public function test_a_missing_pr_has_no_partial_details(): void {
		$this->respond( array( 'pr' => self::NOT_FOUND ) );

		$this->assertFalse( fetch_partial_pr_data( 'WordPress/wordpress-develop', 1 ) );
	}
}

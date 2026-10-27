<?php
/**
 * Tests which linked PRs a ticket view refreshes from GitHub.
 *
 * Every refresh costs four GitHub API requests, so a ticket with hundreds of
 * linked PRs must not refresh them all in one view.
 *
 * @package trac-pr
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Trac\GithubPRs;

use PHPUnit\Framework\TestCase;

/**
 * Tests for picking the PRs that are due a refresh.
 */
class Trac_Refresh_Selection_Test extends TestCase {

	/**
	 * The timezone in effect before the test.
	 *
	 * @var string
	 */
	private string $timezone;

	/**
	 * Reads stored times as UTC, as the API does.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->timezone = date_default_timezone_get();
		date_default_timezone_set( 'UTC' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
	}

	/**
	 * Restores the timezone.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		date_default_timezone_set( $this->timezone ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.timezone_change_date_default_timezone_set
		parent::tearDown();
	}

	/**
	 * Builds a linked PR as the endpoint reads it from the database.
	 *
	 * @param int    $checked_ago Seconds since the PR was last fetched.
	 * @param string $state       The PR's state.
	 * @param array  $data        PR data replacing the defaults.
	 * @return object The linked PR.
	 */
	private function pr( int $checked_ago, string $state = 'open', array $data = array() ): object {
		static $number = 0;

		return (object) array(
			'repo'         => 'WordPress/wordpress-develop',
			'pr'           => ++$number,
			'last_checked' => gmdate( 'Y-m-d H:i:s', time() - $checked_ago ),
			'author'       => 0,
			'data'         => (object) array_merge(
				array(
					'state'      => $state,
					'updated_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 7 * 24 * 60 * 60 ),
					'check_runs' => (object) array( 'GitHub Actions' => 'success' ),
				),
				$data
			),
		);
	}

	/**
	 * An open PR is refreshed once it is older than the viewer's window.
	 *
	 * @dataProvider data_open_pr_windows
	 *
	 * @param bool $authenticated Whether the viewer is logged in.
	 * @param int  $checked_ago   Seconds since the PR was last fetched.
	 * @param bool $expected      Whether it is refreshed.
	 * @return void
	 */
	public function test_an_open_pr_is_refreshed_after_its_window( bool $authenticated, int $checked_ago, bool $expected ): void {
		$pr = $this->pr( $checked_ago );

		$this->assertSame( $expected ? array( $pr ) : array(), select_prs_to_refresh( array( $pr ), $authenticated ) );
	}

	/**
	 * Supplies fetch ages either side of each viewer's window.
	 *
	 * @return array Viewers, ages, and whether the PR is refreshed.
	 */
	public static function data_open_pr_windows(): array {
		return array(
			'logged in, 4 minutes'   => array( true, 4 * 60, false ),
			'logged in, 6 minutes'   => array( true, 6 * 60, true ),
			'logged out, 30 minutes' => array( false, 30 * 60, false ),
			'logged out, 61 minutes' => array( false, 61 * 60, true ),
		);
	}

	/**
	 * An open PR updated in the last half hour is refreshed every two minutes.
	 *
	 * @return void
	 */
	public function test_a_recently_updated_pr_is_refreshed_every_two_minutes(): void {
		$pr = $this->pr( 3 * 60, 'open', array( 'updated_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 10 * 60 ) ) );

		$this->assertSame( array( $pr ), select_prs_to_refresh( array( $pr ), false ) );
	}

	/**
	 * An open PR with running checks is refreshed every two minutes.
	 *
	 * @return void
	 */
	public function test_a_pr_with_running_checks_is_refreshed_every_two_minutes(): void {
		$pr = $this->pr( 3 * 60, 'open', array( 'check_runs' => (object) array( 'GitHub Actions' => 'in_progress' ) ) );

		$this->assertSame( array( $pr ), select_prs_to_refresh( array( $pr ), false ) );
	}

	/**
	 * A closed PR is refreshed once a day, however recently it changed.
	 *
	 * @return void
	 */
	public function test_a_closed_pr_is_refreshed_once_a_day(): void {
		$recent = array(
			'updated_at' => gmdate( 'Y-m-d\TH:i:s\Z', time() - 10 * 60 ),
			'check_runs' => (object) array( 'GitHub Actions' => 'in_progress' ),
		);
		$fresh  = $this->pr( 2 * 60 * 60, 'closed', $recent );
		$stale  = $this->pr( 25 * 60 * 60, 'closed' );

		$this->assertSame( array( $stale ), select_prs_to_refresh( array( $fresh, $stale ), true ) );
	}

	/**
	 * Only the limit is refreshed, PRs never fetched first and then the stalest.
	 *
	 * @return void
	 */
	public function test_only_the_stalest_prs_are_refreshed(): void {
		$prs = array();
		for ( $minutes = 10; $minutes <= 24; $minutes++ ) {
			$prs[] = $this->pr( $minutes * 60 );
		}

		$never_fetched       = $this->pr( 0 );
		$never_fetched->data = false;
		$prs[]               = $never_fetched;

		$refreshed = select_prs_to_refresh( $prs, true, 3 );

		$this->assertSame( array( $never_fetched, $prs[14], $prs[13] ), $refreshed );
	}
}

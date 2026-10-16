<?php
/**
 * Tests for the short times the sidebar shows, e.g. when a plugin was last updated.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

use PHPUnit\Framework\TestCase;

/**
 * Tests for short_time_diff().
 */
class Short_Time_Diff_Test extends TestCase {

	/**
	 * The time is rounded to the largest unit that fits, like human_time_diff().
	 *
	 * @dataProvider data_short_time_diff
	 *
	 * @param int    $seconds  Seconds between the timestamps.
	 * @param string $expected Short time.
	 * @return void
	 */
	public function test_short_time_diff( int $seconds, string $expected ): void {
		$now = 1760000000;

		$this->assertSame( $expected, short_time_diff( $now - $seconds, $now ) );
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_short_time_diff(): array {
		return array(
			'no time'       => array( 0, '1s' ),
			'seconds'       => array( 45, '45s' ),
			'a minute'      => array( MINUTE_IN_SECONDS, '1m' ),
			'hours'         => array( 3 * HOUR_IN_SECONDS, '3h' ),
			'a day'         => array( DAY_IN_SECONDS, '1d' ),
			'rounded up'    => array( DAY_IN_SECONDS + 13 * HOUR_IN_SECONDS, '2d' ),
			'weeks'         => array( 2 * WEEK_IN_SECONDS, '2w' ),
			'months'        => array( 2 * MONTH_IN_SECONDS + DAY_IN_SECONDS, '2mo' ),
			'years'         => array( 12 * YEAR_IN_SECONDS, '12y' ),
			'in the future' => array( -2 * DAY_IN_SECONDS, '2d' ),
		);
	}
}

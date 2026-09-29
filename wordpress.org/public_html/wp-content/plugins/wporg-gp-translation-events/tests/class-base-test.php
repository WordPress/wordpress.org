<?php
/**
 * Base test case shared by the plugin's PHPUnit tests.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\Tests;

use DateTimeImmutable;
use GP_UnitTestCase;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Base test case providing common setup for the plugin's tests.
 */
abstract class Base_Test extends GP_UnitTestCase {
	/**
	 * The current time, captured once at the start of each test.
	 *
	 * @var DateTimeImmutable
	 */
	protected DateTimeImmutable $now;

	/**
	 * Sets up the test case before each test runs.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->now = Translation_Events::now();
	}

	/**
	 * Restore the PHPUnit 9 API that Core's expectDeprecated() relies on.
	 *
	 * Core's test framework predates PHPUnit 10 and falls back to other
	 * PHPUnit 9 APIs when this method is missing. None of these tests use
	 * annotation-based expectations, so an empty set is equivalent.
	 *
	 * @return array[]
	 */
	public function getAnnotations(): array {
		return array(
			'class'  => array(),
			'method' => array(),
		);
	}
}

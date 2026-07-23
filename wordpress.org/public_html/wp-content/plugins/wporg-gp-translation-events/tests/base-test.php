<?php

namespace Wporg\Tests;

use DateTimeImmutable;
use GP_UnitTestCase;
use Wporg\TranslationEvents\Translation_Events;

abstract class Base_Test extends GP_UnitTestCase {
	protected DateTimeImmutable $now;

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

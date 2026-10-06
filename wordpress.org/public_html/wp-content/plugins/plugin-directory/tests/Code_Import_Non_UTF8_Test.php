<?php
/**
 * Tests for Code_Import::strip_non_utf8_entries().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\I18N\Code_Import;

/**
 * Tests that POT entries GlotPress can't parse are removed before import.
 *
 * @group cli
 */
#[Group( 'cli' )]
class Code_Import_Non_UTF8_Test extends TestCase {

	/**
	 * Build a POT file in the shape make-pot writes.
	 *
	 * @param string ...$entries The entries following the header.
	 * @return string The POT file contents.
	 */
	private function pot( string ...$entries ): string {
		$header = "# Copyright (C) 2026 Caf\xE9\n"
			. "msgid \"\"\n"
			. "msgstr \"\"\n"
			. "\"Project-Id-Version: Caf\xE9 Plugin 1.0.0\\n\"\n"
			. "\"Content-Type: text/plain; charset=UTF-8\\n\"\n";

		return implode( "\n\n", array_merge( array( rtrim( $header, "\n" ) ), $entries ) ) . "\n";
	}

	/**
	 * A POT without invalid entries is returned unchanged.
	 */
	public function test_valid_pot_is_unchanged(): void {
		$pot = "msgid \"\"\nmsgstr \"\"\n\"Content-Type: text/plain; charset=UTF-8\\n\"\n\n"
			. "#: plugin.php:3\nmsgid \"Café\"\nmsgstr \"\"\n\n"
			. "#: plugin.php:4\nmsgid \"Hello\"\nmsgid_plural \"Hellos\"\nmsgstr[0] \"\"\nmsgstr[1] \"\"\n";

		$result = Code_Import::strip_non_utf8_entries( $pot );

		$this->assertSame( $pot, $result['pot'] );
		$this->assertSame( 0, $result['count'] );
		$this->assertSame( array(), $result['files'] );
	}

	/**
	 * Invalid entries are removed and their source files are reported without line numbers.
	 */
	public function test_invalid_entries_are_removed(): void {
		$valid = "#: plugin.php:3\nmsgid \"Café\"\nmsgstr \"\"";

		$result = Code_Import::strip_non_utf8_entries(
			$this->pot(
				"#: states/states-01.php:10\nmsgid \"Bi\xE9\"\nmsgstr \"\"",
				$valid,
				"#: states/states-01.php:14 states/states-02.php:8\n#: states/states-03.php:2\nmsgid \"Hu\xEDla\"\nmsgstr \"\"",
				"#: states/states-02.php:20\nmsgid \"U\xEDge\"\nmsgstr \"\""
			)
		);

		$this->assertSame( $this->pot( $valid ), $result['pot'] );
		$this->assertSame( 3, $result['count'] );
		$this->assertSame(
			array( 'states/states-01.php', 'states/states-02.php', 'states/states-03.php' ),
			$result['files']
		);
	}

	/**
	 * A valid string keeps its entry; only the comment lines that aren't valid UTF-8 are removed.
	 */
	public function test_invalid_comments_are_removed_from_valid_strings(): void {
		$result = Code_Import::strip_non_utf8_entries(
			$this->pot( "#. translators: %s: n\xFAmero\n#: inc/caf\xE9.php:3\n#: plugin.php:7\nmsgid \"Number %s\"\nmsgstr \"\"" )
		);

		$this->assertSame( $this->pot( "#: plugin.php:7\nmsgid \"Number %s\"\nmsgstr \"\"" ), $result['pot'] );
		$this->assertSame( 0, $result['count'] );
		$this->assertSame( array(), $result['files'] );
	}

	/**
	 * The header is kept even when it isn't valid UTF-8, and when it's the only entry left.
	 */
	public function test_header_is_kept(): void {
		$result = Code_Import::strip_non_utf8_entries(
			$this->pot( "#: plugin.php:3\nmsgid \"Caf\xE9\"\nmsgstr \"\"" )
		);

		$this->assertSame( $this->pot(), $result['pot'] );
		$this->assertSame( 1, $result['count'] );
		$this->assertSame( array( 'plugin.php' ), $result['files'] );
	}
}

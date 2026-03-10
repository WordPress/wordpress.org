<?php

use WordPressdotorg\Plugin_Directory\Readme\Parser;

/**
 * @group plugin-directory
 * @group readme
 */
class Tests_Readme_Parser extends WP_UnitTestCase {

	protected static $valid_readme = <<<'README'
=== My Test Plugin ===
Contributors: johndoe
Tags: test, unit-test
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2.3
Requires PHP: 7.4
License: GPLv2

A short description of the plugin.

== Description ==

This is a longer description of the plugin.

== Installation ==

1. Upload the plugin.
2. Activate it.

== Frequently Asked Questions ==

= How does it work? =

It just works.

== Changelog ==

= 1.2.3 =
* Fixed a bug.
README;

	function test_parses_plugin_name(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( 'My Test Plugin', $parser->name );
	}

	function test_parses_contributors(): void {
		// Create a user so the contributor sanitization finds them.
		self::factory()->user->create( [ 'user_login' => 'johndoe', 'user_nicename' => 'johndoe' ] );

		$parser = new Parser( self::$valid_readme );
		$this->assertSame( [ 'johndoe' ], $parser->contributors );
	}

	function test_invalid_contributor_produces_warning(): void {
		$parser = new Parser( self::$valid_readme );

		// 'johndoe' does not exist as a WP user, so it should be ignored with a warning.
		$this->assertEmpty( $parser->contributors );
		$this->assertArrayHasKey( 'contributor_ignored', $parser->warnings );
	}

	function test_parses_tags(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( [ 'test', 'unit-test' ], $parser->tags );
	}

	function test_parses_requires(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( '5.0', $parser->requires );
	}

	function test_parses_tested(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( '6.4', $parser->tested );
	}

	function test_parses_stable_tag(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( '1.2.3', $parser->stable_tag );
	}

	function test_parses_requires_php(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( '7.4', $parser->requires_php );
	}

	function test_parses_short_description(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( 'A short description of the plugin.', $parser->short_description );
	}

	function test_parses_license(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertSame( 'GPLv2', $parser->license );
	}

	function test_parses_sections(): void {
		$parser = new Parser( self::$valid_readme );

		$this->assertArrayHasKey( 'description', $parser->sections );
		$this->assertArrayHasKey( 'installation', $parser->sections );
		$this->assertArrayHasKey( 'faq', $parser->sections );
		$this->assertArrayHasKey( 'changelog', $parser->sections );
	}

	function test_description_section_content(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertStringContainsString( 'longer description', $parser->sections['description'] );
	}

	function test_faq_parsed(): void {
		$parser = new Parser( self::$valid_readme );
		$this->assertNotEmpty( $parser->faq );
		$this->assertArrayHasKey( 'How does it work?', $parser->faq );
	}

	function test_empty_readme_produces_warnings(): void {
		$parser = new Parser( '' );
		$this->assertEmpty( $parser->name );
	}

	function test_readme_without_name_header(): void {
		$readme = <<<'README'
Contributors: johndoe
Tags: test
Requires at least: 5.0
Tested up to: 6.0
Stable tag: 1.0

Short description.

== Description ==

Long description.
README;
		$parser = new Parser( $readme );
		$this->assertArrayHasKey( 'invalid_plugin_name_header', $parser->warnings );
	}

	function test_readme_alias_sections(): void {
		$readme = <<<'README'
=== Test Plugin ===
Contributors: johndoe
Stable tag: 1.0

Short description.

== Frequently Asked Questions ==

= Question? =

Answer.

== Change Log ==

= 1.0 =
* Initial release.
README;
		$parser = new Parser( $readme );

		// 'frequently_asked_questions' is aliased to 'faq'.
		$this->assertArrayHasKey( 'faq', $parser->sections );

		// 'change_log' is aliased to 'changelog'.
		$this->assertArrayHasKey( 'changelog', $parser->sections );
	}

	function test_ignored_tags_filtered(): void {
		$readme = <<<'README'
=== Test Plugin ===
Contributors: johndoe
Tags: plugin, wordpress, seo, test
Stable tag: 1.0

Short description.

== Description ==

A plugin.
README;
		$parser = new Parser( $readme );

		// 'plugin' and 'wordpress' should be filtered out.
		$this->assertNotContains( 'plugin', $parser->tags );
		$this->assertNotContains( 'wordpress', $parser->tags );
		$this->assertContains( 'seo', $parser->tags );
		$this->assertContains( 'test', $parser->tags );
	}

	function test_valid_headers_mapping(): void {
		// 'tested up to' and 'tested' both map to 'tested'.
		$readme_tested_up_to = <<<'README'
=== Test ===
Contributors: johndoe
Tested up to: 6.5
Stable tag: 1.0

Short desc.
README;
		$parser = new Parser( $readme_tested_up_to );
		$this->assertSame( '6.5', $parser->tested );

		// 'requires at least' maps to 'requires'.
		$readme_requires = <<<'README'
=== Test ===
Contributors: johndoe
Requires at least: 5.5
Stable tag: 1.0

Short desc.
README;
		$parser = new Parser( $readme_requires );
		$this->assertSame( '5.5', $parser->requires );
	}

	function test_long_short_description_produces_warning(): void {
		$long_desc = str_repeat( 'a ', 100 ); // 200 chars.
		$readme    = "=== Test ===\nContributors: johndoe\nStable tag: 1.0\n\n{$long_desc}\n\n== Description ==\n\nDesc.";

		$parser = new Parser( $readme );
		$this->assertArrayHasKey( 'trimmed_short_description', $parser->warnings );
	}

	function test_donate_link_parsed(): void {
		$readme = <<<'README'
=== Test ===
Contributors: johndoe
Donate link: https://example.com/donate
Stable tag: 1.0

Short desc.

== Description ==

Desc.
README;
		$parser = new Parser( $readme );
		$this->assertSame( 'https://example.com/donate', $parser->donate_link );
	}
}

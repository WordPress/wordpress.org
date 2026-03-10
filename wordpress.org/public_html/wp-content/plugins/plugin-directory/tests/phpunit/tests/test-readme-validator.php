<?php

use WordPressdotorg\Plugin_Directory\Readme\Validator;

/**
 * @group plugin-directory
 * @group readme-validator
 */
class Tests_Readme_Validator extends WP_UnitTestCase {

	protected static $valid_readme = <<<'README'
=== My Test Tool ===
Contributors: validatoruser
Tags: test, unit-test
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.2.3
Requires PHP: 7.4
License: GPLv2
Donate link: https://example.com/donate

A short description of the tool.

== Description ==

This is a longer description of the tool.

== Frequently Asked Questions ==

= How does it work? =

It just works.

== Changelog ==

= 1.2.3 =
* Fixed a bug.

== Screenshots ==

1. The main settings screen.

== Upgrade Notice ==

= 1.2.3 =
Bug fix release.
README;

	static function wpSetUpBeforeClass( $factory ) {
		// Create a user so the contributor sanitization finds them.
		$factory->user->create( [ 'user_login' => 'validatoruser', 'user_nicename' => 'validatoruser' ] );
	}

	function test_valid_readme_has_no_errors(): void {
		$validator = Validator::instance();
		$result    = $validator->validate( self::$valid_readme );

		$this->assertEmpty( $result['errors'], 'Expected no errors for valid readme. Got: ' . print_r( $result['errors'], true ) );
	}

	function test_valid_readme_has_no_warnings(): void {
		$validator = Validator::instance();
		$result    = $validator->validate( self::$valid_readme );

		$this->assertEmpty( $result['warnings'], 'Expected no warnings for valid readme. Got: ' . print_r( $result['warnings'], true ) );
	}

	function test_missing_plugin_name_is_error(): void {
		$readme = <<<'README'
Contributors: johndoe
Stable tag: 1.0

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'invalid_plugin_name_header', $result['errors'] );
	}

	function test_missing_tested_is_warning(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Requires at least: 5.0
Stable tag: 1.0
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'tested_header_missing', $result['warnings'] );
	}

	function test_missing_stable_tag_is_warning(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'stable_tag_invalid', $result['warnings'] );
	}

	function test_trunk_stable_tag_is_warning(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: trunk
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'stable_tag_invalid', $result['warnings'] );
	}

	function test_missing_requires_is_note(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: 1.0
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'requires_header_missing', $result['notes'] );
	}

	function test_missing_requires_php_is_note(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: 1.0
Requires at least: 5.0
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'requires_php_header_missing', $result['notes'] );
	}

	function test_missing_faq_is_note(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: 1.0
License: GPLv2

Short description.

== Description ==

Long description.

== Changelog ==

= 1.0 =
* Initial release.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'faq_missing', $result['notes'] );
	}

	function test_missing_changelog_is_note(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: 1.0
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'changelog_missing', $result['notes'] );
	}

	function test_missing_screenshots_is_note(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: 1.0
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'screenshots_missing', $result['notes'] );
	}

	function test_missing_donate_link_is_note(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: 1.0
License: GPLv2

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'donate_link_missing', $result['notes'] );
	}

	function test_missing_license_is_warning(): void {
		$readme = <<<'README'
=== Test Tool ===
Contributors: validatoruser
Tested up to: 6.4
Stable tag: 1.0

Short description.

== Description ==

Long description.
README;
		$validator = Validator::instance();
		$result    = $validator->validate( $readme );

		$this->assertArrayHasKey( 'license_missing', $result['warnings'] );
	}

	/**
	 * Test translate_code_to_message returns strings for known codes.
	 *
	 * @dataProvider data_translate_code_to_message
	 */
	function test_translate_code_to_message( $code ): void {
		$validator = Validator::instance();
		$result    = $validator->translate_code_to_message( $code );

		$this->assertIsString( $result );
		$this->assertNotEmpty( $result );
	}

	function data_translate_code_to_message(): array {
		return [
			'invalid name'         => [ 'invalid_plugin_name_header' ],
			'tested missing'       => [ 'tested_header_missing' ],
			'stable tag invalid'   => [ 'stable_tag_invalid' ],
			'contributors missing' => [ 'contributors_missing' ],
			'faq missing'          => [ 'faq_missing' ],
			'changelog missing'    => [ 'changelog_missing' ],
			'screenshots missing'  => [ 'screenshots_missing' ],
			'donate link missing'  => [ 'donate_link_missing' ],
			'license missing'      => [ 'license_missing' ],
			'requires missing'     => [ 'requires_header_missing' ],
			'requires php missing' => [ 'requires_php_header_missing' ],
			'upgrade notice'       => [ 'upgrade_notice_missing' ],
		];
	}

	function test_translate_code_to_message_unknown_code_returns_false(): void {
		$validator = Validator::instance();
		$result    = $validator->translate_code_to_message( 'nonexistent_code' );

		$this->assertFalse( $result );
	}

	function test_translate_code_contributor_ignored_with_data(): void {
		$validator = Validator::instance();
		$result    = $validator->translate_code_to_message( 'contributor_ignored', [ 'fakeuser1', 'fakeuser2' ] );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'fakeuser1', $result );
		$this->assertStringContainsString( 'fakeuser2', $result );
	}

	function test_translate_code_contributor_ignored_without_data(): void {
		$validator = Validator::instance();
		$result    = $validator->translate_code_to_message( 'contributor_ignored' );

		$this->assertIsString( $result );
		$this->assertStringContainsString( 'Contributors', $result );
	}

	function test_validate_content_returns_translated_messages(): void {
		$validator = Validator::instance();
		$result    = $validator->validate_content( '' );

		// validate_content translates error codes to human-readable strings.
		foreach ( $result['errors'] as $message ) {
			$this->assertIsString( $message );
		}
		foreach ( $result['warnings'] as $message ) {
			$this->assertIsString( $message );
		}
		foreach ( $result['notes'] as $message ) {
			$this->assertIsString( $message );
		}
	}

	function test_last_content_stored(): void {
		$validator = Validator::instance();
		$readme    = "=== Test ===\nStable tag: 1.0\n\nDesc.";
		$validator->validate( $readme );

		$this->assertSame( $readme, $validator->last_content );
	}
}

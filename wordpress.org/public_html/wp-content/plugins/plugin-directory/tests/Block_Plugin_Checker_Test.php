<?php
/**
 * Tests for the Block Plugin Checker's individual checks and the way its messages
 * are rendered.
 *
 * These exercise the checks that can run purely off injected state. Checks that
 * need a repo export, a database or a filesystem scan are out of scope here and
 * are, with the exception of check_for_translation_function (see
 * Block_Plugin_Checker_Translation_Test), not yet covered anywhere.
 *
 * Escaping is checked inline on the checks that emit plugin-controlled data
 * (license, block name, block.json path), since that is where the values flow in.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\CLI\Block_Plugin_Checker;
use WordPressdotorg\Plugin_Directory\Shortcodes\Block_Validator;

/**
 * Unit tests for the individual Block Plugin Checker checks and message rendering.
 *
 * The group is declared as an attribute, not `@group`: PHPUnit 11 ignores a class
 * doc-block entirely once the class carries any attribute, so `@group` here would
 * silently drop the class out of `--group block-validator` runs.
 */
#[Group( 'block-validator' )]
#[CoversClass( Block_Plugin_Checker::class )]
#[CoversClass( Block_Validator::class )]
class Block_Plugin_Checker_Test extends TestCase {

	/**
	 * Build a checker and inject the given protected properties, bypassing the
	 * constructor (which registers an extra_plugin_headers filter).
	 *
	 * @param array $props Property name => value to set on the checker.
	 * @return Block_Plugin_Checker
	 */
	private function checker( array $props = array() ): Block_Plugin_Checker {
		$reflection = new ReflectionClass( Block_Plugin_Checker::class );
		$checker    = $reflection->newInstanceWithoutConstructor();

		foreach ( $props as $name => $value ) {
			$property = $reflection->getProperty( $name );
			$property->setAccessible( true );
			$property->setValue( $checker, $value );
		}

		return $checker;
	}

	/**
	 * Pull the message strings out of a check's results.
	 *
	 * @param Block_Plugin_Checker $checker The checker that has run a check.
	 * @param string|null          $type    Result type to filter on, or null for any.
	 * @param string|null          $check   Check name to filter on, or null for any.
	 * @return string[]
	 */
	private function messages( Block_Plugin_Checker $checker, ?string $type = null, ?string $check = null ): array {
		return array_map(
			static fn( $result ) => $result->message,
			$checker->get_results( $type, $check )
		);
	}

	/*
	 * Tests for check_license.
	 */

	/**
	 * A license missing from both the readme and the plugin headers is a warning.
	 */
	public function test_missing_license_warns() {
		$checker = $this->checker( array( 'readme' => (object) array( 'license' => '' ) ) );
		$checker->check_license();

		$this->assertCount( 1, $checker->get_results( 'warning', 'check_license' ) );
	}

	/**
	 * The readme license is interpolated into the result message, so check_license()
	 * must escape it. Parser::sanitize_text() strips markup out of the license first;
	 * this covers the case where the checker is handed a readme that has not.
	 */
	public function test_readme_license_is_reported_and_escaped() {
		$checker = $this->checker( array( 'readme' => (object) array( 'license' => 'GPLv2 <img src=x onerror=alert(document.domain)>' ) ) );
		$checker->check_license();

		$messages = $this->messages( $checker, 'info', 'check_license' );
		$this->assertCount( 1, $messages );
		$this->assertStringNotContainsString( '<img', $messages[0] );
		$this->assertStringContainsString( '&lt;img', $messages[0] );
	}

	/**
	 * The same, for a License read from the plugin file headers instead of the readme.
	 */
	public function test_plugin_header_license_is_reported_and_escaped() {
		$checker = $this->checker(
			array(
				'readme'  => (object) array( 'license' => '' ),
				'headers' => (object) array( 'License' => 'GPLv2 <img src=x onerror=alert(document.domain)>' ),
			)
		);
		$checker->check_license();

		$messages = $this->messages( $checker, 'info', 'check_license' );
		$this->assertCount( 1, $messages );
		$this->assertStringNotContainsString( '<img', $messages[0] );
		$this->assertStringContainsString( '&lt;img', $messages[0] );
	}

	/**
	 * Escaping is idempotent in WordPress, so running esc_html() over a license that
	 * Parser already sanitized must not turn "&amp;" into "&amp;amp;" on the page.
	 */
	public function test_benign_license_is_not_double_escaped() {
		$checker = $this->checker( array( 'readme' => (object) array( 'license' => 'GPLv2 (see LICENSE &amp; COPYING)' ) ) );
		$checker->check_license();

		$messages = $this->messages( $checker, 'info', 'check_license' );
		$this->assertCount( 1, $messages );
		$this->assertStringContainsString( 'LICENSE &amp; COPYING', $messages[0] );
		$this->assertStringNotContainsString( '&amp;amp;', $messages[0] );
	}

	/*
	 * Tests for check_plugin_headers.
	 */

	/**
	 * A plugin with no readable headers is not a WordPress plugin.
	 */
	public function test_missing_headers_error() {
		$checker = $this->checker( array( 'headers' => null ) );
		$checker->check_plugin_headers();

		$this->assertCount( 1, $checker->get_results( 'error', 'check_plugin_headers' ) );
	}

	/*
	 * Tests for check_block_tag.
	 */

	/**
	 * Data provider for {@see test_block_tag()}.
	 *
	 * @return array<string, array{0: string[], 1: string}>
	 */
	public static function block_tag_provider(): array {
		return array(
			'present'            => array( array( 'block' ), 'info' ),
			'present mixed case' => array( array( 'Block' ), 'info' ),
			'other tags only'    => array( array( 'gallery', 'media' ), 'warning' ),
			'no tags'            => array( array(), 'warning' ),
		);
	}

	/**
	 * The "block" tag is required (case-insensitively); its absence is a warning.
	 *
	 * @param string[] $tags     Tags parsed from the readme.
	 * @param string   $expected Expected result type.
	 */
	#[DataProvider( 'block_tag_provider' )]
	public function test_block_tag( array $tags, string $expected ) {
		$checker = $this->checker( array( 'readme' => (object) array( 'tags' => $tags ) ) );
		$checker->check_block_tag();

		$this->assertCount( 1, $checker->get_results( $expected, 'check_block_tag' ) );
	}

	/*
	 * Tests for check_for_blocks.
	 */

	/**
	 * A block plugin with no blocks is a fatal error.
	 */
	public function test_no_blocks_error() {
		$checker = $this->checker( array( 'blocks' => array() ) );
		$checker->check_for_blocks();

		$this->assertCount( 1, $checker->get_results( 'error', 'check_for_blocks' ) );
	}

	/**
	 * A reasonable number of blocks is reported as an informational note.
	 */
	public function test_blocks_are_counted() {
		$checker = $this->checker(
			array(
				'blocks' => array(
					'sample/one' => (object) array( 'name' => 'sample/one' ),
					'sample/two' => (object) array( 'name' => 'sample/two' ),
				),
			)
		);
		$checker->check_for_blocks();

		$this->assertCount( 1, $checker->get_results( 'info', 'check_for_blocks' ) );
	}

	/*
	 * Tests for check_for_standard_block_name.
	 */

	/**
	 * A conventional namespaced block name raises nothing.
	 */
	public function test_valid_block_name_passes() {
		$checker = $this->checker( array( 'blocks' => array( (object) array( 'name' => 'my-plugin/my-block' ) ) ) );
		$checker->check_for_standard_block_name();

		$this->assertEmpty( $checker->get_results( 'error', 'check_for_standard_block_name' ) );
	}

	/**
	 * A malformed block name is echoed back inside <code>, so the name must be escaped.
	 */
	public function test_invalid_block_name_errors_and_is_escaped() {
		$checker = $this->checker( array( 'blocks' => array( (object) array( 'name' => 'sample/<img src=x onerror=alert(document.domain)>' ) ) ) );
		$checker->check_for_standard_block_name();

		$messages = $this->messages( $checker, 'error', 'check_for_standard_block_name' );
		$this->assertCount( 1, $messages );
		$this->assertStringNotContainsString( '<img', $messages[0] );
		$this->assertStringContainsString( '<code>', $messages[0] );
	}

	/**
	 * Reserved namespaces from block templates (core/, create-block/, …) are rejected.
	 */
	public function test_reserved_namespace_errors() {
		$checker = $this->checker( array( 'blocks' => array( (object) array( 'name' => 'core/my-block' ) ) ) );
		$checker->check_for_standard_block_name();

		$this->assertCount( 1, $checker->get_results( 'error', 'check_for_standard_block_name' ) );
	}

	/*
	 * Tests for check_for_multiple_namespaces.
	 */

	/**
	 * Blocks sharing one namespace are fine.
	 */
	public function test_single_namespace_passes() {
		$checker = $this->checker(
			array(
				'blocks' => array(
					(object) array( 'name' => 'sample/one' ),
					(object) array( 'name' => 'sample/two' ),
				),
			)
		);
		$checker->check_for_multiple_namespaces();

		$this->assertEmpty( $checker->get_results( 'error', 'check_for_multiple_namespaces' ) );
	}

	/**
	 * Blocks spread across namespaces suggest an unrelated suite bundled together.
	 */
	public function test_multiple_namespaces_error() {
		$checker = $this->checker(
			array(
				'blocks' => array(
					(object) array( 'name' => 'sample/one' ),
					(object) array( 'name' => 'other/two' ),
				),
			)
		);
		$checker->check_for_multiple_namespaces();

		$this->assertCount( 1, $checker->get_results( 'error', 'check_for_multiple_namespaces' ) );
	}

	/*
	 * Tests for check_for_block_json.
	 */

	/**
	 * A block backed by a block.json file is noted.
	 */
	public function test_block_with_block_json_is_noted() {
		$checker = $this->checker(
			array(
				'blocks'           => array( 'sample/main' => (object) array( 'name' => 'sample/main' ) ),
				'block_json_files' => array( 'sample/main' => '/tmp/plugin/block.json' ),
			)
		);
		$checker->check_for_block_json();

		$this->assertCount( 1, $checker->get_results( 'info', 'check_for_block_json' ) );
	}

	/**
	 * No block.json files anywhere is a warning.
	 */
	public function test_block_without_block_json_warns() {
		$checker = $this->checker(
			array(
				'blocks'           => array( 'sample/main' => (object) array( 'name' => 'sample/main' ) ),
				'block_json_files' => array(),
			)
		);
		$checker->check_for_block_json();

		$this->assertCount( 1, $checker->get_results( 'warning', 'check_for_block_json' ) );
	}

	/*
	 * Tests for check_for_single_parent.
	 */

	/**
	 * One top-level block (others declaring it as parent) is the expected shape.
	 */
	public function test_single_top_level_block_passes() {
		$checker = $this->checker(
			array(
				'blocks' => array(
					'sample/list'      => (object) array( 'name' => 'sample/list' ),
					'sample/list-item' => (object) array(
						'name'   => 'sample/list-item',
						'parent' => array( 'sample/list' ),
					),
				),
			)
		);
		$checker->check_for_single_parent();

		$this->assertEmpty( $checker->get_results( 'warning', 'check_for_single_parent' ) );
	}

	/**
	 * Two independent top-level blocks earns a warning.
	 */
	public function test_multiple_top_level_blocks_warn() {
		$checker = $this->checker(
			array(
				'blocks' => array(
					'sample/one' => (object) array( 'name' => 'sample/one' ),
					'sample/two' => (object) array( 'name' => 'sample/two' ),
				),
			)
		);
		$checker->check_for_single_parent();

		$this->assertCount( 1, $checker->get_results( 'warning', 'check_for_single_parent' ) );
	}

	/*
	 * Tests for check_block_json_is_valid and check_block_json_is_valid_json.
	 */

	/**
	 * A block.json that validates is reported as valid.
	 */
	public function test_valid_block_json_is_noted() {
		$checker = $this->checker(
			array(
				'path_to_plugin'        => '/tmp/plugin',
				'block_json_validation' => array( '/tmp/plugin/block.json' => true ),
			)
		);
		$checker->check_block_json_is_valid();

		$this->assertCount( 1, $checker->get_results( 'info', 'check_block_json_is_valid' ) );
	}

	/**
	 * The file path is built into a Trac link. A quote in the path would otherwise
	 * close the href attribute and turn the rest of the path into live markup.
	 */
	public function test_block_json_error_path_cannot_break_out_of_href() {
		$bad_path = '/tmp/plugin/report" onmouseover=alert(1) x="/block.json';
		$checker  = $this->checker(
			array(
				'path_to_plugin'        => '/tmp/plugin',
				'repo_url'              => 'https://plugins.svn.wordpress.org/sample/trunk',
				'block_json_validation' => array( $bad_path => new WP_Error( 'error', 'block.json[name] is required.' ) ),
			)
		);
		$checker->check_block_json_is_valid();

		$messages = $this->messages( $checker, 'warning', 'check_block_json_is_valid' );
		$this->assertCount( 1, $messages );

		// The quote survives only as an entity, never as attribute syntax.
		$this->assertStringNotContainsString( '" onmouseover', $messages[0] );
		$this->assertStringContainsString( '&quot; onmouseover', $messages[0] );
	}

	/**
	 * Block_JSON\Validator formats field names as <code>, so that has to survive the
	 * wp_kses() pass — while anything it does not emit does not.
	 */
	public function test_block_json_error_keeps_code_markup_only() {
		$checker = $this->checker(
			array(
				'path_to_plugin'        => '/tmp/plugin',
				'block_json_validation' => array(
					'/tmp/plugin/block.json' => new WP_Error(
						'error',
						'One of <code>script</code>, <code>editorScript</code> is required.<img src=x onerror=alert(1)>'
					),
				),
			)
		);
		$checker->check_block_json_is_valid();

		$messages = $this->messages( $checker, 'warning', 'check_block_json_is_valid' );
		$this->assertCount( 1, $messages );
		$this->assertStringContainsString( '<code>editorScript</code>', $messages[0] );
		$this->assertStringNotContainsString( '<img', $messages[0] );
	}

	/**
	 * A JSON parse failure is surfaced as a fatal error.
	 */
	public function test_block_json_parse_error() {
		$error   = new WP_Error( 'json_parse_error', 'Syntax error' );
		$checker = $this->checker(
			array(
				'path_to_plugin'        => '/tmp/plugin',
				'block_json_validation' => array( '/tmp/plugin/block.json' => $error ),
			)
		);
		$checker->check_block_json_is_valid_json();

		$this->assertCount( 1, $checker->get_results( 'error', 'check_block_json_is_valid_json' ) );
	}

	/*
	 * Tests for check_php_function_calls.
	 */

	/**
	 * Data provider for {@see test_php_function_calls_are_flagged()}.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function php_call_provider(): array {
		return array(
			'wp_add_inline_script' => array( 'wp_add_inline_script', 'warning' ),
			'wp_localize_script'   => array( 'wp_localize_script', 'warning' ),
			'add_shortcode'        => array( 'add_shortcode', 'error' ),
			'header'               => array( 'header', 'error' ),
			'wp_redirect'          => array( 'wp_redirect', 'error' ),
		);
	}

	/**
	 * Certain PHP calls are flagged as warnings, others as errors.
	 *
	 * @param string $function_name Function name recorded from the plugin's PHP.
	 * @param string $expected      Expected result type.
	 */
	#[DataProvider( 'php_call_provider' )]
	public function test_php_function_calls_are_flagged( string $function_name, string $expected ) {
		$checker = $this->checker( array( 'php_function_calls' => array( array( $function_name, 5, 'plugin.php' ) ) ) );
		$checker->check_php_function_calls();

		$this->assertCount( 1, $checker->get_results( $expected, 'check_php_function_calls' ) );
	}

	/*
	 * Tests for run_check_plugin_repo.
	 */

	/**
	 * Data provider for {@see test_rejected_repo_url_is_escaped()}.
	 *
	 * The URL is whatever was typed into the form on the validator page, echoed back
	 * in the rejection message, so it is the rawest input the checker handles. Each
	 * case here trips a different early return, before any network access.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function rejected_repo_url_provider(): array {
		$payload = '"><img src=x onerror=alert(document.domain)>';

		return array(
			'unparseable'     => array( 'http://' . $payload ),
			'wrong host'      => array( 'https://evil.example/' . $payload ),
			'github, no repo' => array( 'https://github.com/' . $payload ),
		);
	}

	/**
	 * A rejected repository URL is reported back with its markup escaped. Which of
	 * the early returns fires depends on how far wp_parse_url() gets with the
	 * payload; every one of them interpolates the URL, so all are covered.
	 *
	 * @param string $url URL submitted to the validator.
	 */
	#[DataProvider( 'rejected_repo_url_provider' )]
	public function test_rejected_repo_url_is_escaped( string $url ) {
		$checker  = $this->checker();
		$messages = array_map(
			static fn( $result ) => $result->message,
			$checker->run_check_plugin_repo( $url )
		);

		$this->assertCount( 1, $messages );
		$this->assertStringNotContainsString( '<img', $messages[0] );
		$this->assertStringContainsString( '&lt;img', $messages[0] );
	}

	/*
	 * Tests for get_browser_url.
	 */

	/**
	 * A ZIP upload never sets a repo URL, so there is nothing to link to. Returning
	 * '' rather than null keeps esc_url() off the PHP 8.1 deprecation path.
	 */
	public function test_browser_url_is_empty_without_a_repo() {
		$this->assertSame( '', $this->checker()->get_browser_url( '/tmp/plugin/block.json' ) );
	}

	/*
	 * Tests for the Block_Validator sanitize_message render-time backstop.
	 */

	/**
	 * The backstop drops dangerous markup but keeps the formatting the checks emit.
	 */
	public function test_sanitize_message_allows_only_intended_markup() {
		$method = new ReflectionMethod( Block_Validator::class, 'sanitize_message' );
		$method->setAccessible( true );

		$dirty = '<code><a href="https://example.org/block.json">block.json</a></code>: '
			. '<img src=x onerror=alert(document.domain)><script>alert(1)</script><a href="javascript:alert(1)">x</a>';
		$clean = $method->invoke( null, $dirty );

		$this->assertStringNotContainsString( '<img', $clean );
		$this->assertStringNotContainsString( '<script', $clean );
		$this->assertStringNotContainsString( 'javascript:', $clean );
		$this->assertStringContainsString( '<code>', $clean );
		$this->assertStringContainsString( '<a href="https://example.org/block.json">', $clean );
	}
}

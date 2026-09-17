<?php
/**
 * Tests for Validator::translate_code_to_message().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Readme\Validator;

/**
 * Covers the escaping of caller-supplied values quoted in validation messages.
 *
 * The `[readme-validator]` shortcode prints these messages without escaping them again, and the
 * plugin name they quote comes from a readme, so the escaping has to happen here.
 *
 * @group readme
 */
class Readme_Validator_Message_Test extends TestCase {

	/**
	 * The trademark message quotes the plugin name without leaving markup in it.
	 *
	 * @dataProvider trademark_context_provider
	 *
	 * @param string $context  The plugin name supplied by the caller.
	 * @param string $expected The value the message is expected to quote.
	 * @return void
	 */
	public function test_trademark_message_escapes_the_plugin_name( string $context, string $expected ): void {
		$message = Validator::instance()->translate_code_to_message(
			'trademarked_name',
			array(
				'trademark' => array( 'wordpress' ),
				'context'   => $context,
			)
		);

		preg_match( '!<code>(.*?)</code>!', $message, $matches );

		$this->assertSame( $expected, $matches[1] ?? '' );
	}

	/**
	 * Supplies plugin names and the value each should be quoted as.
	 *
	 * Callers differ: the readme parser escapes the name before it reaches this method, while the
	 * plugin upload handler passes the raw `Plugin Name` header straight through. Neither may end
	 * up double-escaped or unescaped.
	 *
	 * @return array<string, array<int, string>>
	 */
	public static function trademark_context_provider(): array {
		return array(
			// Format: name supplied, value expected in the message.
			'plain'              => array( 'WordPress Toolkit', 'WordPress Toolkit' ),
			'pre-escaped entity' => array( 'WordPress &amp; Friends', 'WordPress &amp; Friends' ),
			'pre-escaped quote'  => array( 'WordPress&#039;s Helper', 'WordPress&#039;s Helper' ),
			'raw ampersand'      => array( 'WordPress & Friends', 'WordPress &amp; Friends' ),
			'raw script tag'     => array( 'WordPress <script>alert(1)</script>', 'WordPress &lt;script&gt;alert(1)&lt;/script&gt;' ),
			'raw event handler'  => array( 'WordPress <img src=x onerror=alert(1)>', 'WordPress &lt;img src=x onerror=alert(1)&gt;' ),
			'raw double quote'   => array( 'WordPress" onmouseover="x', 'WordPress&quot; onmouseover=&quot;x' ),
		);
	}
}

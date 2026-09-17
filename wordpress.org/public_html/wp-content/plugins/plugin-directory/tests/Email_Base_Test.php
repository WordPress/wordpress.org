<?php
/**
 * Tests for Email\Base::plugin_title().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Email\Plugin_Approved;

/**
 * @group email
 */
class Email_Base_Test extends TestCase {

	/**
	 * Invoke Base::plugin_title() against a fake plugin post, bypassing the
	 * real constructor (which loads plugins from the DB).
	 */
	private function plugin_title( string $post_title ): string {
		// Plugin_Approved is a concrete Base subclass. Any subclass works since
		// plugin_title() lives on Base itself.
		$reflection = new ReflectionClass( Plugin_Approved::class );
		$email      = $reflection->newInstanceWithoutConstructor();

		$plugin_prop = $reflection->getProperty( 'plugin' );
		$plugin_prop->setAccessible( true );
		$plugin_prop->setValue( $email, (object) [ 'post_title' => $post_title ] );

		$method = $reflection->getMethod( 'plugin_title' );
		$method->setAccessible( true );

		return $method->invoke( $email );
	}

	public function test_plain_title_is_unchanged() {
		$this->assertSame( 'My Plugin', $this->plugin_title( 'My Plugin' ) );
	}

	public function test_ampersand_entity() {
		$this->assertSame( 'JLPoints&Rewards', $this->plugin_title( 'JLPoints&amp;Rewards' ) );
	}

	public function test_registered_trademark_entity() {
		$this->assertSame( 'Subscription DNA®', $this->plugin_title( 'Subscription DNA&reg;' ) );
	}

	public function test_trademark_entity() {
		$this->assertSame( 'Eventify™ - Simple Events', $this->plugin_title( 'Eventify&trade; - Simple Events' ) );
	}

	public function test_copyright_entity() {
		$this->assertSame( '©Feed', $this->plugin_title( '&copy;Feed' ) );
	}

	public function test_accented_character_entities() {
		$this->assertSame( 'Anonimação CTDO', $this->plugin_title( 'Anonima&ccedil;&atilde;o CTDO' ) );
	}

	public function test_numeric_entities() {
		$this->assertSame(
			'★★★ FLASH ROTATOR GALLERY ★★★',
			$this->plugin_title( '&#9733;&#9733;&#9733; FLASH ROTATOR GALLERY &#9733;&#9733;&#9733;' )
		);
	}

	public function test_hex_numeric_entity() {
		$this->assertSame(
			'🏦 Exchs & Currency Converter',
			$this->plugin_title( '&#x1F3E6; Exchs &amp; Currency Converter' )
		);
	}

	public function test_quote_entities_are_decoded() {
		$this->assertSame(
			"Joe's \"Best\" Plugin",
			$this->plugin_title( 'Joe&#039;s &quot;Best&quot; Plugin' )
		);
	}

	public function test_html5_only_entity() {
		// &apos; is HTML5 only; ENT_HTML5 flag is required to decode it.
		$this->assertSame( "Poppy's videos", $this->plugin_title( 'Poppy&apos;s videos' ) );
	}

	public function test_non_breaking_space() {
		$this->assertSame(
			"Filter Everything\u{00A0}— WordPress Filters",
			$this->plugin_title( 'Filter Everything&nbsp;— WordPress Filters' )
		);
	}

	public function test_degree_entity() {
		$this->assertSame(
			'feuerball3D - 360° animations',
			$this->plugin_title( 'feuerball3D - 360&deg; animations' )
		);
	}

	public function test_already_decoded_utf8_passes_through() {
		$this->assertSame(
			'ReFlex Gallery » WordPress Photo Gallery',
			$this->plugin_title( 'ReFlex Gallery » WordPress Photo Gallery' )
		);
	}

	public function test_empty_title() {
		$this->assertSame( '', $this->plugin_title( '' ) );
	}
}

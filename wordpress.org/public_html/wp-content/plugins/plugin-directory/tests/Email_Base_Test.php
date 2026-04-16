<?php
/**
 * Tests for Email\Base::plugin_title().
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Email\Base;

/**
 * @group email
 */
class Email_Base_Test extends TestCase {

	/**
	 * Build a Base subclass instance with a fake plugin post, bypassing the
	 * real constructor (which loads plugins from the DB).
	 */
	private function make_email( string $post_title ) {
		// Anonymous subclass to expose the protected plugin_title() method.
		$class = new class() extends Base {
			public function subject() {}
			public function body() {}
			public function expose_plugin_title() {
				return $this->plugin_title();
			}
		};

		$reflection = new ReflectionClass( $class );
		$email      = $reflection->newInstanceWithoutConstructor();

		$plugin_prop = new ReflectionProperty( Base::class, 'plugin' );
		$plugin_prop->setAccessible( true );
		$plugin_prop->setValue( $email, (object) [ 'post_title' => $post_title ] );

		return $email;
	}

	public function test_plain_title_is_unchanged() {
		$email = $this->make_email( 'My Plugin' );

		$this->assertSame( 'My Plugin', $email->expose_plugin_title() );
	}

	public function test_ampersand_entity() {
		$email = $this->make_email( 'JLPoints&amp;Rewards' );

		$this->assertSame( 'JLPoints&Rewards', $email->expose_plugin_title() );
	}

	public function test_registered_trademark_entity() {
		$email = $this->make_email( 'Subscription DNA&reg;' );

		$this->assertSame( 'Subscription DNA®', $email->expose_plugin_title() );
	}

	public function test_trademark_entity() {
		$email = $this->make_email( 'Eventify&trade; - Simple Events' );

		$this->assertSame( 'Eventify™ - Simple Events', $email->expose_plugin_title() );
	}

	public function test_copyright_entity() {
		$email = $this->make_email( '&copy;Feed' );

		$this->assertSame( '©Feed', $email->expose_plugin_title() );
	}

	public function test_accented_character_entities() {
		$email = $this->make_email( 'Anonima&ccedil;&atilde;o CTDO' );

		$this->assertSame( 'Anonimação CTDO', $email->expose_plugin_title() );
	}

	public function test_numeric_entities() {
		$email = $this->make_email( '&#9733;&#9733;&#9733; FLASH ROTATOR GALLERY &#9733;&#9733;&#9733;' );

		$this->assertSame( '★★★ FLASH ROTATOR GALLERY ★★★', $email->expose_plugin_title() );
	}

	public function test_hex_numeric_entity() {
		$email = $this->make_email( '&#x1F3E6; Exchs &amp; Currency Converter' );

		$this->assertSame( '🏦 Exchs & Currency Converter', $email->expose_plugin_title() );
	}

	public function test_quote_entities_are_decoded() {
		$email = $this->make_email( 'Joe&#039;s &quot;Best&quot; Plugin' );

		$this->assertSame( "Joe's \"Best\" Plugin", $email->expose_plugin_title() );
	}

	public function test_html5_only_entity() {
		// &apos; is HTML5 only; ENT_HTML5 flag is required to decode it.
		$email = $this->make_email( 'Poppy&apos;s videos' );

		$this->assertSame( "Poppy's videos", $email->expose_plugin_title() );
	}

	public function test_non_breaking_space() {
		$email = $this->make_email( 'Filter Everything&nbsp;— WordPress Filters' );

		$this->assertSame( "Filter Everything\u{00A0}— WordPress Filters", $email->expose_plugin_title() );
	}

	public function test_degree_entity() {
		$email = $this->make_email( 'feuerball3D - 360&deg; animations' );

		$this->assertSame( 'feuerball3D - 360° animations', $email->expose_plugin_title() );
	}

	public function test_already_decoded_utf8_passes_through() {
		$email = $this->make_email( 'ReFlex Gallery » WordPress Photo Gallery' );

		$this->assertSame( 'ReFlex Gallery » WordPress Photo Gallery', $email->expose_plugin_title() );
	}

	public function test_empty_title() {
		$email = $this->make_email( '' );

		$this->assertSame( '', $email->expose_plugin_title() );
	}
}

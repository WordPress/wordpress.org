<?php
/**
 * Tests that auto-review results only reach the ticket for the theme they describe.
 *
 * The check this covers reads a `theme-{slug}` keyword off the ticket. Matching
 * the whole keyword rather than searching for the slug inside the string is what
 * keeps a slug that is a prefix of another theme's from matching that theme's
 * ticket, and the earlier `strpos()` form also treated a match at offset 0 as a
 * failure.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Theme_Directory\Rest_API\Auto_Review_Controller;

/**
 * Covers the ticket-to-theme match used before an auto-review comment is posted.
 *
 * @group themes-api
 */
class Auto_Review_Ticket_Match_Test extends TestCase {

	/**
	 * The controller under test.
	 *
	 * @var Auto_Review_Controller
	 */
	protected $controller;

	/**
	 * Loads the endpoint, which is only included on `rest_api_init`.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		if ( ! class_exists( Auto_Review_Controller::class ) ) {
			do_action( 'rest_api_init' );
		}
	}

	/**
	 * Builds the controller without its constructor, which registers REST routes.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->controller = ( new ReflectionClass( Auto_Review_Controller::class ) )
			->newInstanceWithoutConstructor();
	}

	/**
	 * Keyword strings paired with the slug each should or should not match.
	 *
	 * @return array
	 */
	public static function data_keywords() {
		return array(
			'keyword first in the list'     => array( 'theme-mente-clara', 'mente-clara', true ),
			'keyword later in the list'     => array( 'child-theme theme-foo', 'foo', true ),
			'comma separated'               => array( 'theme-foo, needs-screenshot', 'foo', true ),
			'comma without a space'         => array( 'needs-screenshot,theme-foo', 'foo', true ),
			'surrounding whitespace'        => array( '  theme-foo   child-theme  ', 'foo', true ),
			'mixed case keyword'            => array( 'Theme-Twenty-Four', 'twenty-four', true ),
			'mixed case slug'               => array( 'theme-twenty-four', 'Twenty-Four', true ),
			'slug is a prefix of another'   => array( 'theme-twentytwentyfour', 'twenty', false ),
			'slug longer than the keyword'  => array( 'theme-twenty', 'twentytwentyfour', false ),
			'a different theme'             => array( 'theme-bar', 'foo', false ),
			'parent keyword is not a match' => array( 'child-theme parent-foo', 'foo', false ),
			'no keywords'                   => array( '', 'foo', false ),
			'no slug'                       => array( 'theme-foo', '', false ),
		);
	}

	/**
	 * A ticket is the theme's only when it carries that theme's whole keyword.
	 *
	 * @dataProvider data_keywords
	 *
	 * @param string $keywords   The ticket's keywords.
	 * @param string $theme_slug The theme slug.
	 * @param bool   $expected   Whether the ticket should be treated as the theme's.
	 */
	public function test_ticket_is_for_theme( $keywords, $theme_slug, $expected ) {
		$this->assertSame(
			$expected,
			$this->controller->ticket_is_for_theme( $keywords, $theme_slug )
		);
	}
}

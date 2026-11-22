<?php
/**
 * Tests for block icons in the plugin Blocks section.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory\Tests;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * Tests that block.json SVG icons keep their shapes when rendered.
 *
 * @group plugin-directory
 */
#[Group( 'plugin-directory' )]
class Section_Blocks_Icon_Test extends TestCase {

	/**
	 * Renders the Blocks section for a single block with the given icon.
	 *
	 * @param string $icon The block icon markup.
	 * @return string The rendered section.
	 */
	private function render_section( string $icon ): string {
		$GLOBALS['section_slug']    = 'blocks';
		$GLOBALS['section_title']   = 'Blocks';
		$GLOBALS['section_content'] = array(
			(object) array(
				'name'  => 'test/table',
				'title' => 'Table',
				'icon'  => $icon,
			),
		);

		ob_start();
		try {
			include WP_CONTENT_DIR . '/themes/wporg-plugins-2024/template-parts/section-blocks.php';
		} finally {
			$output = ob_get_clean();
			unset( $GLOBALS['section_slug'], $GLOBALS['section_title'], $GLOBALS['section_content'] );
		}

		return $output;
	}

	/**
	 * Finds the first tag with the given name in the markup.
	 *
	 * @param string $html     The markup to search.
	 * @param string $tag_name The upper-case tag name.
	 * @return \WP_HTML_Tag_Processor|null The processor at that tag, or null.
	 */
	private function find_tag( string $html, string $tag_name ): ?\WP_HTML_Tag_Processor {
		$processor = new \WP_HTML_Tag_Processor( $html );

		return $processor->next_tag( $tag_name ) ? $processor : null;
	}

	/**
	 * SVG shape elements other than path survive sanitization.
	 */
	public function test_svg_shapes_are_kept(): void {
		$output = $this->render_section(
			"<svg xmlns='http://www.w3.org/2000/svg' width='24' height='24' fill='black' viewBox='0 0 24 24'>" .
			"<polygon points='0,0 24,0 24,24'/>" .
			"<circle cx='12' cy='12' r='4'/>" .
			"<rect x='1' y='1' width='4' height='4' rx='1'/>" .
			"<ellipse cx='6' cy='6' rx='2' ry='1'/>" .
			"<line x1='0' y1='0' x2='24' y2='24' stroke='black' stroke-width='2'/>" .
			"<polyline points='0,24 12,12 24,24' fill='none'/>" .
			"<path d='M0 0h24v24H0z'/>" .
			'</svg>'
		);

		$svg = $this->find_tag( $output, 'SVG' );
		$this->assertNotNull( $svg, 'The svg element is missing.' );
		$this->assertSame( 'black', $svg->get_attribute( 'fill' ) );
		$this->assertSame( 'false', $svg->get_attribute( 'focusable' ) );

		$expected = array(
			'POLYGON'  => array( 'points' => '0,0 24,0 24,24' ),
			'CIRCLE'   => array( 'r' => '4' ),
			'RECT'     => array(
				'width' => '4',
				'rx'    => '1',
			),
			'ELLIPSE'  => array(
				'rx' => '2',
				'ry' => '1',
			),
			'LINE'     => array(
				'x2'           => '24',
				'stroke'       => 'black',
				'stroke-width' => '2',
			),
			'POLYLINE' => array(
				'points' => '0,24 12,12 24,24',
				'fill'   => 'none',
			),
			'PATH'     => array( 'd' => 'M0 0h24v24H0z' ),
		);

		foreach ( $expected as $tag_name => $attributes ) {
			$tag = $this->find_tag( $output, $tag_name );
			$this->assertNotNull( $tag, "The {$tag_name} element is missing." );

			foreach ( $attributes as $name => $value ) {
				$this->assertSame( $value, $tag->get_attribute( $name ), "{$tag_name} lost its {$name} attribute." );
			}
		}
	}

	/**
	 * Shapes inside clip paths stay inside their definitions, and the clip-path reference is removed.
	 */
	public function test_clip_path_shapes_stay_in_definitions(): void {
		$output = $this->render_section(
			"<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'>" .
			"<g clip-path='url(#clip)'><path d='M4 4h16v16H4z'/></g>" .
			"<defs><clipPath id='clip'><rect width='24' height='24' fill='white'/></clipPath></defs>" .
			'</svg>'
		);

		$this->assertMatchesRegularExpression( '#<defs><clipPath><rect[^>]*/></clipPath></defs>#i', $output );
		$group = $this->find_tag( $output, 'G' );
		$this->assertNotNull( $group, 'The g element is missing.' );
		$this->assertNull( $group->get_attribute( 'clip-path' ) );
	}

	/**
	 * Scripts and event handlers are still removed.
	 */
	public function test_scripts_and_handlers_are_removed(): void {
		$output = $this->render_section(
			"<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24'><circle cx='12' cy='12' r='4' onclick='alert(1)'/><script>alert(2)</script></svg>"
		);

		$this->assertStringContainsString( '<circle', $output );
		$this->assertStringNotContainsString( 'onclick', $output );
		$this->assertStringNotContainsString( '<script', $output );
	}
}

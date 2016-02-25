<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * The base SVG class does not provide functions for getting dimensions, so..
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Geopattern_SVG extends \RedeyeVentures\GeoPattern\SVG {

	/**
	 * @var string
	 */
	protected $viewbox;

	/**
	 * @return int
	 */
	function getWidth() {
		return $this->width;
	}

	/**
	 * @return int
	 */
	function getHeight() {
		return $this->height;
	}

	/**
	 * @return string
	 */
	protected function getSvgHeader() {
		if ( $this->viewbox ) {
			return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$this->width}\" height=\"{$this->height}\" viewbox=\"{$this->viewbox}\" preserveAspectRatio=\"none\">";
		} else {
			return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$this->width}\" height=\"{$this->height}\">";
		}
	}

	/**
	 * @param       $text
	 * @param       $x
	 * @param       $y
	 * @param       $text_anchor
	 * @param       $style
	 * @param array $args
	 *
	 * @return $this
	 */
	public function addText( $text, $x, $y, $text_anchor, $style, $args = array() ) {
		$element = new Plugin_Geopattern_SVGText( $text, $x, $y, $text_anchor, $style, $args );
		$this->svgString .= $element;

		return $this;
	}

	/**
	 * @param $svg
	 */
	public function addNested( $svg ) {
		if ( method_exists( $svg, 'getString' ) ) {
			$this->svgString .= $svg->getString();
		}
	}

	/**
	 * @param $x
	 * @param $y
	 * @param $w
	 * @param $h
	 */
	public function setViewBox( $x, $y, $w, $h ) {
		$this->viewbox = esc_attr( "$x $y $w $h" );
	}
}

<?php
namespace WordPressdotorg\Plugin_Directory;
use RedeyeVentures\GeoPattern\SVGElements\Base;

/**
 * Nor does it support text.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Geopattern_SVGText extends Base {

	/**
	 * @var string
	 */
	protected $tag = 'text';

	/**
	 * @var string
	 */
	protected $text;

	/**
	 * @param       $text
	 * @param       $x
	 * @param       $y
	 * @param       $text_anchor
	 * @param       $style
	 * @param array $args
	 */
	function __construct( $text, $x, $y, $text_anchor, $style, $args = array() ) {
		$this->elements = array(
			'x'           => $x,
			'y'           => $y,
			'text-anchor' => $text_anchor,
			'style'       => $style,
		);
		$this->text     = esc_html( $text );
		parent::__construct( $args );
	}

	/**
	 * @return string
	 */
	public function getString() {
		return "<{$this->tag}{$this->elementsToString()}{$this->argsToString()}>{$this->text}</{$this->tag}>";
	}
}

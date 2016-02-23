<?php

require __DIR__ . '/libs/geopattern-1.1.0/geopattern_loader.php';

class WPorg_Plugin_Geopattern extends \RedeyeVentures\GeoPattern\GeoPattern {

	var $slug; // Hashed to generate pattern
	var $text; // Text to be overlaid
	var $textcolor = 'black';

	function __construct($options=array()) {
		parent::__construct( $options );

		if ( isset( $options['text'] ) )
			$this->text = $options['text'];

		if ( isset( $options['textcolor'] ) )
			$this->textcolor = $options['textcolor'];

		// Replace the base SVG object with our own, so the dimensions are gettable.
		$this->svg = new WPorg_Plugin_Geopattern_SVG();
	}

	function setText( $text ) {
		$this->text = $text;
	}

	function setTextColor( $color ) {
		$this->textcolor = $color;
	}

	function generateText() {
		$size = min( $this->svg->getHeight(), $this->svg->getWidth() );
		$text_height = floor( $size * 0.8 ) . 'px';

		$this->svg->addText( $this->text, '50%', $text_height, 'middle', "font-family: Times New Roman, serif; font-size: {$text_height}; font-weight: normal; fill: {$this->textcolor};" );
	}

	public function toSVG( $width = 128, $height = 128 ) {
		$this->svg = new WPorg_Plugin_Geopattern_SVG();
		$this->generateBackground();

		// Work around a bug in 1.1.0: the hash-based pattern selection doesn't account for the size of the pattern array and can choose a null result.
		$this->setGenerator( $this->patterns[$this->hexVal(20, 1) % count( $this->patterns )] );

		$this->generatePattern();

		#if ( $this->svg->getWidth() < $width || $this->svg->getHeight() < $height ) {
			$this->svg->setViewBox( 0, 0, $this->svg->getWidth(), $this->svg->getHeight() );
		#}

		if ( $this->text ) {
			$inner = $this->svg;

			// Outer SVG, containing the text and nested inner SVG.
			// Needed because of aspect ratio problems with the background pattern. The outer is square, inner may be a different shape.
			$this->svg = new PluginSVG();
			$this->svg->setWidth( $width );
			$this->svg->setHeight( $height );
			$this->svg->addNested( $inner );
			$this->generateText();
		}

		return $this->svg->getString();

    }

}

// The base SVG class doesn't provide functions for getting dimensions, so..
class WPorg_Plugin_Geopattern_SVG extends \RedeyeVentures\GeoPattern\SVG {

	protected $viewbox;

	function getWidth() {
		return $this->width;
	}

	function getHeight() {
		return $this->height;
	}

    protected function getSvgHeader() {
		if ( $this->viewbox )
			return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$this->width}\" height=\"{$this->height}\" viewbox=\"{$this->viewbox}\" preserveAspectRatio=\"none\">";
		else
			return "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$this->width}\" height=\"{$this->height}\">";
    }

    public function addText( $text, $x, $y, $text_anchor, $style, $args=array() ) {
        $element = new WPorg_Plugin_Geopattern_SVGText($text, $x, $y, $text_anchor, $style, $args);
        $this->svgString .= $element;
        return $this;
	}

	public function addNested( $svg ) {
		if ( method_exists( $svg, 'getString' ) )
			$this->svgString .= $svg->getString();
	}

	public function setViewBox( $x, $y, $w, $h ) {
		$this->viewbox = esc_attr( "$x $y $w $h" );
	}

}

// Nor does it support text.
class WPorg_Plugin_Geopattern_SVGText extends \RedeyeVentures\GeoPattern\SVGElements\Base {
	protected $tag = 'text';
	protected $text;

	function __construct($text, $x, $y, $text_anchor, $style, $args=array()) {
		$this->elements = array( 
			'x' => $x,
			'y' => $y,
			'text-anchor' => $text_anchor,
			'style' => $style,
			);
		$this->text = esc_html( $text );
		parent::__construct($args);
	}

	public function getString() {
		return "<{$this->tag}{$this->elementsToString()}{$this->argsToString()}>{$this->text}</{$this->tag}>";
	}
}


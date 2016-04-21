<?php
namespace WordPressdotorg\Plugin_Directory;
use RedeyeVentures\GeoPattern\GeoPattern;

require __DIR__ . '/libs/geopattern-1.1.0/geopattern_loader.php';

/**
 * Generates Geopattern icons for Plugins.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Geopattern extends GeoPattern {

	/**
	 * Hashed to generate pattern.
	 *
	 * @var string
	 */
	public $slug;

	/**
	 * Text to be overlaid.
	 *
	 * @var string
	 */
	public $text;

	/**
	 * @var string
	 */
	public $textcolor = 'black';

	/**
	 * @param array $options
	 */
	function __construct( $options = array() ) {
		parent::__construct( $options );

		if ( isset( $options['text'] ) ) {
			$this->text = $options['text'];
		}

		if ( isset( $options['textcolor'] ) ) {
			$this->textcolor = $options['textcolor'];
		}

		// Replace the base SVG object with our own, so the dimensions are gettable.
		$this->svg = new Plugin_Geopattern_SVG();
	}

	/**
	 * @param $text
	 */
	function setText( $text ) {
		$this->text = $text;
	}

	/**
	 * @param $color
	 */
	function setTextColor( $color ) {
		$this->textcolor = $color;
	}

	/**
	 *
	 */
	function generateText() {
		$size        = min( $this->svg->getHeight(), $this->svg->getWidth() );
		$text_height = floor( $size * 0.8 ) . 'px';

		$this->svg->addText( $this->text, '50%', $text_height, 'middle', "font-family: Times New Roman, serif; font-size: {$text_height}; font-weight: normal; fill: {$this->textcolor};" );
	}

	/**
	 * @param int $width
	 * @param int $height
	 * @return string
	 */
	public function toSVG( $width = 128, $height = 128 ) {
		$this->svg = new Plugin_Geopattern_SVG();
		$this->generateBackground();

		/*
		 * Work around a bug in 1.1.0:
		 * The hash-based pattern selection doesn't account for the size of the
		 * pattern array and can choose a null result.
		 */
		$this->setGenerator( $this->patterns[ $this->hexVal( 20, 1 ) % count( $this->patterns ) ] );

		$this->generatePattern();

		#if ( $this->svg->getWidth() < $width || $this->svg->getHeight() < $height ) {
			$this->svg->setViewBox( 0, 0, $this->svg->getWidth(), $this->svg->getHeight() );
		#}

		if ( $this->text ) {
			$inner = $this->svg;

			/*
			 * Outer SVG, containing the text and nested inner SVG.
			 *
			 * Needed because of aspect ratio problems with the background pattern.
			 * The outer is square, inner may be a different shape.
			 */
			$this->svg = new PluginSVG();
			$this->svg->setWidth( $width );
			$this->svg->setHeight( $height );
			$this->svg->addNested( $inner );
			$this->generateText();
		}

		return $this->svg->getString();
	}
}

<?php
namespace WordPressdotorg\Post_Translation\Parsers;

class TextNode implements BlockParser {
	use DomUtils;

	public function to_strings( array $block ) : array {
		$dom   = $this->get_dom( serialize_block( $block ) );
		$xpath = new \DOMXPath( $dom );

		$strings = [];

		foreach ( $xpath->query( '//text()' ) as $text ) {
			if ( trim( $text->nodeValue ) ) {
				$strings[] = $text->nodeValue;
			}
		}

		return $strings;
	}

	public function replace_strings( array $block, array $replacements ) : array {
		$dom   = $this->get_dom( serialize_block( $block ) );
		$xpath = new \DOMXPath( $dom );

		foreach ( $xpath->query( '//text()' ) as $text ) {
			if ( trim( $text->nodeValue ) && isset( $replacements[ $text->nodeValue ] ) ) {
				$text->parentNode->replaceChild( $dom->createCDATASection( $replacements[ $text->nodeValue ] ), $text );
			}
		}

		return parse_blocks( $this->removeHtml( $dom->saveHTML() ) )[0] ?? [];
	}
}

<?php
namespace WordPressdotorg\Post_Translation\Parsers;

class Button implements BlockParser {
	use DomUtils;
	use SwapTags;
	use GetSetAttribute;
	use TextNodesXPath;

	public function to_strings( array $block ) : array {
		$strings = $this->get_attribute( 'placeholder', $block );

		$encoded_html = $this->encode_tags( $block['innerHTML'] );

		$dom   = $this->get_dom( $encoded_html );
		$xpath = new \DOMXPath( $dom );

		foreach ( $xpath->query( $this->text_nodes_xpath_query() ) as $text ) {
			if ( trim( $text->nodeValue ) ) {
				$strings[] = $this->decode_tags( $text->nodeValue );
			}
		}

		return $strings;
	}

	public function replace_strings( array $block, array $replacements ) : array {
		$this->set_attribute( 'placeholder', $block, $replacements );

		$encoded_html = $this->encode_tags( $block['innerHTML'] );

		$dom   = $this->get_dom( $encoded_html );
		$xpath = new \DOMXPath( $dom );

		foreach ( $xpath->query( $this->text_nodes_xpath_query() ) as $text ) {
			if ( trim( $text->nodeValue ) && isset( $replacements[ $this->decode_tags( $text->nodeValue ) ] ) ) {
				$text->parentNode->replaceChild(
					$dom->createCDATASection(
						$this->encode_tags(
							$replacements[ $this->decode_tags( $text->nodeValue ) ]
						)
					),
					$text
				);
			}
		}

		$decoded_html = $this->decode_tags( $this->removeHtml( $dom->saveHTML() ) );
		$block['innerHTML']    = $decoded_html;
		$block['innerContent'] = [ $decoded_html ];

		return $block;
	}
}

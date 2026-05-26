<?php
namespace WordPressdotorg\Post_Translation\Parsers;

/**
 * Fallback parser that extracts all text nodes from block HTML using DOMXPath.
 *
 * Used for blocks that don't have a specialized parser. Extracts all visible
 * text nodes and optionally alt/title attributes from images and links.
 */
class Basic_Text implements Block_Parser {
	use Dom_Utils;
	use Swap_Tags;
	use Replaces_Strings;

	public function to_strings( array $block ): array {
		$html = $block['innerHTML'] ?? '';

		if ( ! trim( $html ) ) {
			return [];
		}

		$strings = [];

		// Encode inline formatting tags so they become part of the text node.
		$encoded = $this->encode_tags( $html );
		$doc     = $this->load_html( $encoded );
		$xpath   = new \DOMXPath( $doc );

		// Get all text nodes that contain visible content.
		$text_nodes = $xpath->query( '//div[@id="wrap"]//text()[normalize-space()]' );
		foreach ( $text_nodes as $node ) {
			$text = trim( $this->decode_tags( $node->nodeValue ) ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode property.
			if ( '' !== $text ) {
				$strings[] = $text;
			}
		}

		// Get alt and title attributes.
		$attr_nodes = $xpath->query( '//div[@id="wrap"]//*[@alt or @title]' );
		foreach ( $attr_nodes as $node ) {
			foreach ( [ 'alt', 'title' ] as $attr ) {
				$value = trim( $node->getAttribute( $attr ) );
				if ( '' !== $value ) {
					$strings[] = $value;
				}
			}
		}

		return array_unique( $strings );
	}

	public function replace_strings( array $block, array $replacements ): array {
		return $this->apply_replacements( $block, $replacements );
	}
}

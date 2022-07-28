<?php
/**
 * Block Parser interface and traits to be used by individual block parsers.
 *
 * Each block parser needs to implement a to_strings and replace_strings method.
 * The traits are provided here as helper functions for specific tasks, such as
 * dealing with wrapping markup in html tags, getting and setting block attributes,
 * and encoding and decoding tags.
 *
 * @phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound
 */

namespace WordPressdotorg\Post_Translation\Parsers;

// A block transform is specific to a certain block type and contains
// the know-how of how to both extract and replace strings
interface BlockParser {
	public function to_strings( array $block ) : array;
	public function replace_strings( array $block, array $replacements ) : array;
}

// DomDocument::loadHTML with LIBXML_HTML_NOIMPLIED causes dom doc settings to format / strip whitespace from our html
// Add/remove html tags to avoid the need for noimplied html
trait DomUtils {
	// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
	private function addHtml( string $html ) : string {
		return "<html><head><meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\"></head><body>$html</body></html>";
	}

	private function removeHtml( string $html ) : string {
		return preg_replace(
			[
				'/^\s*<html><head><meta http-equiv="Content-Type" content="text\/html; charset=utf-8"><\/head><body>/sm',
				// $dom->saveHTML() can have a trailing newline after the closing </html>, match to the real end of the document.
				'/<\/body><\/html>\s*$/sm',
			],
			'',
			$html
		);
	}

	private function get_dom( string $html ) : \DOMDocument {
		$previous = libxml_use_internal_errors( true );
		$dom      = new \DomDocument();
		$dom->loadHTML( $this->addHtml( $html ), LIBXML_HTML_NODEFDTD | LIBXML_COMPACT );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );
		return $dom;
	}
	// phpcs:enable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
}

trait GetSetAttribute {
	private function get_attribute( string $attribute_name, array $block ) : array {
		if ( isset( $block['attrs'][ $attribute_name ] ) && is_string( $block['attrs'][ $attribute_name ] ) ) {
			return [ $block['attrs'][ $attribute_name ] ];
		}
		return [];
	}

	private function set_attribute( string $attribute_name, array &$block, array $replacements ) {
		if ( isset( $block['attrs'][ $attribute_name ] ) && is_string( $block['attrs'][ $attribute_name ] ) ) {
			if ( isset( $replacements[ $block['attrs'][ $attribute_name ] ] ) ) {
				$block['attrs'][ $attribute_name ] = $replacements[ $block['attrs'][ $attribute_name ] ];
			}
		}
	}
}

trait SwapTags {
	private $safe_tags = [
		'strong',
		'em',
	];

	private function encode_tags( string $raw_html ) : string {
		foreach ( $this->safe_tags as $tag ) {
			$raw_html = preg_replace(
				'#(<' . $tag . '([^>]*)>)(.*)(</' . $tag . '>)#',
				'{' . $tag . '$2' . '}$3' . '{/' . $tag . '}', // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found
				$raw_html
			);
		}
		return $raw_html;
	}

	private function decode_tags( string $encoded_html ) : string {
		foreach ( $this->safe_tags as $tag ) {
			$encoded_html = preg_replace(
				'#({' . $tag . '([^}]*)})(.*)({/' . $tag . '})#',
				'<' . $tag . '$2' . '>$3' . '</' . $tag . '>', // phpcs:ignore Generic.Strings.UnnecessaryStringConcat.Found
				$encoded_html
			);
		}
		return $encoded_html;
	}
}

trait TextNodesXPath {
	private $xpaths = [
		'//text()',   // Visible Text nodes.
		'//img/@alt', // Image alt="" text.
		'//*/@title', // title="" text.
	];

	protected function text_nodes_xpath_query() {
		return implode( ' | ', $this->xpaths );
	}
}

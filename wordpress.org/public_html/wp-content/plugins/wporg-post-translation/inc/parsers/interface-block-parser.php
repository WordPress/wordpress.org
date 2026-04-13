<?php
namespace WordPressdotorg\Post_Translation\Parsers;

/**
 * Interface for block-specific string parsers.
 */
interface Block_Parser {
	/**
	 * Extract translatable strings from a parsed block.
	 *
	 * @param array $block A parsed block array from parse_blocks().
	 * @return string[] Translatable strings found in this block.
	 */
	public function to_strings( array $block ): array;

	/**
	 * Replace strings in a parsed block with their translations.
	 *
	 * @param array $block        A parsed block array.
	 * @param array $replacements Map of original => translated strings.
	 * @return array The modified block array.
	 */
	public function replace_strings( array $block, array $replacements ): array;
}

/**
 * Trait for common DOM manipulation utilities.
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
trait Dom_Utils {
	/**
	 * Load HTML content into a DOMDocument, handling encoding.
	 */
	protected function load_html( string $html ): \DOMDocument {
		$doc = new \DOMDocument();
		// Wrap in a div to handle fragments, and force UTF-8 encoding.
		$doc->loadHTML(
			'<html><head><meta charset="UTF-8"></head><body><div id="wrap">' . $html . '</div></body></html>',
			LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR
		);

		return $doc;
	}

	/**
	 * Get the inner HTML of the wrapper div from a loaded DOMDocument.
	 */
	protected function get_inner_html( \DOMDocument $doc ): string {
		$wrap = $doc->getElementById( 'wrap' );
		if ( ! $wrap ) {
			return '';
		}

		$html = '';
		foreach ( $wrap->childNodes as $child ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DOMNode property.
			$html .= $doc->saveHTML( $child );
		}

		return $html;
	}
}

/**
 * Trait for temporarily encoding inline HTML tags to prevent DOM corruption.
 *
 * When extracting text from HTML that contains inline formatting tags like
 * <strong>, <em>, etc., DOM processing can corrupt these tags. This trait
 * provides methods to encode them before processing and decode them after.
 */
// phpcs:ignore Generic.Files.OneObjectStructurePerFile.MultipleFound
trait Swap_Tags {
	private static $safe_tags = [ 'strong', 'em', 'b', 'i', 'code', 'br', 'span', 'abbr', 'sub', 'sup', 'mark' ];

	/**
	 * Encode safe HTML tags to placeholders.
	 */
	protected function encode_tags( string $html ): string {
		foreach ( self::$safe_tags as $tag ) {
			$html = preg_replace(
				'#<(/?' . preg_quote( $tag, '#' ) . ')(\s[^>]*)?\s*/?>#i',
				'{{TAG:$1$2}}',
				$html
			);
		}

		return $html;
	}

	/**
	 * Decode tag placeholders back to HTML tags.
	 */
	protected function decode_tags( string $html ): string {
		return preg_replace( '#\{\{TAG:(/?[a-z]+[^}]*)\}\}#i', '<$1>', $html );
	}
}

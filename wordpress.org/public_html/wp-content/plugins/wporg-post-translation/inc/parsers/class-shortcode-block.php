<?php
namespace WordPressdotorg\Post_Translation\Parsers;

/**
 * Parser for core/shortcode blocks.
 *
 * Extracts the entire shortcode content as a single translatable string,
 * or extracts specific shortcode attributes if configured.
 */
class Shortcode_Block implements Block_Parser {
	use Replaces_Strings;

	public function to_strings( array $block ): array {
		$html = trim( $block['innerHTML'] ?? '' );

		if ( '' === $html ) {
			return [];
		}

		// Extract the shortcode content as a single string.
		return [ $html ];
	}

	public function replace_strings( array $block, array $replacements ): array {
		return $this->apply_replacements( $block, $replacements );
	}
}

<?php
namespace WordPressdotorg\Post_Translation\Parsers;

/**
 * Parser for core/shortcode blocks.
 *
 * Extracts the entire shortcode content as a single translatable string,
 * or extracts specific shortcode attributes if configured.
 */
class Shortcode_Block implements Block_Parser {
	public function to_strings( array $block ): array {
		$html = trim( $block['innerHTML'] ?? '' );

		if ( '' === $html ) {
			return [];
		}

		// Extract the shortcode content as a single string.
		return [ $html ];
	}

	public function replace_strings( array $block, array $replacements ): array {
		foreach ( $block['innerContent'] as &$content ) {
			if ( null === $content ) {
				continue;
			}

			foreach ( $replacements as $original => $translated ) {
				if ( $original === $translated || '' === $original ) {
					continue;
				}

				$content = str_replace( $original, $translated, $content );
			}
		}

		if ( ! empty( $block['innerHTML'] ) ) {
			foreach ( $replacements as $original => $translated ) {
				if ( $original === $translated || '' === $original ) {
					continue;
				}

				$block['innerHTML'] = str_replace( $original, $translated, $block['innerHTML'] );
			}
		}

		return $block;
	}
}

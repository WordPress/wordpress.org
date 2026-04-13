<?php
namespace WordPressdotorg\Post_Translation\Parsers;

/**
 * Parses translatable strings from specific HTML tags within block content.
 *
 * Extracts text content from specified HTML tags, and optionally extracts
 * specific attribute values (like alt, title, href).
 */
class HTML_Parser implements Block_Parser {
	use Swap_Tags;

	protected $tags;
	protected $attributes;

	/**
	 * @param string|string[] $tags       HTML tag name(s) to extract text from.
	 * @param string[]        $attributes HTML attribute names to extract values from.
	 */
	public function __construct( $tags, array $attributes = [] ) {
		$this->tags       = (array) $tags;
		$this->attributes = $attributes;
	}

	public function to_strings( array $block ): array {
		$strings = [];
		$html    = $this->get_block_html( $block );

		if ( ! $html ) {
			return $strings;
		}

		foreach ( $this->tags as $tag ) {
			$pattern = $this->tag_pattern( $tag );

			if ( preg_match_all( $pattern, $html, $matches ) ) {
				foreach ( $matches['content'] as $content ) {
					$content = trim( $content );
					if ( '' !== $content ) {
						$strings[] = $content;
					}
				}
			}

		}

		// Extract attribute values from any tag in the block HTML.
		if ( $this->attributes ) {
			foreach ( $this->attributes as $attr ) {
				if ( preg_match_all( '/' . preg_quote( $attr, '/' ) . '=["\']([^"\']+)["\']/i', $html, $attr_matches ) ) {
					foreach ( $attr_matches[1] as $value ) {
						$value = trim( $value );
						if ( '' !== $value ) {
							$strings[] = $value;
						}
					}
				}
			}
		}

		return array_unique( $strings );
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

				// Replace within tag content.
				$content = str_replace( $original, $translated, $content );
			}
		}

		// Also replace in innerHTML if present.
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

	/**
	 * Get the combined HTML content of a block.
	 */
	protected function get_block_html( array $block ): string {
		if ( ! empty( $block['innerHTML'] ) ) {
			return $block['innerHTML'];
		}

		return implode( '', array_filter( $block['innerContent'], 'is_string' ) );
	}

	/**
	 * Build a regex pattern to match content within a tag.
	 */
	protected function tag_pattern( string $tag ): string {
		return '#<' . $tag . '(?:\s[^>]*)?>(?P<content>.*?)</' . $tag . '>#is';
	}

}

/**
 * HTML parser that supports regex patterns for tag names.
 *
 * For example, '/h[1-6]/' matches any heading tag.
 */
class HTML_Regex_Parser extends HTML_Parser {
	protected function tag_pattern( string $tag ): string {
		// Strip delimiters from the regex pattern.
		$tag_pattern = trim( $tag, '/' );

		return '#<(?P<tag>' . $tag_pattern . ')(?:\s[^>]*)?>(?P<content>.*?)</(?P=tag)>#is';
	}
}

<?php
namespace WordPressdotorg\Post_Translation\Parsers;

class HTMLParser implements BlockParser {
	use GetSetAttribute;

	public $tags = [];
	public $attributes = [];

	public function __construct( $tags = array(), $attributes = array() ) {
		$this->tags       = (array) $tags;
		$this->attributes = (array) $attributes;
	}

	public function to_strings( array $block ) : array {
		$strings = $this->get_attribute( 'placeholder', $block );

		foreach ( $this->tags as $tag ) {
			$tag = $this->escape_tag( $tag, '#' );

			if ( preg_match_all( "#<{$tag}[^>]*>\s*(?P<string>.+?)\s*</{$tag}>#is", $block['innerHTML'], $matches ) ) {
				$strings = array_merge( $strings, $matches['string'] );
			}
		}

		foreach ( $this->attributes as $attr ) {
			$attr = $this->escape_attr( $attr, '#' );

			if (
				str_contains( $block['innerHTML'], "='" ) &&
				preg_match_all( "#{$attr}='(?P<string>[^']+?)'#is", $block['innerHTML'], $matches )
			) {
				$strings = array_merge( $strings, $matches['string'] );
			}

			if (
				str_contains( $block['innerHTML'], '="' ) &&
				preg_match_all( "#{$attr}=\"(?P<string>[^\"]+?)\"#is", $block['innerHTML'], $matches )
			) {
				$strings = array_merge( $strings, $matches['string'] );
			}
		}

		return $strings;
	}

	// todo: this needs a fix to properly rebuild innerContent - see ParagraphParserTest
	public function replace_strings( array $block, array $replacements ) : array {
		$this->set_attribute( 'placeholder', $block, $replacements );

		$html = $block['innerHTML'];

		foreach ( $this->to_strings( $block ) as $original ) {
			if ( empty( $original ) || ! isset( $replacements[ $original ] ) ) {
				continue;
			}

			// TODO: Potentially this should be more specific for tags/attribute replacements as needed.
			$regex = '#([>"\'])\s*' . preg_quote( $original, '#' ) . '\s*([\'"<])#s';
			$html  = preg_replace( $regex, '$1' . addcslashes( $replacements[ $original ], '\\$' ) . '$2', $html );
		}

		$block['innerHTML']    = $html;
		$block['innerContent'] = [ $html ];

		return $block;
	}

	/**
	 * Escape a tag/attribute to use in a regex.
	 */
	protected function escape_tag( $string, $delim ) {
		return $this->escape( $string, $delim );
	}
	protected function escape_attr( $string, $delim ) {
		return $this->escape( $string, $delim );
	}
	protected function escape( $string, $delim ) {
		return preg_quote( $string, $delim );
	}
}

class HTMLRegexParser extends HTMLParser {
	/**
	 * Maybe escape a string for a regex match, unless it looks like regex (ie. /..../) then use as-is.
	 */
	protected function escape_tag( $string, $delim ) {
		if ( str_starts_with( $string, '/' ) && str_ends_with( $string, '/' ) ) {
			return trim( $string, '/' );
		}

		return parent::escape_tag( $string, $delim );
	}
}

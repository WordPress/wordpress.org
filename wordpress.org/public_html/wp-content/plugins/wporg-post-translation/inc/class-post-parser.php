<?php
namespace WordPressdotorg\Post_Translation;

use WordPressdotorg\Post_Translation\Parsers\{
	Block_Parser,
	HTML_Parser,
	HTML_Regex_Parser,
	Basic_Text,
	Noop,
	Shortcode_Block,
};

require_once __DIR__ . '/parsers/interface-block-parser.php';
require_once __DIR__ . '/parsers/class-html-parser.php';
require_once __DIR__ . '/parsers/class-basic-text.php';
require_once __DIR__ . '/parsers/class-noop.php';
require_once __DIR__ . '/parsers/class-shortcode-block.php';

/**
 * Extracts translatable strings from WordPress block content and replaces
 * them with translations.
 */
class Post_Parser {
	protected $parsers = [];
	protected $fallback;

	public function __construct() {
		$noop = new Noop();

		$this->fallback = new Basic_Text();
		$this->parsers  = [
			'core/paragraph' => new HTML_Parser( 'p' ),
			'core/heading'   => new HTML_Regex_Parser( '/h[1-6]/' ),
			'core/image'     => new HTML_Parser( 'figcaption', [ 'alt', 'title' ] ),
			'core/list'      => new HTML_Parser( 'li' ),
			'core/list-item' => new HTML_Parser( 'li' ),
			'core/quote'     => new HTML_Parser( [ 'p', 'cite' ] ),
			'core/button'    => new HTML_Parser( 'a', [ 'href', 'title' ] ),
			'core/shortcode' => new Shortcode_Block(),
			'core/spacer'    => $noop,
			'core/separator' => $noop,
			'core/column'    => $noop,
			'core/columns'   => $noop,
			'core/group'     => $noop,
			'core/buttons'   => $noop,
		];

		$this->parsers = apply_filters( 'post_translation_block_parsers', $this->parsers );
	}

	/**
	 * Extract all translatable strings from a post.
	 *
	 * @param \WP_Post $post The post to extract strings from.
	 * @return string[] Unique translatable strings.
	 */
	public static function post_to_strings( $post ) {
		$post = get_post( $post );

		if ( ! $post ) {
			return [];
		}

		$self    = new self();
		$strings = $self->extract_strings( $post->post_content );

		if ( $post->post_title ) {
			$strings[] = $post->post_title;
		}

		if ( $post->post_excerpt ) {
			$strings[] = $post->post_excerpt;
		}

		$meta_keys = apply_filters( 'post_translation_meta_keys', [] );
		foreach ( $meta_keys as $meta_key ) {
			// All rows of the meta key; the frontend translates each string row.
			foreach ( get_post_meta( $post->ID, $meta_key ) as $value ) {
				if ( $value && is_string( $value ) ) {
					$strings[] = $value;
				}
			}
		}

		return array_unique( array_filter( $strings ) );
	}

	/**
	 * Extract translatable strings from block content.
	 *
	 * @param string $content Block content (serialized blocks).
	 * @return string[] Unique translatable strings.
	 */
	public function extract_strings( string $content ): array {
		$blocks  = parse_blocks( $content );
		$strings = [];

		foreach ( $blocks as $block ) {
			$strings = array_merge( $strings, $this->extract_from_block( $block ) );
		}

		return array_unique( $strings );
	}

	/**
	 * Replace strings in block content with translations.
	 *
	 * @param string   $content      Block content (serialized blocks).
	 * @param callable $translate_fn Callback: fn( string $original ) => string $translated.
	 * @return string|false Translated content, or false if no translations were applied.
	 */
	public function translate_content( string $content, callable $translate_fn ) {
		$strings      = $this->extract_strings( $content );
		$replacements = [];
		$has_changes  = false;

		foreach ( $strings as $string ) {
			$translated              = $translate_fn( $string );
			$replacements[ $string ] = $translated;

			if ( $string !== $translated ) {
				$has_changes = true;
			}
		}

		if ( ! $has_changes ) {
			return false;
		}

		return $this->replace_in_content( $content, $replacements );
	}

	/**
	 * Replace strings in block content using a replacement map.
	 *
	 * @param string $content      Block content.
	 * @param array  $replacements Map of original => translated strings.
	 * @return string The translated block content.
	 */
	public function replace_in_content( string $content, array $replacements ): string {
		// Sanitize replacements.
		$sanitized = [];
		foreach ( $replacements as $original => $translated ) {
			$sanitized[ $original ] = wp_kses_post( $translated );
		}

		$blocks = parse_blocks( $content );

		foreach ( $blocks as &$block ) {
			$this->replace_in_block( $block, $sanitized );
		}

		return $this->decode_unicode_characters( serialize_blocks( $blocks ) );
	}

	/**
	 * Recursively extract strings from a block and its innerBlocks.
	 */
	protected function extract_from_block( array $block ): array {
		$parser  = $this->get_parser( $block['blockName'] );
		$strings = $parser->to_strings( $block );

		foreach ( $block['innerBlocks'] as $inner ) {
			$strings = array_merge( $strings, $this->extract_from_block( $inner ) );
		}

		return $strings;
	}

	/**
	 * Recursively replace strings in a block and its innerBlocks.
	 */
	protected function replace_in_block( array &$block, array $replacements ): void {
		$parser = $this->get_parser( $block['blockName'] );
		$block  = $parser->replace_strings( $block, $replacements );

		foreach ( $block['innerBlocks'] as &$inner ) {
			$this->replace_in_block( $inner, $replacements );
		}
	}

	/**
	 * Get the parser for a given block type.
	 */
	public function get_parser( ?string $block_name ): Block_Parser {
		if ( $block_name && isset( $this->parsers[ $block_name ] ) ) {
			return $this->parsers[ $block_name ];
		}

		return $this->fallback;
	}

	/**
	 * Decode unicode escape sequences in serialized block output.
	 *
	 * Serialize_blocks() encodes unicode characters in attributes as \uXXXX
	 * sequences. This decodes them back, except for characters that would
	 * interfere with block comment delimiters.
	 *
	 * @param string $string Serialized block content.
	 * @return string Content with decoded unicode characters.
	 */
	protected function decode_unicode_characters( string $string ): string {
		$excluded = [
			'\\u002d\\u002d', // '--'
			'\\u003c',        // '<'
			'\\u003e',        // '>'
			'\\u0026',        // '&'
			'\\u0022',        // '"'
		];

		return preg_replace_callback(
			'#(\\\\u[a-fA-F0-9]{4})+#',
			function ( $matches ) use ( $excluded ) {
				foreach ( $excluded as $char ) {
					if ( false !== mb_stripos( $matches[0], $char ) ) {
						return $matches[0];
					}
				}

				return json_decode( '"' . $matches[0] . '"' );
			},
			$string
		);
	}
}

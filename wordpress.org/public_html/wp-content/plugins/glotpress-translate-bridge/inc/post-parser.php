<?php
//phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- DomDocument/DOMXPath returns classes that use camelCasing

namespace WordPressdotorg\Post_Translation;
use WP_Post;

require_once __DIR__ . '/parsers/BlockParser.php';
require_once __DIR__ . '/parsers/HTMLParser.php';
require_once __DIR__ . '/parsers/BasicText.php';
require_once __DIR__ . '/parsers/Button.php';
require_once __DIR__ . '/parsers/Noop.php';
require_once __DIR__ . '/parsers/ShortcodeBlock.php';
require_once __DIR__ . '/parsers/TextNode.php'; // Unused

class Post_Parser {
	public $content;
	public $parsers = [];
	public $fallback;

	public function __construct( string $content = '' ) {
		$this->content  = $content;
		$this->fallback = new Parsers\BasicText();
		$this->parsers  = [
			// Blocks that have custom parsers.
			'core/paragraph'   => new Parsers\HTMLParser( 'p' ),
			'core/image'       => new Parsers\HTMLParser( 'figcaption', [ 'alt', 'title' ] ),
			'core/list'        => new Parsers\HTMLParser( 'li' ),
			'core/quote'       => new Parsers\HTMLParser( [ 'p', 'cite' ] ),
			'core/heading'     => new Parsers\HTMLRegexParser( '/h[1-6]/' ),

			//'core/button'      => new Parsers\Button(),
			//'core/buttons'     => new Parsers\BasicText(),
			'core/button'      => new Parsers\HTMLParser( 'a', [ 'href', 'title' ] ),

			// Generic shortcode handler..
			'core/shortcode'   => new Parsers\ShortcodeBlock(),

			'core/spacer'      => new Parsers\Noop(),
			// These contain other blocks to be parsed.
			'core/column'      => new Parsers\Noop(),
			'core/columns'     => new Parsers\Noop(),
			'core/group'       => new Parsers\Noop(),

			// Common core blocks that use the default parser.
			'core/media-text'  => new Parsers\BasicText(),
			'core/social-link' => new Parsers\BasicText(),
		];
	}

	public static function post_to_strings( $post ) {
		// TODO: Detect post using a block template, pull strings from there.
		$self    = new self( $post->post_content );
		$strings = $self->to_strings();

		if ( $post->post_title ) {
			$strings[] = $post->post_title;
		}
		if ( $post->post_excerpt ) {
			$strings[] = $post->post_excerpt;
		}

		$post_meta_to_include = apply_filters( 'translatable_post_meta', [] );
		foreach ( $post_meta_to_include as $meta_key ) {
			$strings[] = get_post_meta( $post->ID, $meta_key, true );
		}

		return $strings;
	}

	public static function translate_post( $post, callable $callback_translate ) {
		$post->post_content = self::translate_blocks( $post->post_content, $callback_translate ) ?: $post->post_content;
		$post->post_title   = $callback_translate( $post->post_title ) ?: $post->post_title;
		$post->post_excerpt = $callback_translate( $post->post_excerpt ) ?: $post->post_excerpt;

		return $post;
	}

	public static function translate_blocks( string $content, callable $callback_translate ) /*: bool|string*/ {
		$self         = new self( $content );

		$translations = [];
		$translated   = false;
		$strings      = $self->to_strings();

//		var_dump( $strings );
		foreach ( $strings as $string ) {
			$translations[ $string ] = $callback_translate( $string );
	
			$translated = $translated || ( $string !== $translations[ $string ] );
		}

		// Are there any translations?
		if ( ! $translated ) {
			return false;
		}

		return $self->replace_strings_with_kses( $translations );
	}

	public static function translate_block( string $content, $block, callable $callback_translate ) /* :bool|string */ {
		$self    = new self();
		$parser  = $self->parsers[ $block['blockName'] ] ?? $self->fallback;
		$strings = $parser->to_strings( $block ); // does not do innerBlocks, intentionally.

		if ( ! $strings ) {
			return $content;
		}

		$replacements = [];
		foreach ( $strings as $string ) {
			$replacements[ $string ] = $callback_translate( $string ) ?: $string;
		}

		$block = $parser->replace_strings( $block, $replacements );

		return $block['innerContent'][0] ?: $content;
	}

	public function block_parser_to_strings( array $block ) : array {
		$parser = $this->parsers[ $block['blockName'] ] ?? $this->fallback;

		$strings = $parser->to_strings( $block );

		foreach ( $block['innerBlocks'] as $inner_block ) {
			$strings = array_merge( $strings, $this->block_parser_to_strings( $inner_block ) );
		}

		return array_unique( $strings );
	}

	public function block_parser_replace_strings( array &$block, array $replacements ) : array {
		$parser = $this->parsers[ $block['blockName'] ] ?? $this->fallback;
		$block = $parser->replace_strings( $block, $replacements );

		foreach ( $block['innerBlocks'] as &$inner_block ) {
			$inner_block = $this->block_parser_replace_strings( $inner_block, $replacements );
		}

		return $block;
	}

	public function to_strings() : array {
		$strings = [];

		$blocks = parse_blocks( $this->content );
		//var_Dump( $blocks, $this->content ); die();
		foreach ( $blocks as $block ) {
			$strings = array_merge( $strings, $this->block_parser_to_strings( $block ) );
		}

		return array_unique( $strings );
	}

	public function replace_strings_with_kses( array $replacements ) : string {
		// Sanitize replacement strings before injecting them into blocks and block attributes.
		$sanitized_replacements = $replacements;
		foreach ( $sanitized_replacements as &$replacement ) {
			$replacement = wp_kses_post( $replacement );
		}
		return $this->replace_strings( $sanitized_replacements );
	}

	public function replace_strings( array $replacements ) : string {
		$translated = $this->content;

		$blocks = parse_blocks( $translated );
		foreach ( $blocks as &$block ) {
			$block = $this->block_parser_replace_strings( $block, $replacements );
		}

		// If we pass `serialize_blocks` a block that includes unicode characters in the
		// attributes, these attributes will be encoded with a unicode escape character, e.g.
		// "subscribePlaceholder":"😀" becomes "subscribePlaceholder":"\ud83d\ude00".
		// After we get the serialized blocks back from `serialize_blocks` we need to convert these
		// characters back to their unicode form so that we don't break blocks in the editor.
		$translated = $this->decode_unicode_characters( serialize_blocks( $blocks ) );

		return $translated;
	}

	/**
	 * Decode a string containing unicode escape sequences.
	 * Excludes decoding characters not allowed within block attributes.
	 *
	 * @param string $string A string containing serialized blocks.
	 * @return string A string containing decoded unicode characters.
	 */
	public function decode_unicode_characters( string $string ): string {

		// In WordPress core, `serialize_block_attributes` intentionally leaves some characters
		// in the block attributes encoded in their unicode form. These are characters that would
		// interfere with characters in block comments e.g. consider potential values entered
		// in the placeholder attribute: <!-- wp:paragraph {"placeholder":"dangerous characters go here"} -->
		// Reference: https://github.com/WordPress/WordPress/blob/HEAD/wp-includes/blocks.php#L367

		$excluded_characters = [
			'\\u002d\\u002d', // '--'
			'\\u003c',        // '<'
			'\\u003e',        // '>'
			'\\u0026',        // '&'
			'\\u0022',        // '"'
		];

		// Match any uninterrupted sequence of \u escaped unicode characters.
		$decoded_string = preg_replace_callback(
			'#(\\\\u[a-zA-Z0-9]{4})+#',
			function ( $matches ) use ( $excluded_characters ) {
				// If we encounter any excluded characters, don't decode this match.
				foreach ( $excluded_characters as $excluded_character ) {
					if ( false !== mb_stripos( $matches[0], $excluded_character ) ) {
						return $matches[0];
					}
				}
				// If we didn't encounter excluded characters, use json_decode to do the heavy lifting.
				return json_decode( '"' . $matches[0] . '"' );
			},
			$string
		);

		return $decoded_string;
	}
}

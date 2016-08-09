<?php
/**
 * Code Reference formatting.
 *
 * @package wporg-developer
 */

/**
 * Class to handle content formatting.
 */
class DevHub_Formatting {

	/**
	 * Initializer
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Handles adding/removing hooks to perform formatting as needed.
	 */
	public static function do_init() {
		add_filter( 'the_excerpt', array( __CLASS__, 'lowercase_P_dangit_just_once' ) );
		add_filter( 'the_content', array( __CLASS__, 'make_doclink_clickable' ), 10, 5 );

		add_filter( 'the_excerpt', array( __CLASS__, 'remove_inline_internal' ) );
		add_filter( 'the_content', array( __CLASS__, 'remove_inline_internal' ) );

		add_filter( 'the_excerpt', array( __CLASS__, 'autolink_references' ), 11 );
		add_filter( 'the_content', array( __CLASS__, 'autolink_references' ), 11 );
		add_filter( 'devhub-format-description', array( __CLASS__, 'autolink_references' ) );
		add_filter( 'devhub-parameter-type', array( __CLASS__, 'autolink_references' ) );

		add_filter( 'devhub-format-description', array( __CLASS__, 'fix_param_hash_formatting' ), 9 );
		add_action( 'the_content', array( __CLASS__, 'fix_unintended_markdown' ) );

		add_filter( 'devhub-function-return-type', array( __CLASS__, 'autolink_references' ) );
	}

	/**
	 * Allows for "Wordpress" just for the excerpt value of the capital_P_dangit function.
	 *
	 * WordPress.org has a global output buffer that runs capital_P_dangit() over displayed
	 * content. For this one field of this one post, circumvent that function to
	 * to show the lowercase P.
	 *
	 * @param  string $excerpt The post excerpt.
	 * @return string
	 */
	public static function lowercase_P_dangit_just_once( $excerpt ) {
		if ( 'wp-parser-function' == get_post_type() && 'capital_P_dangit' == get_the_title() ) {
			$excerpt = str_replace( 'Wordpress', 'Word&#112;ress', $excerpt );
		}

		return $excerpt;
	}

	/**
	 * Prevents display of the inline use of {@internal}} as it is not meant to be shown.
	 *
	 * @param  string      $content   The post content.
	 * @param  null|string $post_type Optional. The post type. Default null.
	 * @return string
	 */
	public static function remove_inline_internal( $content, $post_type = null ) {
		// Only attempt a change for a parsed post type with an @internal reference in the text.
		if ( DevHub\is_parsed_post_type( $post_type ) && false !== strpos( $content, '{@internal ' ) ) {
			$content = preg_replace( '/\{@internal (.+)\}\}/', '', $content );
		}

		return $content;
	}

	/**
	 * Makes phpDoc @see and @link references clickable.
	 *
	 * Handles these six different types of links:
	 *
	 * - {@link http://en.wikipedia.org/wiki/ISO_8601}
	 * - {@see WP_Rewrite::$index}
	 * - {@see WP_Query::query()}
	 * - {@see esc_attr()}
	 * - {@see 'pre_get_search_form'}
	 * - {@link http://codex.wordpress.org/The_Loop Use new WordPress Loop}
	 *
	 * Note: Though @see and @link are semantically different in meaning, that isn't always
	 * the case with use so this function handles them identically.
	 *
	 * @param  string $content The content.
	 * @return string
	 */
	public static function make_doclink_clickable( $content ) {

		// Nothing to change unless a @link or @see reference is in the text.
		if ( false === strpos( $content, '{@link ' ) && false === strpos( $content, '{@see ' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/\{@(?:link|see) ([^\}]+)\}/',
			function ( $matches ) {

				$link = $matches[1];

				// We may have encoded a link, so unencode if so.
				// (This would never occur natually.)
				if ( 0 === strpos( $link, '&lt;a ' ) ) {
					$link = html_entity_decode( $link );
				}

				// Undo links made clickable during initial parsing
				if ( 0 === strpos( $link, '<a ' ) ) {

					if ( preg_match( '/^<a .*href=[\'\"]([^\'\"]+)[\'\"]>(.*)<\/a>(.*)$/', $link, $parts ) ) {
						$link = $parts[1];
						if ( $parts[3] ) {
							$link .= ' ' . $parts[3];
						}
					}

				}

				// Link to an external resource.
				if ( 0 === strpos( $link, 'http' ) ) {

					$parts = explode( ' ', $link, 2 );

					// Link without linked text: {@link http://en.wikipedia.org/wiki/ISO_8601}
					if ( 1 === count( $parts ) ) {
						$link = '<a href="' . esc_url( $link ) . '">' . esc_html( $link ) . '</a>';
					}

					// Link with linked text: {@link http://codex.wordpress.org/The_Loop Use new WordPress Loop}
					else {
						$link = '<a href="' . esc_url( $parts[0] ) . '">' . esc_html( $parts[1] ) . '</a>';
					}

				}

				// Link to an internal resource.
				else {
					$link = self::link_internal_element( $link );
				}

				return $link;
			},
			$content
		);
	}

	/**
	 * Parses and links an internal element if a valid element is found.
	 *
	 * @static
	 * @access public
	 *
	 * @param string $link Element string.
	 * @param string HTML link markup if a valid element was found.
	 */
	public static function link_internal_element( $link ) {
		// Link to class variable: {@see WP_Rewrite::$index}
		if ( false !== strpos( $link, '::$' ) ) {
			// Nothing to link to currently.
		}

		// Link to class method: {@see WP_Query::query()}
		elseif ( false !== strpos( $link, '::' ) ) {
			$link = '<a href="' .
			        get_post_type_archive_link( 'wp-parser-class' ) .
			        str_replace( array( '::', '()' ), array( '/', '' ), $link ) .
			        '">' . esc_html( $link ) . '</a>';
		}

		// Link to hook: {@see 'pre_get_search_form'}
		elseif ( 1 === preg_match( '/^(?:&#8216;)([\$\w]+)(?:&#8217;)$/', $link, $hook ) ) {
			if ( ! empty( $hook[1] ) ) {
				$link = '<a href="' .
				        get_post_type_archive_link( 'wp-parser-hook' ) .
				        sanitize_key( $hook[1] ) . '/' .
				        '">' . esc_html( $link ) . '</a>';
			}
		}

		// Link to function: {@see esc_attr()}
		else {
			$link = '<a href="' .
			        get_post_type_archive_link( 'wp-parser-function' ) .
			        str_replace( '()', '', $link ) .
			        '">' . esc_html( $link ) . '</a>';
		}
		return $link;
	}

	/**
	 * Fixes unintended markup generated by Markdown during parsing.
	 *
	 * The parser interprets underscores surrounding text as Markdown indicating
	 * italics. That is never the intention, so undo it.
	 *
	 * @param  string      $content   The post content.
	 * @param  null|string $post_type Optional. The post type. Default null.
	 * @return string
	 */
	public static function fix_unintended_markdown( $content, $post_type = null ) {
		// Only apply to parsed content that have the em tag.
		if ( DevHub\is_parsed_post_type( $post_type )
			&& false !== strpos( $content, '<em>' )
			&& false === strpos( $content, '<p>' )
		) {
			$content = preg_replace_callback(
				'/([^\s])<em>(.+)<\/em>/',
				function ( $matches ) {
					return $matches[1] . '_' . $matches[2] . '_';
				},
				$content
			);
		}

		return $content;
	}

	/**
	 * Handles formatting of the parameter description.
	 *
	 * @param  string $text The parameter description.
	 * @return string
	 */
	public static function format_param_description( $text ) {
		// Undo parser's Markdown conversion of '*' to `<em>` and `</em>`.
		// In pretty much all cases, the docs mean literal '*' and never emphasis.
		$text = str_replace( array( '<em>', '</em>' ), '*', $text );

		// Encode all htmlentities (but don't double-encode).
		$text = htmlentities( $text, ENT_COMPAT | ENT_HTML401, 'UTF-8', false );

		// Simple allowable tags that should get unencoded.
		// Note: This precludes them from being able to be used in an encoded fashion
		// within a parameter description.
		$allowable_tags = array( 'code' );
		foreach ( $allowable_tags as $tag ) {
			$text = str_replace( array( "&lt;{$tag}&gt;", "&lt;/{$tag}&gt;" ), array( "<{$tag}>", "</{$tag}>" ), $text );
		}

		// Convert asterisks to a list.
		// Inline lists in param descriptions aren't handled by parser.
		// Example: https://developer.wordpress.org/reference/functions/add_menu_page/
		if ( false !== strpos( $text, ' * ' ) )  {
			// Display as simple plaintext list.
			$text = str_replace( ' * ', '<br /> * ', $text );
		}

		// Convert any @link or @see to actual link.
		$text = self::make_doclink_clickable( $text );

		return apply_filters( 'devhub-format-description', $text );
	}

	/**
	 * Automatically detects inline references to parsed resources and links to them.
	 *
	 * Examples:
	 * - Functions: get_the_ID()
	 * - Classes:   WP_Query
	 * - Methods:   WP_Query::is_single()
	 *
	 * Note: currently there is not a reliable way to infer references to hooks. Recommend
	 * using the {@}see 'hook_name'} notation as used in the inline docs.
	 *
	 * @param  string $text The text.
	 * @return string
	 */
	public static function autolink_references( $text ) {
		// Temporary: Don't do anything if the text is a hash notation string.
		if ( $text && '{' === $text[0] ) {
			return $text;
		}

		$r = '';
		$textarr = preg_split( '/(<[^<>]+>)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE ); // split out HTML tags
		$nested_code_pre = 0; // Keep track of how many levels link is nested inside <pre> or <code>
		foreach ( $textarr as $piece ) {

			if ( preg_match( '|^<code[\s>]|i', $piece ) || preg_match( '|^<pre[\s>]|i', $piece ) || preg_match( '|^<script[\s>]|i', $piece ) || preg_match( '|^<style[\s>]|i', $piece ) )
				$nested_code_pre++;
			elseif ( $nested_code_pre && ( '</code>' === strtolower( $piece ) || '</pre>' === strtolower( $piece ) || '</script>' === strtolower( $piece ) || '</style>' === strtolower( $piece ) ) )
				$nested_code_pre--;

			if ( $nested_code_pre || empty( $piece ) || ( $piece[0] === '<' && ! preg_match( '|^<\s*[\w]{1,20}+://|', $piece ) ) ) {
				$r .= $piece;
				continue;
			}

			// Long strings might contain expensive edge cases ...
			if ( 10000 < strlen( $piece ) ) {
				// ... break it up
				foreach ( _split_str_by_whitespace( $piece, 2100 ) as $chunk ) { // 2100: Extra room for scheme and leading and trailing paretheses
					if ( 2101 < strlen( $chunk ) ) {
						$r .= $chunk; // Too big, no whitespace: bail.
					} else {
						$r .= make_clickable( $chunk );
					}
				}
			} else {
				/*
				 * Everthing outside of this conditional block was copied from core's
				 *`make_clickable()`.
				 */

				$content = " $piece "; // Pad with whitespace to simplify the regexes

				// Only if the text contains something that might be a function.
				if ( false !== strpos( $content, '()' ) ) {

					// Detect references to class methods, e.g. WP_Query::query()
					// or functions, e.g. register_post_type().
					$content = preg_replace_callback(
						'~
							(?!<.*?)       # Non-capturing check to ensure not matching what looks like the inside of an HTML tag.
							(              # 1: The full method or function name.
								((\w+)::)? # 2: The class prefix, if a method reference.
								(\w+)      # 3: The method or function name.
							)
							\(\)           # The () that signifies either a method or function.
							(?![^<>]*?>)   # Non-capturing check to ensure not matching what looks like the inside of an HTML tag.
						~x',
						function ( $matches ) {
							// Reference to a class method.
							if ( $matches[2] ) {
								// Only link actually parsed methods.
								if ( $post = get_page_by_title( $matches[1], OBJECT, 'wp-parser-method' ) ) {
									return sprintf(
										'<a href="%s">%s</a>',
										get_permalink( $post->ID ),
										$matches[0]
									);
								}

							// Reference to a function.
							} else {
								// Only link actually parsed functions.
								if ( $post = get_page_by_title( $matches[1], OBJECT, 'wp-parser-function' ) ) {
									return sprintf(
										'<a href="%s">%s</a>',
										get_permalink( $post->ID ),
										$matches[0]
									);
								}
							}

							// It's not a reference to an actual thing, so restore original text.
							return $matches[0];
						},
						$content
					);

				}

				// Detect references to classes, e.g. WP_Query
				$content = preg_replace_callback(
					// Most class names start with an uppercase letter and have an underscore.
					// The exceptions are explicitly listed since future classes likely won't violate previous statement.
					'~'
						. '(?<!/)'
						. '\b'                // Word boundary
						. '('                 // Primary match grouping
							. 'wpdb|wp_atom_server|wp_xmlrpc_server extends IXR_Server'               // Exceptions that start with lowercase letter
							. '|AtomFeed|AtomEntry|AtomParser|MagpieRSS|RSSCache|Translations|Walker' // Exceptions that lack an underscore
							. '|[A-Z][a-zA-Z]+_\w+'                                                   // Most start with uppercase, has underscore
						. ')'                 // End primary match grouping
						. '\b'                // Word boundary
						. '(?!([<:]|"|\'>))'  // Does not appear within a tag
					. '~',
					function ( $matches ) {
						// If match is all caps, it's not a possible class name.
						// We'll chalk the sole exception, WP, as merely being an abbreviation (the regex won't match it anyhow).
						if ( strtoupper( $matches[0] ) === $matches[0] ) {
							return $matches[0];
						}

						// Only link actually parsed classes.
						if ( $post = get_page_by_title( $matches[0], OBJECT, 'wp-parser-class' ) ) {
							return sprintf(
								'<a href="%s">%s</a>',
								get_permalink( $post->ID ),
								$matches[0]
							);
						}

						// Not a class reference, so put the original reference back in.
						return $matches[0];
					},
					$content
				);

				// Maybelater: Detect references to hooks, Currently not deemed reliably possible.

				$content = substr( $content, 1, -1 ); // Remove our whitespace padding.
				$r .= $content;

			} // end else

		} // end foreach

		// Cleanup of accidental links within links
		return preg_replace( '#(<a([ \r\n\t]+[^>]+?>|>))<a [^>]+?>([^>]+?)</a></a>#i', "$1$3</a>", $r );
	}

	/**
	 * Formats the output of params defined using hash notation.
	 *
	 * This is a temporary measure until the parser parses the hash notation
	 * into component elements that the theme could then handle and style
	 * properly.
	 *
	 * Also, as a stopgap this is going to begin as a barebones hack to simply
	 * keep the text looking like one big jumble.
	 *
	 * @param  string $text The content for the param.
	 * @return string
	 */
	public static function fix_param_hash_formatting( $text ) {
		// Don't do anything if this isn't a hash notation string.
		if ( ! $text || '{' != $text[0] ) {
			return $text;
		}

		$new_text = '';
		$text     = trim( substr( $text, 1, -1 ) );
		$text     = str_replace( '@type', "\n@type", $text );

		$in_list = false;
		$parts = explode( "\n", $text );
		foreach ( $parts as $part ) {
			$part = preg_replace( '/\s+/', ' ', $part );
			list( $wordtype, $type, $name, $description ) = explode( ' ', $part, 4 );
			$description = trim( $description );
			$description = self::autolink_references( $description );

			$skip_closing_li = false;

			// Handle nested hashes.
			if ( '{' === $description[0] || '{' === $name ) {
				$description = ltrim( $description, '{' ) . '<ul class="param-hash">';
				$skip_closing_li = true;
			} elseif ( '}' === substr( $description, -1 ) ) {
				$description = substr( $description, 0, -1 ) . "</li></ul>\n";
			}

			if ( '@type' != $wordtype ) {
				if ( $in_list ) {
					$in_list = false;
					$new_text .= "</li></ul>\n";
				}

				$new_text .= $part;
			} else {
				if ( $in_list ) {
					$new_text .= '<li>';
				} else {
					$new_text .= '<ul class="param-hash"><li>';
					$in_list = true;
				}

				// Normalize argument name.
				if ( $name === '{' ) {
					// No name is specified, generally indicating an array of arrays.
					$name = '';
				} else {
					// The name is defined as a variable, so remove the leading '$'.
					$name = ltrim( $name, '$' );
				}
				if ( $name ) {
					$new_text .= "<b>'{$name}'</b><br />";
				}
				$new_text .= "<i><span class='type'>({$type})</span></i> {$description}";
				if ( ! $skip_closing_li ) {
					$new_text .= '</li>';
				}
				$new_text .= "\n";
			}
		}

		if ( $in_list ) {
			$new_text .= "</li></ul>\n";
		}

		return $new_text;
	}

} // DevHub_Formatting

DevHub_Formatting::init();

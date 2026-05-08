<?php
namespace WordPressdotorg\Plugin_Directory\Tools;

/**
 * PHP source tokenisation helpers used by the importer to detect calls to
 * specific functions and extract their literal-string arguments.
 *
 * @package WordPressdotorg\Plugin_Directory\Tools
 */
class Tokenisation_Helpers {

	/**
	 * Find calls to `$function_name` in PHP source and return the literal-string
	 * value of the Nth positional argument from each call.
	 *
	 * Returns one entry per matched call, in source order. The first
	 * `T_CONSTANT_ENCAPSED_STRING` reachable inside the target arg wins, so
	 * wrapping calls like `__( 'Foo', 'td' )`, `_x( 'Foo', 'ctx', 'td' )`, and
	 * `esc_html__( 'Foo' )` are handled implicitly. Calls whose target arg has
	 * no literal-string value (variable, class const, expression) yield an
	 * empty string — the call is still reported so callers can detect that the
	 * function was used even when the label is not parseable.
	 *
	 * @param string $contents      PHP source code.
	 * @param string $function_name Function name to match. Prefix with `'new '`
	 *                              for a constructor (e.g. `'new WP_Block_Type'`).
	 * @param int    $arg_index     Zero-based index of the target arg.
	 * @return string[] One entry per matched call (`''` when the arg has no literal).
	 */
	public static function find_function_call_arg_strings( $contents, $function_name, $arg_index = 0 ) {
		$values = array();
		foreach ( self::walk_calls( $contents, $function_name ) as $args ) {
			$values[] = self::first_literal( $args[ $arg_index ] ?? array() ) ?? '';
		}
		return $values;
	}

	/**
	 * For each call to `$function_name` whose first argument is a literal string,
	 * return `[ first_arg_value => value_at_$array_key ]`, where the value is the
	 * literal-string entry at `$array_key` inside the inline `array(...)`/`[...]`
	 * literal at `$array_arg_index`. The value is null when the arg is not an
	 * inline array, the key is missing, or the matched value is not a literal.
	 *
	 * Use this for the "registration call" pattern where the first arg is the
	 * identifier and a second-arg options array carries inline metadata, e.g.
	 * `register_block_type( 'foo/bar', array( 'title' => 'Foo' ) )`.
	 *
	 * @param string $contents         PHP source code.
	 * @param string $function_name    Function name to match. Prefix with `'new '`
	 *                                 for a constructor (e.g. `'new WP_Block_Type'`).
	 * @param int    $array_arg_index  Zero-based index of the array argument.
	 * @param string $array_key        Key to look up inside the array literal.
	 * @return array<string, string|null>
	 */
	public static function find_function_call_first_arg_and_array_value( $contents, $function_name, $array_arg_index, $array_key ) {
		$results = array();
		foreach ( self::walk_calls( $contents, $function_name ) as $args ) {
			$name = self::first_literal( $args[0] ?? array() );
			if ( null === $name || '' === $name ) {
				continue;
			}
			$results[ $name ] = self::array_string_value( $args[ $array_arg_index ] ?? array(), $array_key );
		}
		return $results;
	}

	/**
	 * Walk PHP tokens for calls to `$function_name` and yield each call's
	 * arg-list tokens, split into per-arg slices at top-level commas.
	 *
	 * @return array[] One entry per matched call: [ arg0_tokens, arg1_tokens, ... ].
	 */
	private static function walk_calls( $contents, $function_name ) {
		$tokens = @token_get_all( $contents );
		if ( ! $tokens ) {
			return array();
		}

		$is_new      = str_starts_with( $function_name, 'new ' );
		$needle      = $is_new ? substr( $function_name, 4 ) : $function_name;
		$global_form = '\\' . $needle;
		$skip        = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
		$out         = array();
		$count       = count( $tokens );

		for ( $i = 0; $i < $count; $i++ ) {
			$tok = $tokens[ $i ];
			if ( ! is_array( $tok ) ) {
				continue;
			}
			$matches_simple = ( T_STRING === $tok[0] && 0 === strcasecmp( $tok[1], $needle ) );
			$matches_global = ( T_NAME_FULLY_QUALIFIED === $tok[0] && 0 === strcasecmp( $tok[1], $global_form ) );
			if ( ! $matches_simple && ! $matches_global ) {
				continue;
			}

			// Skip method/property access, function declarations, and constructor mismatches.
			$prev_id = null;
			for ( $p = $i - 1; $p >= 0; $p-- ) {
				$pt = $tokens[ $p ];
				if ( is_array( $pt ) && in_array( $pt[0], $skip, true ) ) {
					continue;
				}
				$prev_id = is_array( $pt ) ? $pt[0] : null;
				break;
			}
			if ( in_array( $prev_id, array( T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR ), true ) ) {
				continue;
			}
			if ( $is_new ? T_NEW !== $prev_id : T_NEW === $prev_id ) {
				continue;
			}

			// Find the opening paren of the call.
			$j = $i + 1;
			while ( $j < $count && is_array( $tokens[ $j ] ) && in_array( $tokens[ $j ][0], $skip, true ) ) {
				$j++;
			}
			if ( $j >= $count || '(' !== $tokens[ $j ] ) {
				continue;
			}

			// Collect arg-list tokens, splitting at top-level commas.
			$args  = array();
			$cur   = array();
			$depth = 1;
			for ( $k = $j + 1; $k < $count; $k++ ) {
				$t = $tokens[ $k ];
				if ( is_array( $t ) ) {
					if ( in_array( $t[0], $skip, true ) ) {
						continue;
					}
					$cur[] = $t;
					continue;
				}
				if ( '(' === $t || '[' === $t || '{' === $t ) {
					$depth++;
					$cur[] = $t;
					continue;
				}
				if ( ')' === $t || ']' === $t || '}' === $t ) {
					$depth--;
					if ( 0 === $depth ) {
						if ( $cur ) {
							$args[] = $cur;
						}
						break;
					}
					$cur[] = $t;
					continue;
				}
				if ( ',' === $t && 1 === $depth ) {
					$args[] = $cur;
					$cur    = array();
					continue;
				}
				$cur[] = $t;
			}
			$out[] = $args;
			$i = $j;
		}
		return $out;
	}

	/**
	 * Return the unescaped value of the first `T_CONSTANT_ENCAPSED_STRING` in
	 * the given token list, regardless of nesting depth. null if none.
	 */
	private static function first_literal( array $tokens ) {
		foreach ( $tokens as $t ) {
			if ( is_array( $t ) && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
				return self::unescape_string_token( $t[1] );
			}
		}
		return null;
	}

	/**
	 * Inside an arg-token list that forms an `array(...)` or `[...]` literal,
	 * return the literal-string value of the entry whose key equals `$array_key`.
	 * null when the arg is not an inline array, the key is absent, or the value
	 * isn't a literal string (recursing through wrappers like `__()`).
	 */
	private static function array_string_value( array $tokens, $array_key ) {
		$inner = self::array_inner_tokens( $tokens );
		if ( null === $inner ) {
			return null;
		}

		$depth = 0;
		$count = count( $inner );
		for ( $i = 0; $i < $count; $i++ ) {
			$t = $inner[ $i ];
			if ( ! is_array( $t ) ) {
				if ( '(' === $t || '[' === $t || '{' === $t ) {
					$depth++;
					continue;
				}
				if ( ')' === $t || ']' === $t || '}' === $t ) {
					$depth--;
					continue;
				}
				continue;
			}
			if ( 0 !== $depth || T_CONSTANT_ENCAPSED_STRING !== $t[0] ) {
				continue;
			}
			if ( self::unescape_string_token( $t[1] ) !== $array_key ) {
				continue;
			}

			// Found the key; the next significant token must be `=>`.
			for ( $j = $i + 1; $j < $count; $j++ ) {
				$u = $inner[ $j ];
				if ( is_array( $u ) && T_DOUBLE_ARROW === $u[0] ) {
					// Capture the value tokens until the next top-level comma.
					$value_tokens = array();
					$vd           = 0;
					for ( $k = $j + 1; $k < $count; $k++ ) {
						$v = $inner[ $k ];
						if ( ! is_array( $v ) ) {
							if ( '(' === $v || '[' === $v || '{' === $v ) {
								$vd++;
							} elseif ( ')' === $v || ']' === $v || '}' === $v ) {
								$vd--;
							} elseif ( ',' === $v && 0 === $vd ) {
								break;
							}
						}
						$value_tokens[] = $v;
					}
					return self::first_literal( $value_tokens );
				}
				break;
			}
		}
		return null;
	}

	/**
	 * Return the inner tokens of an `array(...)` or `[...]` literal, or null
	 * when the given tokens are not such a literal.
	 */
	private static function array_inner_tokens( array $tokens ) {
		$count = count( $tokens );
		if ( $count < 2 ) {
			return null;
		}
		$first = $tokens[0];
		$last  = $tokens[ $count - 1 ];
		if ( '[' === $first && ']' === $last ) {
			return array_slice( $tokens, 1, $count - 2 );
		}
		if ( $count >= 3 && is_array( $first ) && T_ARRAY === $first[0] && '(' === $tokens[1] && ')' === $last ) {
			return array_slice( $tokens, 2, $count - 3 );
		}
		return null;
	}

	/**
	 * Decode a PHP string literal token (with surrounding quotes) to its value.
	 */
	private static function unescape_string_token( $literal ) {
		if ( strlen( $literal ) < 2 ) {
			return $literal;
		}
		$body = substr( $literal, 1, -1 );
		if ( "'" === $literal[0] ) {
			return str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body );
		}
		return stripcslashes( $body );
	}
}

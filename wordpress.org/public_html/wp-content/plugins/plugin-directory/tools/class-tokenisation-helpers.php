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
	 * Function names whose first positional argument is treated as the wrapped
	 * literal-string value. Compared case-insensitively (PHP function-name rules).
	 */
	private const TRANSLATION_FUNCTIONS = [
		'__',
		'_e',
		'_x',
		'_ex',
		'_n',
		'_nx',
		'esc_html__',
		'esc_html_e',
		'esc_html_x',
		'esc_attr__',
		'esc_attr_e',
		'esc_attr_x',
		'translate',
		'translate_with_gettext_context',
	];

	private const SKIP_TOKENS = [ T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ];

	/**
	 * Find calls to `$function_name` in PHP source and return structured arg
	 * values for each call.
	 *
	 * Each entry is a positional list of resolved arg values where each value
	 * is one of:
	 *   - string  literal-string value (bare, or unwrapped from a recognised
	 *             i18n function call like `__()`, `_x()`, `esc_html__()`)
	 *   - array   inline `array(...)` / `[...]` literal, recursively resolved
	 *             (only string-keyed entries are returned; values follow the
	 *             same string|array|null contract)
	 *   - null    any other expression (variable, class const, method call,
	 *             concat, non-i18n wrapper, etc.)
	 *
	 * Calls inside comments, string literals, declarations, method/static-method
	 * accesses, and constructor mismatches are ignored.
	 *
	 * @param string $contents      PHP source code.
	 * @param string $function_name Function name to match. Prefix with `'new '`
	 *                              for a constructor (e.g. `'new WP_Block_Type'`).
	 * @return array[] One entry per matched call.
	 */
	public static function find_function_calls( string $contents, string $function_name ): array {
		$tokens = token_get_all( $contents );
		$calls  = [];
		foreach ( self::walk_calls( $tokens, $function_name ) as $arg_tokens_per_call ) {
			$calls[] = array_map(
				static fn( array $arg_tokens ) => self::resolve_value( $arg_tokens ),
				$arg_tokens_per_call
			);
		}
		return $calls;
	}

	/**
	 * Generator: walk PHP tokens for calls to `$function_name`, yielding each
	 * call's argument list split into per-arg token slices.
	 *
	 * @return iterable<array[]>
	 */
	private static function walk_calls( array $tokens, string $function_name ): iterable {
		$is_new      = str_starts_with( $function_name, 'new ' );
		$needle      = $is_new ? substr( $function_name, 4 ) : $function_name;
		$global_form = '\\' . $needle;
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
			$prev_id = self::previous_significant_id( $tokens, $i );
			if ( in_array( $prev_id, [ T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NULLSAFE_OBJECT_OPERATOR ], true ) ) {
				continue;
			}
			if ( $is_new ? T_NEW !== $prev_id : T_NEW === $prev_id ) {
				continue;
			}

			// Find the opening paren.
			$j = self::next_significant_index( $tokens, $i + 1 );
			if ( null === $j || '(' !== ( $tokens[ $j ] ?? null ) ) {
				continue;
			}

			$arg_lists = self::collect_arg_lists( $tokens, $j );
			if ( null !== $arg_lists ) {
				yield $arg_lists;
			}
			$i = $j;
		}
	}

	private static function previous_significant_id( array $tokens, int $from ): mixed {
		for ( $p = $from - 1; $p >= 0; $p-- ) {
			$pt = $tokens[ $p ];
			if ( is_array( $pt ) && in_array( $pt[0], self::SKIP_TOKENS, true ) ) {
				continue;
			}
			return is_array( $pt ) ? $pt[0] : null;
		}
		return null;
	}

	private static function next_significant_index( array $tokens, int $from ): ?int {
		$count = count( $tokens );
		for ( $k = $from; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( ! is_array( $t ) || ! in_array( $t[0], self::SKIP_TOKENS, true ) ) {
				return $k;
			}
		}
		return null;
	}

	/**
	 * Walk from the index of `(` through the matching `)`, returning per-arg
	 * token slices (split at top-level commas). Returns null when parens are
	 * unbalanced.
	 *
	 * @return array[]|null
	 */
	private static function collect_arg_lists( array $tokens, int $open_paren_index ): ?array {
		$args  = [];
		$cur   = [];
		$depth = 1;
		$count = count( $tokens );

		for ( $i = $open_paren_index + 1; $i < $count; $i++ ) {
			$tok = $tokens[ $i ];

			if ( is_array( $tok ) ) {
				if ( in_array( $tok[0], self::SKIP_TOKENS, true ) ) {
					continue;
				}
				$cur[] = $tok;
				continue;
			}
			if ( '(' === $tok || '[' === $tok || '{' === $tok ) {
				$depth++;
				$cur[] = $tok;
				continue;
			}
			if ( ')' === $tok || ']' === $tok || '}' === $tok ) {
				$depth--;
				if ( 0 === $depth ) {
					if ( $cur ) {
						$args[] = $cur;
					}
					return $args;
				}
				$cur[] = $tok;
				continue;
			}
			if ( ',' === $tok && 1 === $depth ) {
				$args[] = $cur;
				$cur    = [];
				continue;
			}
			$cur[] = $tok;
		}
		return null;
	}

	/**
	 * Resolve an arg-token list to a structured value: string | array | null.
	 */
	private static function resolve_value( array $tokens ): string|array|null {
		$count = count( $tokens );
		if ( 0 === $count ) {
			return null;
		}
		$first = $tokens[0];

		// Bare literal: a single T_CONSTANT_ENCAPSED_STRING token.
		if ( 1 === $count && is_array( $first ) && T_CONSTANT_ENCAPSED_STRING === $first[0] ) {
			return self::unescape_string_token( $first[1] );
		}

		// Inline `array(...)` / `[...]` literal.
		$inner = self::array_inner_tokens( $tokens );
		if ( null !== $inner ) {
			return self::resolve_array_literal( $inner );
		}

		// Known translation wrapper: `T_STRING '(' ... ')'`.
		if ( is_array( $first )
			&& T_STRING === $first[0]
			&& '(' === ( $tokens[1] ?? null )
			&& self::is_translation_function( $first[1] )
		) {
			return self::resolve_value( self::first_inner_arg_tokens( $tokens ) );
		}

		return null;
	}

	private static function is_translation_function( string $name ): bool {
		foreach ( self::TRANSLATION_FUNCTIONS as $candidate ) {
			if ( 0 === strcasecmp( $name, $candidate ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Given tokens that begin with `T_STRING '('`, return the tokens of the
	 * call's first positional argument (between `(` and the first top-level
	 * `,` or the matching `)`).
	 */
	private static function first_inner_arg_tokens( array $tokens ): array {
		$inner = [];
		$depth = 1;
		$count = count( $tokens );
		for ( $k = 2; $k < $count; $k++ ) {
			$t = $tokens[ $k ];
			if ( is_array( $t ) ) {
				$inner[] = $t;
				continue;
			}
			if ( '(' === $t || '[' === $t || '{' === $t ) {
				$depth++;
				$inner[] = $t;
				continue;
			}
			if ( ')' === $t || ']' === $t || '}' === $t ) {
				$depth--;
				if ( 0 === $depth ) {
					break;
				}
				$inner[] = $t;
				continue;
			}
			if ( ',' === $t && 1 === $depth ) {
				break;
			}
			$inner[] = $t;
		}
		return $inner;
	}

	/**
	 * Walk the inner tokens of an array literal and return an associative
	 * array of `string-key => resolved-value`. Entries without an explicit
	 * `=>` (implicit integer keys) or with non-literal-string keys are
	 * skipped.
	 */
	private static function resolve_array_literal( array $inner_tokens ): array {
		$result = [];
		foreach ( self::split_array_entries( $inner_tokens ) as $entry_tokens ) {
			[ $key_tokens, $value_tokens ] = self::split_key_value( $entry_tokens );
			if ( null === $key_tokens ) {
				continue;
			}
			$key = self::resolve_value( $key_tokens );
			if ( ! is_string( $key ) ) {
				continue;
			}
			$result[ $key ] = self::resolve_value( $value_tokens );
		}
		return $result;
	}

	/**
	 * Split a list of array-inner tokens at top-level commas.
	 *
	 * @return array[]
	 */
	private static function split_array_entries( array $tokens ): array {
		$entries = [];
		$cur     = [];
		$depth   = 0;
		foreach ( $tokens as $t ) {
			if ( ! is_array( $t ) ) {
				if ( '(' === $t || '[' === $t || '{' === $t ) {
					$depth++;
					$cur[] = $t;
					continue;
				}
				if ( ')' === $t || ']' === $t || '}' === $t ) {
					$depth--;
					$cur[] = $t;
					continue;
				}
				if ( ',' === $t && 0 === $depth ) {
					if ( $cur ) {
						$entries[] = $cur;
					}
					$cur = [];
					continue;
				}
			}
			$cur[] = $t;
		}
		if ( $cur ) {
			$entries[] = $cur;
		}
		return $entries;
	}

	/**
	 * Split a single array-entry token list at the top-level `=>` operator.
	 *
	 * @return array{0: array|null, 1: array} [ key_tokens, value_tokens ];
	 *         key_tokens is null when no top-level `=>` is present.
	 */
	private static function split_key_value( array $tokens ): array {
		$depth = 0;
		$count = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			$t = $tokens[ $i ];
			if ( is_array( $t ) ) {
				if ( 0 === $depth && T_DOUBLE_ARROW === $t[0] ) {
					return [ array_slice( $tokens, 0, $i ), array_slice( $tokens, $i + 1 ) ];
				}
				continue;
			}
			if ( '(' === $t || '[' === $t || '{' === $t ) {
				$depth++;
			} elseif ( ')' === $t || ']' === $t || '}' === $t ) {
				$depth--;
			}
		}
		return [ null, $tokens ];
	}

	/**
	 * Return the inner tokens of an `array(...)` or `[...]` literal, or null
	 * when the given tokens are not such a literal.
	 */
	private static function array_inner_tokens( array $tokens ): ?array {
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
	private static function unescape_string_token( string $literal ): string {
		if ( strlen( $literal ) < 2 ) {
			return $literal;
		}
		$body = substr( $literal, 1, -1 );
		return "'" === $literal[0]
			? str_replace( [ "\\'", '\\\\' ], [ "'", '\\' ], $body )
			: stripcslashes( $body );
	}
}

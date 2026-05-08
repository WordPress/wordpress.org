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
	 * The first `T_CONSTANT_ENCAPSED_STRING` reachable inside the target arg wins,
	 * so wrapping calls like `__( 'Foo', 'td' )`, `_x( 'Foo', 'ctx', 'td' )`, and
	 * `esc_html__( 'Foo' )` are handled implicitly. Calls whose target arg has no
	 * literal-string value (variable, class const, expression) are skipped.
	 *
	 * @param string $contents      PHP source code.
	 * @param string $function_name Function name to match. Prefix with `'new '`
	 *                              for a constructor (e.g. `'new WP_Block_Type'`).
	 * @param int    $arg_index     Zero-based index of the target arg.
	 * @return string[] One literal-string value per matched call.
	 */
	public static function find_function_call_arg_strings( $contents, $function_name, $arg_index = 0 ) {
		$tokens = @token_get_all( $contents );
		if ( ! $tokens ) {
			return array();
		}

		$is_new = str_starts_with( $function_name, 'new ' );
		$needle = $is_new ? substr( $function_name, 4 ) : $function_name;
		$skip   = array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
		$values = array();
		$count  = count( $tokens );

		$global_form = '\\' . $needle;

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

			// Walk the args and capture the first literal in the target position.
			$depth    = 1;
			$cur_arg  = 0;
			$captured = null;
			for ( $k = $j + 1; $k < $count; $k++ ) {
				$t = $tokens[ $k ];
				if ( is_array( $t ) ) {
					if ( in_array( $t[0], $skip, true ) ) {
						continue;
					}
					if ( null === $captured && $cur_arg === $arg_index && T_CONSTANT_ENCAPSED_STRING === $t[0] ) {
						$body     = substr( $t[1], 1, -1 );
						$captured = "'" === $t[1][0]
							? str_replace( array( "\\'", '\\\\' ), array( "'", '\\' ), $body )
							: stripcslashes( $body );
					}
					continue;
				}
				if ( '(' === $t || '[' === $t || '{' === $t ) {
					$depth++;
					continue;
				}
				if ( ')' === $t || ']' === $t || '}' === $t ) {
					$depth--;
					if ( 0 === $depth ) {
						break;
					}
					continue;
				}
				if ( ',' === $t && 1 === $depth ) {
					$cur_arg++;
				}
			}
			if ( null !== $captured ) {
				$values[] = $captured;
			}
			$i = $j;
		}

		return $values;
	}
}

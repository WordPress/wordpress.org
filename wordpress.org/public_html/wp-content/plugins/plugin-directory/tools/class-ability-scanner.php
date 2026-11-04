<?php
namespace WordPressdotorg\Plugin_Directory\Tools;

use OverflowException;
use PhpToken;
use stdClass;

/**
 * Finds the abilities a plugin registers with the Abilities API by reading its
 * PHP source. Nothing in the plugin is executed.
 *
 * Popular plugins rarely pass literals to wp_register_ability(). The name and
 * args are usually built from class constants, getter methods, constructor
 * arguments, loops or the plugin's own wrapper functions. So every call is
 * evaluated with a small, side-effect-free interpreter that follows:
 *
 * - string and array literals, concatenation, `??`, sprintf() and i18n functions;
 * - local variables, foreach loops over arrays it can resolve, and define()d constants;
 * - class constants (`self::`, `static::`, `parent::`, `Foo::`, `$obj::`), including inherited ones;
 * - calls to the plugin's functions and methods whose value is a `return` expression,
 *   with `$this` set to each concrete subclass of the class making the call;
 * - `new Foo( ... )`, with constructor-promoted and assigned properties;
 * - the plugin's own wrappers around wp_register_ability(), through their call sites;
 * - array_merge(), array_replace(), wp_parse_args() and apply_filters() (which returns its value).
 *
 * Anything else evaluates to null ("unknown"). An ability is reported only when
 * its name resolves to a valid "namespace/name"; a label or description that
 * doesn't resolve is reported as null.
 *
 * Plugins that register names it can't resolve may keep their abilities in a
 * registry it can't follow (e.g. rows added through filters), so for those it
 * also falls back to looking for ability-shaped array literals.
 *
 * @package WordPressdotorg\Plugin_Directory\Tools
 */
class Ability_Scanner {

	/** The ability name format core accepts. */
	const NAME_PATTERN = '#^[a-z0-9-]+/[a-z0-9-]+$#';

	/** Nested evaluations (calls, constants, variables) before giving up on a value. */
	private const MAX_DEPTH = 24;

	/** Wrapper functions followed back through their callers. */
	private const MAX_CALL_DEPTH = 4;

	/**
	 * Work allowed for a whole plugin: one unit per expression evaluated and per
	 * token scanned while evaluating, so a pathological plugin can't stall the import.
	 */
	private const BUDGET = 5000000;

	/** Work allowed per wp_register_ability() call, so one expensive call can't starve the rest. */
	private const SITE_BUDGET = 250000;

	/** Work allowed for the definition-array fallback, in tokens. */
	private const DEFINITIONS_BUDGET = 5000000;

	/** Longest string the interpreter will build (labels and descriptions are far shorter). */
	private const MAX_STRING = 8192;

	/** Most elements in an array the interpreter will build by merging or spreading. */
	private const MAX_ARRAY = 2000;

	/** Contexts (loop iterations, subclasses, call sites) evaluated per call. */
	private const MAX_CONTEXTS = 500;

	/** Files larger than this are generated or minified, not hand-written registration code. */
	private const MAX_FILE_SIZE = 1048576;

	/** Tokenised files kept in memory. */
	private const CACHE_SIZE = 64;

	/** Functions that return their first argument, translated. */
	private const PASSTHROUGH_FUNCTIONS = [
		'__', '_e', '_x', '_ex', '_n', '_nx', 'esc_html__', 'esc_html_e', 'esc_html_x',
		'esc_attr__', 'esc_attr_e', 'esc_attr_x', 'translate', 'translate_with_gettext_context',
		'esc_html', 'esc_attr', 'trim', 'strval',
	];

	private array $files       = [];
	private array $cache       = [];
	private array $meta        = [];
	private array $identifiers = [];
	private array $classes     = [];
	private array $short_names = [];
	private array $children    = [];
	private array $functions   = [];
	private array $defines     = [];
	private array $sites       = [];
	private array $memo        = [];
	private int $budget        = self::BUDGET;
	private int $site_budget   = self::SITE_BUDGET;
	private int $depth         = 0;
	private bool $evaluating   = false;
	private \WeakMap $spans;
	private \WeakMap $closers;

	/**
	 * Find the abilities a plugin registers.
	 *
	 * Files under vendor/ are skipped: they belong to bundled dependencies,
	 * not the plugin itself.
	 *
	 * @param string $base_dir The plugin's root directory.
	 * @return object[] Unique abilities, each with name, label and description properties.
	 */
	public static function scan( string $base_dir ): array {
		$files = array_values( array_filter(
			Filesystem::list_files( $base_dir, true, '!\.php$!i' ),
			static fn( $file ) => ! str_contains( $file, '/vendor/' ) && filesize( $file ) <= self::MAX_FILE_SIZE
		) );
		// Directory order varies by filesystem; sort so duplicate registrations merge the same way every time.
		sort( $files );

		// Most plugins register no abilities; don't tokenise them.
		$relevant = false;
		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $file );
			if ( false !== stripos( $contents, 'register_ability' ) || false !== stripos( $contents, 'abilities_registry' ) ) {
				$relevant = true;
				break;
			}
		}
		if ( ! $relevant ) {
			return [];
		}

		return ( new self( $files ) )->run();
	}

	private function __construct( array $files ) {
		$this->files = $files;
		$this->spans   = new \WeakMap();
		$this->closers = new \WeakMap();
	}

	/**
	 * Whether a built array is small enough to keep. Charges its size, since
	 * memoised values (e.g. class constants) can be combined repeatedly.
	 */
	private function array_fits( array $array ): bool {
		$this->spend( count( $array ) );
		return count( $array ) <= self::MAX_ARRAY;
	}

	/**
	 * Charge work against the budget. Only evaluation is charged; indexing is
	 * linear in the plugin's size.
	 *
	 * @throws OverflowException When the budget is spent.
	 */
	private function spend( int $units = 1 ): void {
		if ( ! $this->evaluating ) {
			return;
		}
		$this->budget      -= $units;
		$this->site_budget -= $units;
		if ( $this->budget < 0 || $this->site_budget < 0 ) {
			throw new OverflowException( 'Ability scan budget exhausted.' );
		}
	}

	/**
	 * @return object[]
	 */
	private function run(): array {
		foreach ( $this->files as $file ) {
			$this->index( $file );
		}
		foreach ( $this->classes as $lname => $class ) {
			$parent = $this->parent_of( $lname );
			if ( $parent ) {
				$this->children[ $parent ][] = $lname;
			}
		}

		$this->evaluating = true;
		$found            = [];
		$dynamic          = false;
		$exhausted        = false;

		// Calls with a literal name cost almost nothing; evaluate them first so
		// expensive calls elsewhere can't use up the budget before they're reached.
		$literal = [];
		$other   = [];
		foreach ( $this->sites as $site ) {
			$tokens = $this->tokens( $site[0] );
			if ( T_CONSTANT_ENCAPSED_STRING === ( $tokens[ $site[1] + 2 ] ?? null )?->id && ( $tokens[ $site[1] + 3 ] ?? null )?->is( ',' ) ) {
				$literal[] = $site;
			} else {
				$other[] = $site;
			}
		}

		foreach ( array_merge( $literal, $other ) as [ $file, $index ] ) {
			$tokens = $this->tokens( $file );
			$args   = $this->split_args( $tokens, $index + 1 );
			if ( ! $args ) {
				continue;
			}
			[ $start, $end ] = $args[0];
			if ( $start !== $end || T_CONSTANT_ENCAPSED_STRING !== $tokens[ $start ]->id ) {
				$dynamic = true;
			}
			if ( $exhausted ) {
				continue;
			}
			$this->site_budget = self::SITE_BUDGET;
			$this->depth       = 0;
			try {
				$found = array_merge( $found, $this->evaluate_site( $file, $index, $args ) );
			} catch ( OverflowException $e ) {
				// Out of budget for this call (skip it) or for the plugin (keep what was found so far).
				$exhausted = $this->budget < 0;
			}
		}

		if ( $dynamic ) {
			$budget = self::DEFINITIONS_BUDGET;
			foreach ( $this->files as $file ) {
				$found = array_merge( $found, self::find_definitions( (string) file_get_contents( $file ), $budget ) );
				if ( $budget <= 0 ) {
					break;
				}
			}
		}

		return self::merge( $found );
	}

	/*
	 * Registration call sites.
	 */

	/**
	 * Evaluate one wp_register_ability() call in every context it can run in.
	 *
	 * @return object[]
	 */
	private function evaluate_site( string $file, int $index, array $args ): array {
		$tokens = $this->tokens( $file );
		$scope  = $this->scope_at( $file, $index );
		$name   = $this->slice( $tokens, $args[0] );
		$params = $this->uses_params( $name, $scope );

		$found = [];
		foreach ( $this->contexts( $scope, $index, $params, 0 ) as $ctx ) {
			$value = $this->evaluate( $name, $scope, $ctx );
			if ( ! is_string( $value ) || ! preg_match( self::NAME_PATTERN, $value ) ) {
				continue;
			}
			$options = isset( $args[1] ) ? $this->evaluate( $this->slice( $tokens, $args[1] ), $scope, $ctx ) : null;
			$found[] = self::make_ability( $value, $options );
		}
		return $found;
	}

	/**
	 * Contexts the code at $index can run in: each binding of the enclosing
	 * function's parameters (from its call sites, when $expand), each `$this`
	 * class, and each iteration of the foreach loops around $index.
	 *
	 * @return array[] Each with 'vars' (name => value) and 'this' (object|null).
	 */
	private function contexts( array $scope, int $index, bool $expand, int $depth ): array {
		$bindings = [ [ 'vars' => [], 'this' => null ] ];

		if ( $expand && $scope['name'] && $depth < self::MAX_CALL_DEPTH ) {
			foreach ( $this->call_sites( $scope ) as [ $file, $call, $receiver ] ) {
				$caller_tokens = $this->tokens( $file );
				$caller_scope  = $this->scope_at( $file, $call );
				$caller_args   = $this->split_args( $caller_tokens, $call + 1 );
				$slices        = array_map( fn( $range ) => $this->slice( $caller_tokens, $range ), $caller_args );
				$needs_params  = (bool) array_filter( $slices, fn( $slice ) => $this->uses_params( $slice, $caller_scope ) );

				foreach ( $this->contexts( $caller_scope, $call, $needs_params, $depth + 1 ) as $caller_ctx ) {
					$values     = $this->evaluate_args( $slices, $caller_scope, $caller_ctx );
					$bindings[] = [
						'vars' => $this->bind( $scope, $values ),
						'this' => $this->receiver( $receiver, $scope, $caller_tokens, $caller_scope, $caller_ctx ),
					];
					if ( count( $bindings ) > self::MAX_CONTEXTS ) {
						break 2;
					}
				}
			}
		}

		$contexts = [];
		foreach ( $bindings as $binding ) {
			$receivers = $binding['this'] ? [ $binding['this'] ] : ( $scope['class'] ? $this->receivers_for( $scope['class'] ) : [ null ] );
			foreach ( $receivers as $this_object ) {
				$ctx = [ 'vars' => $binding['vars'], 'this' => $this_object ];
				foreach ( $this->unroll_loops( $scope, $index, $ctx ) as $unrolled ) {
					$contexts[] = $unrolled;
					if ( count( $contexts ) >= self::MAX_CONTEXTS ) {
						return $contexts;
					}
				}
			}
		}
		return $contexts;
	}

	/**
	 * Whether tokens refer to a parameter of the scope's function.
	 */
	private function uses_params( array $tokens, array $scope ): bool {
		if ( ! $scope['params'] ) {
			return false;
		}
		$names = array_column( $scope['params'], 'name' );
		foreach ( $tokens as $token ) {
			if ( T_VARIABLE === $token->id && in_array( substr( $token->text, 1 ), $names, true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Where a function or method is called: [ file, index of the name token, receiver index ].
	 * The receiver index points at the token before `->`/`::` for method calls, or is null.
	 */
	private function call_sites( array $scope ): array {
		$key = "calls:{$scope['file']}:{$scope['start']}";
		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}

		$name      = strtolower( $scope['name'] );
		$is_method = 'method' === $scope['kind'];
		$sites     = [];
		foreach ( $this->files as $file ) {
			if ( ! isset( $this->identifiers[ $file ][ $name ] ) ) {
				continue;
			}
			$tokens = $this->tokens( $file );
			$this->spend( count( $tokens ) );
			foreach ( $tokens as $i => $token ) {
				if ( ! self::is_name( $token ) || strtolower( self::short_name( $token->text ) ) !== $name || ! ( $tokens[ $i + 1 ] ?? null )?->is( '(' ) ) {
					continue;
				}
				$prev = $tokens[ $i - 1 ] ?? null;
				$op   = $prev && in_array( $prev->id, [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON ], true );
				if ( $prev && in_array( $prev->id, [ T_FUNCTION, T_NEW ], true ) ) {
					continue;
				}
				if ( $is_method !== $op ) {
					continue;
				}
				if ( $is_method && T_DOUBLE_COLON === $prev->id && ! $this->static_receiver_matches( $file, $i - 2, $scope ) ) {
					continue;
				}
				$sites[] = [ $file, $i, $is_method ? $i - 2 : null ];
				if ( count( $sites ) >= 50 ) {
					break 2;
				}
			}
		}
		return $this->memo[ $key ] = $sites;
	}

	/**
	 * For `Foo::method()`, whether Foo is the method's class (or a subclass).
	 * `self::`, `static::` and `parent::` are accepted; the scope check happens when evaluating.
	 */
	private function static_receiver_matches( string $file, int $index, array $scope ): bool {
		$token = $this->tokens( $file )[ $index ] ?? null;
		if ( ! $token || T_VARIABLE === $token->id || in_array( strtolower( $token->text ), [ 'self', 'static', 'parent' ], true ) ) {
			return true;
		}
		$class = $this->lookup_class( $token->text, $this->scope_at( $file, $index ) );
		return null === $class || $this->is_a( $class, $scope['class'] );
	}

	/**
	 * The `$this` a wrapper method runs with, given the receiver at its call site.
	 */
	private function receiver( ?int $index, array $scope, array $caller_tokens, array $caller_scope, array $caller_ctx ): ?stdClass {
		if ( null === $index || ! $scope['class'] ) {
			return null;
		}
		$token = $caller_tokens[ $index ] ?? null;
		if ( ! $token ) {
			return null;
		}
		$lower = strtolower( $token->text );
		if ( '$this' === $lower || in_array( $lower, [ 'self', 'static', 'parent' ], true ) ) {
			return $caller_ctx['this'] && $this->is_a( $caller_ctx['this']->class, $scope['class'] ) ? $caller_ctx['this'] : null;
		}
		if ( T_VARIABLE === $token->id ) {
			$value = $this->evaluate( [ $token ], $caller_scope, $caller_ctx );
			if ( $value instanceof stdClass ) {
				return $value;
			}
			$class = is_string( $value ) ? $this->lookup_class( $value, $caller_scope ) : null;
			return $class ? $this->bare( $class ) : null;
		}
		$class = $this->lookup_class( $token->text, $caller_scope );
		return $class ? $this->bare( $class ) : null;
	}

	/**
	 * Objects to use as `$this` for code in a class: each concrete class that is
	 * it or extends it, as constructed wherever the plugin does `new`, and as
	 * constructed with no arguments.
	 *
	 * @return stdClass[]
	 */
	private function receivers_for( string $class ): array {
		$key = "receivers:$class";
		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}
		$this->memo[ $key ] = [ $this->bare( $class ) ]; // Guards against recursion through instantiations().

		$concrete = array_values( array_filter(
			array_merge( [ $class ], $this->descendants( $class ) ),
			fn( $c ) => empty( $this->classes[ $c ]['abstract'] ) && 'class' === ( $this->classes[ $c ]['kind'] ?? 'class' )
		) );
		$concrete = $concrete ?: [ $class ];

		$receivers = [];
		foreach ( array_slice( $concrete, 0, 50 ) as $c ) {
			foreach ( $this->instantiations( $c ) as $object ) {
				$receivers[] = $object;
			}
			// Also as built with no arguments, for classes instantiated from a list of class names.
			$receivers[] = $this->instantiate( $c, [] );
		}
		return $this->memo[ $key ] = array_slice( $receivers, 0, 60 );
	}

	/**
	 * Objects built by `new Class( ... )` anywhere in the plugin, with the
	 * arguments evaluated where they're written.
	 *
	 * @return stdClass[]
	 */
	private function instantiations( string $class ): array {
		$short   = strtolower( self::short_name( $this->classes[ $class ]['name'] ?? $class ) );
		$objects = [];
		foreach ( $this->files as $file ) {
			if ( ! isset( $this->identifiers[ $file ][ $short ] ) ) {
				continue;
			}
			$tokens = $this->tokens( $file );
			$this->spend( count( $tokens ) );
			foreach ( $tokens as $i => $token ) {
				if ( T_NEW !== $token->id || ! self::is_name( $tokens[ $i + 1 ] ?? null ) ) {
					continue;
				}
				$scope = $this->scope_at( $file, $i );
				if ( $this->lookup_class( $tokens[ $i + 1 ]->text, $scope ) !== $class ) {
					continue;
				}
				$values = [];
				if ( ( $tokens[ $i + 2 ] ?? null )?->is( '(' ) ) {
					$slices = array_map( fn( $range ) => $this->slice( $tokens, $range ), $this->split_args( $tokens, $i + 2 ) );
					$ctx    = [ 'vars' => [], 'this' => $scope['class'] ? $this->bare( $scope['class'] ) : null ];
					$values = $this->evaluate_args( $slices, $scope, $ctx );
				}
				$objects[] = $this->instantiate( $class, $values );
				if ( count( $objects ) >= 10 ) {
					return $objects;
				}
			}
		}
		return $objects;
	}

	/**
	 * Expand a context into one per iteration of each foreach loop enclosing $index.
	 *
	 * @return array[]
	 */
	private function unroll_loops( array $scope, int $index, array $ctx ): array {
		$contexts = [ $ctx ];
		foreach ( $this->enclosing_loops( $scope, $index ) as $loop ) {
			$next = [];
			foreach ( $contexts as $c ) {
				$items = $this->evaluate( $loop['expr'], $scope, $c );
				if ( ! is_array( $items ) || ! $items ) {
					// Unknown: bind the loop variables to unknown rather than looking for assignments.
					$items = [ null => null ];
				}
				foreach ( $items as $key => $item ) {
					$bound = $c;
					if ( $loop['key'] ) {
						$bound['vars'][ $loop['key'] ] = $key;
					}
					if ( $loop['value'] ) {
						$bound['vars'][ $loop['value'] ] = $item;
					}
					$next[] = $bound;
					if ( count( $next ) >= self::MAX_CONTEXTS ) {
						break 2;
					}
				}
			}
			$contexts = $next;
		}
		return $contexts;
	}

	/**
	 * The foreach loops in a scope whose body contains $index, outermost first.
	 *
	 * @return array[] Each with 'expr' (tokens), 'key' and 'value' (variable names or null).
	 */
	private function enclosing_loops( array $scope, int $index ): array {
		$tokens = $this->tokens( $scope['file'] );
		$loops  = [];
		for ( $i = $scope['start'] + 1; $i < $index; $i++ ) {
			$i = $this->skip_inner_scope( $scope, $i );
			$this->spend();
			if ( T_FOREACH !== ( $tokens[ $i ] ?? null )?->id || ! ( $tokens[ $i + 1 ] ?? null )?->is( '(' ) ) {
				continue;
			}
			$close = $this->match( $tokens, $i + 1 );
			if ( null === $close ) {
				continue;
			}

			$body_start = $close + 1;
			if ( ( $tokens[ $body_start ] ?? null )?->is( '{' ) ) {
				$body_end = $this->match( $tokens, $body_start );
			} elseif ( ( $tokens[ $body_start ] ?? null )?->is( ':' ) ) {
				$body_end = $this->find_token( $tokens, $body_start, T_ENDFOREACH );
			} else {
				$body_end = $this->statement_end( $tokens, $body_start );
			}
			if ( null === $body_end || $index <= $body_start || $index > $body_end ) {
				continue;
			}

			$as = null;
			for ( $j = $i + 2, $depth = 0; $j < $close; $j++ ) {
				if ( self::is_open( $tokens[ $j ] ) ) {
					$depth++;
				} elseif ( self::is_close( $tokens[ $j ] ) ) {
					$depth--;
				} elseif ( 0 === $depth && T_AS === $tokens[ $j ]->id ) {
					$as = $j;
					break;
				}
			}
			if ( null === $as ) {
				continue;
			}

			$target = array_values( array_filter( array_slice( $tokens, $as + 1, $close - $as - 1 ), static fn( $t ) => ! $t->is( '&' ) ) );
			$key    = null;
			$value  = $target;
			foreach ( $target as $k => $t ) {
				if ( T_DOUBLE_ARROW === $t->id ) {
					$key   = 1 === $k && T_VARIABLE === $target[0]->id ? substr( $target[0]->text, 1 ) : null;
					$value = array_slice( $target, $k + 1 );
					break;
				}
			}
			$loops[] = [
				'expr'  => array_slice( $tokens, $i + 2, $as - $i - 2 ),
				'key'   => $key,
				'value' => 1 === count( $value ) && T_VARIABLE === $value[0]->id ? substr( $value[0]->text, 1 ) : null,
			];
		}
		return $loops;
	}

	/*
	 * Evaluation.
	 */

	/**
	 * Evaluate an expression. Returns null when any part of it is unknown.
	 *
	 * @param PhpToken[] $tokens
	 * @return string|int|float|bool|array|stdClass|null
	 */
	private function evaluate( array $tokens, array $scope, array $ctx ) {
		if ( ! $tokens ) {
			return null;
		}
		$this->spend();
		if ( $this->depth >= self::MAX_DEPTH ) {
			return null;
		}
		$this->depth++;
		try {
			$i     = 0;
			$value = $this->parse_expression( $tokens, $i, $scope, $ctx );
			return count( $tokens ) === $i ? $value : null;
		} finally {
			$this->depth--;
		}
	}

	private function parse_expression( array $t, int &$i, array $scope, array $ctx ) {
		$left = $this->parse_postfix( $t, $i, $scope, $ctx );
		$n    = count( $t );
		while ( $i < $n ) {
			$token = $t[ $i ];
			if ( $token->is( '.' ) ) {
				$i++;
				$right = $this->parse_postfix( $t, $i, $scope, $ctx );
				$left  = is_scalar( $left ) && is_scalar( $right ) && strlen( (string) $left ) + strlen( (string) $right ) <= self::MAX_STRING ? $left . $right : null;
			} elseif ( T_COALESCE === $token->id ) {
				$i++;
				$right = $this->parse_expression( $t, $i, $scope, $ctx );
				$left  = $left ?? $right;
			} else {
				// Ternaries, arithmetic, comparisons: not followed.
				$i = $n;
				return null;
			}
		}
		return $left;
	}

	private function parse_postfix( array $t, int &$i, array $scope, array $ctx ) {
		$value = $this->parse_primary( $t, $i, $scope, $ctx );
		$n     = count( $t );
		while ( $i < $n ) {
			$token = $t[ $i ];
			if ( $token->is( '[' ) ) {
				$close = $this->match( $t, $i );
				if ( null === $close ) {
					$i = $n;
					return null;
				}
				$key   = $this->evaluate( array_slice( $t, $i + 1, $close - $i - 1 ), $scope, $ctx );
				$value = is_array( $value ) && ( is_string( $key ) || is_int( $key ) ) ? ( $value[ $key ] ?? null ) : null;
				$i     = $close + 1;
			} elseif ( in_array( $token->id, [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR ], true ) ) {
				$member = $t[ $i + 1 ] ?? null;
				if ( ! self::is_name( $member ) ) {
					$i = $n;
					return null;
				}
				$i += 2;
				if ( ( $t[ $i ] ?? null )?->is( '(' ) ) {
					$args  = $this->call_args( $t, $i, $scope, $ctx );
					$value = $this->call_method( $value, $member->text, $args );
				} elseif ( $value instanceof stdClass && array_key_exists( $member->text, $value->props ) ) {
					$value = $value->props[ $member->text ];
				} elseif ( $value instanceof stdClass && $value === $ctx['this'] ) {
					// Not set by the constructor: use its assignment in this method (lazy initialisation).
					$value = $this->assigned_property( $member->text, $scope, $ctx );
				} else {
					$value = null;
				}
			} elseif ( T_DOUBLE_COLON === $token->id ) {
				// `$object::CONST`, `$class_name::method()`.
				$class = $value instanceof stdClass ? $value->class : ( is_string( $value ) ? $this->lookup_class( $value, $scope ) : null );
				$i++;
				$value = $this->static_member( $class, $value instanceof stdClass ? $value : null, $t, $i, $scope, $ctx );
			} else {
				break;
			}
		}
		return $value;
	}

	private function parse_primary( array $t, int &$i, array $scope, array $ctx ) {
		$n     = count( $t );
		$token = $t[ $i ] ?? null;
		if ( ! $token ) {
			return null;
		}

		// Casts and the error-suppression operator don't change anything we care about.
		while ( $token && ( $token->is( '@' ) || in_array( $token->id, [ T_STRING_CAST, T_ARRAY_CAST, T_INT_CAST, T_BOOL_CAST, T_DOUBLE_CAST, T_OBJECT_CAST ], true ) ) ) {
			$token = $t[ ++$i ] ?? null;
		}
		if ( ! $token ) {
			return null;
		}

		switch ( true ) {
			case T_CONSTANT_ENCAPSED_STRING === $token->id:
				$i++;
				return self::unescape( $token->text );

			case T_LNUMBER === $token->id:
				$i++;
				return (int) $token->text;

			case $token->is( '(' ):
				$close = $this->match( $t, $i );
				if ( null === $close ) {
					$i = $n;
					return null;
				}
				$value = $this->evaluate( array_slice( $t, $i + 1, $close - $i - 1 ), $scope, $ctx );
				$i     = $close + 1;
				return $value;

			case $token->is( '[' ):
			case T_ARRAY === $token->id && ( $t[ $i + 1 ] ?? null )?->is( '(' ):
				$open  = $token->is( '[' ) ? $i : $i + 1;
				$close = $this->match( $t, $open );
				if ( null === $close ) {
					$i = $n;
					return null;
				}
				$value = $this->array_literal( array_slice( $t, $open + 1, $close - $open - 1 ), $scope, $ctx );
				$i     = $close + 1;
				return $value;

			case T_VARIABLE === $token->id:
				$i++;
				return '$this' === $token->text ? $ctx['this'] : $this->variable( substr( $token->text, 1 ), $scope, $ctx );

			case T_NEW === $token->id:
				$class_token = $t[ $i + 1 ] ?? null;
				$i          += 2;
				if ( T_VARIABLE === $class_token?->id ) {
					$value = $this->variable( substr( $class_token->text, 1 ), $scope, $ctx );
					$class = is_string( $value ) ? $this->lookup_class( $value, $scope ) : null;
				} elseif ( T_STATIC === $class_token?->id ) {
					$class = $ctx['this']->class ?? $scope['class'];
				} elseif ( self::is_name( $class_token ) ) {
					$class = $this->class_ref( $class_token->text, $scope, $ctx );
				} else {
					$i = $n;
					return null;
				}
				$args = ( $t[ $i ] ?? null )?->is( '(' ) ? $this->call_args( $t, $i, $scope, $ctx ) : [];
				return $class ? $this->instantiate( $class, $args ) : null;

			case T_NS_C === $token->id:
				$i++;
				return $scope['ns'];

			case T_CLASS_C === $token->id:
				$i++;
				return $scope['class'] ? $this->classes[ $scope['class'] ]['name'] : null;

			case T_STATIC === $token->id && T_DOUBLE_COLON === ( $t[ $i + 1 ] ?? null )?->id:
			case self::is_name( $token ):
				$next = $t[ $i + 1 ] ?? null;
				if ( $next?->is( '(' ) ) {
					$i++;
					$args = $this->call_args( $t, $i, $scope, $ctx );
					return $this->call_function( $token->text, $args, $scope );
				}
				if ( T_DOUBLE_COLON === $next?->id ) {
					$i     += 2;
					$lower  = strtolower( $token->text );
					$class  = $this->class_ref( $token->text, $scope, $ctx );
					$object = in_array( $lower, [ 'self', 'static', 'parent' ], true ) ? $ctx['this'] : null;
					if ( T_CLASS === ( $t[ $i ] ?? null )?->id ) {
						$i++;
						return $class ? $this->classes[ $class ]['name'] : ltrim( $token->text, '\\' );
					}
					return $this->static_member( $class, $object, $t, $i, $scope, $ctx );
				}
				$i++;
				return $this->constant( $token->text );

			default:
				// Interpolated strings, heredocs, closures, match, etc.
				$i = $n;
				return null;
		}
	}

	/**
	 * `Class::CONST` or `Class::method( ... )`, with $i just past the `::`.
	 */
	private function static_member( ?string $class, ?stdClass $object, array $t, int &$i, array $scope, array $ctx ) {
		$member = $t[ $i ] ?? null;
		if ( ! self::is_name( $member ) ) {
			$i = count( $t );
			return null;
		}
		$i++;
		if ( ( $t[ $i ] ?? null )?->is( '(' ) ) {
			$args = $this->call_args( $t, $i, $scope, $ctx );
			if ( ! $class ) {
				return null;
			}
			$receiver = $object && $this->is_a( $object->class, $class ) ? $object : $this->bare( $class );
			return $this->call_method( $receiver, $member->text, $args, $class );
		}
		return $class ? $this->class_constant( $class, $member->text ) : null;
	}

	/**
	 * Resolve `self`, `static`, `parent` or a class name to a known class.
	 */
	private function class_ref( string $name, array $scope, array $ctx ): ?string {
		switch ( strtolower( $name ) ) {
			case 'self':
				return $scope['class'];
			case 'static':
				return $ctx['this']->class ?? $scope['class'];
			case 'parent':
				return $scope['class'] ? $this->parent_of( $scope['class'] ) : null;
			default:
				return $this->lookup_class( $name, $scope );
		}
	}

	/**
	 * Evaluate a call's argument list, with $i at its `(`; leaves $i past the `)`.
	 *
	 * @return array Positional values, plus named arguments under their names.
	 */
	private function call_args( array $t, int &$i, array $scope, array $ctx ): array {
		$close = $this->match( $t, $i );
		if ( null === $close ) {
			$i = count( $t );
			return [];
		}
		$slices = array_map( static fn( $range ) => array_slice( $t, $range[0], $range[1] - $range[0] + 1 ), $this->split_args( $t, $i ) );
		$i      = $close + 1;
		return $this->evaluate_args( $slices, $scope, $ctx );
	}

	private function evaluate_args( array $slices, array $scope, array $ctx ): array {
		$values = [];
		foreach ( $slices as $slice ) {
			if ( T_ELLIPSIS === ( $slice[0] ?? null )?->id ) {
				$spread = $this->evaluate( array_slice( $slice, 1 ), $scope, $ctx );
				$spread = is_array( $spread ) && $this->array_fits( $spread ) ? $spread : [];
				foreach ( $spread as $value ) {
					$values[] = $value;
				}
			} elseif ( self::is_name( $slice[0] ?? null ) && ( $slice[1] ?? null )?->is( ':' ) ) {
				$values[ $slice[0]->text ] = $this->evaluate( array_slice( $slice, 2 ), $scope, $ctx );
			} else {
				$values[] = $this->evaluate( $slice, $scope, $ctx );
			}
		}
		return $values;
	}

	/**
	 * @param PhpToken[] $inner Tokens between the brackets.
	 */
	private function array_literal( array $inner, array $scope, array $ctx ): array {
		$result = [];
		foreach ( self::split( $inner ) as $entry ) {
			if ( T_ELLIPSIS === ( $entry[0] ?? null )?->id ) {
				$spread = $this->evaluate( array_slice( $entry, 1 ), $scope, $ctx );
				$result = is_array( $spread ) ? array_merge( $result, $spread ) : $result;
				if ( ! $this->array_fits( $result ) ) {
					return [];
				}
				continue;
			}
			$arrow = null;
			for ( $k = 0, $depth = 0, $c = count( $entry ); $k < $c; $k++ ) {
				if ( self::is_open( $entry[ $k ] ) ) {
					$depth++;
				} elseif ( self::is_close( $entry[ $k ] ) ) {
					$depth--;
				} elseif ( 0 === $depth && T_DOUBLE_ARROW === $entry[ $k ]->id ) {
					$arrow = $k;
					break;
				}
			}
			if ( null === $arrow ) {
				$result[] = $this->evaluate( $entry, $scope, $ctx );
				continue;
			}
			$key = $this->evaluate( array_slice( $entry, 0, $arrow ), $scope, $ctx );
			if ( is_string( $key ) || is_int( $key ) ) {
				$result[ $key ] = $this->evaluate( array_slice( $entry, $arrow + 1 ), $scope, $ctx );
			}
		}
		return $result;
	}

	private function call_function( string $name, array $args, array $scope ) {
		$short = strtolower( self::short_name( $name ) );

		if ( in_array( $short, self::PASSTHROUGH_FUNCTIONS, true ) ) {
			return is_scalar( $args[0] ?? null ) ? (string) $args[0] : null;
		}
		switch ( $short ) {
			case 'sprintf':
				$format = array_shift( $args );
				return is_string( $format ) ? self::format( $format, array_values( $args ) ) : null;
			case 'array_merge':
			case 'array_replace':
				// Unknown parts are treated as empty: what's known is still worth reading.
				$merged = [];
				foreach ( $args as $arg ) {
					$arg    = is_array( $arg ) ? $arg : [];
					$merged = 'array_merge' === $short ? array_merge( $merged, $arg ) : array_replace( $merged, $arg );
					if ( ! $this->array_fits( $merged ) ) {
						return null;
					}
				}
				return $merged;
			case 'wp_parse_args':
				$merged = array_merge( is_array( $args[1] ?? null ) ? $args[1] : [], is_array( $args[0] ?? null ) ? $args[0] : [] );
				return $this->array_fits( $merged ) ? $merged : null;
			case 'apply_filters':
				return $args[1] ?? null;
		}

		$function = $this->lookup_function( $name, $scope );
		return $function ? $this->invoke( $function, $args, null ) : null;
	}

	/**
	 * Call a method on an object (or class name), looking it up from class $from (default: the object's class).
	 */
	private function call_method( $target, string $name, array $args, ?string $from = null ) {
		if ( is_string( $target ) ) {
			$class  = $this->classes[ strtolower( ltrim( $target, '\\' ) ) ] ?? null;
			$target = $class ? $this->bare( strtolower( ltrim( $class['name'], '\\' ) ) ) : null;
		}
		if ( ! $target instanceof stdClass ) {
			return null;
		}
		// `self::m()` and `parent::m()` resolve from the named class; `$obj->m()` and `static::m()` from the object's.
		$method = $this->find_method( $from ?? $target->class, strtolower( $name ) );
		return $method ? $this->invoke( $method, $args, $target ) : null;
	}

	/**
	 * Evaluate what a function or method returns.
	 */
	private function invoke( array $callable, array $args, ?stdClass $this_object ) {
		$return = $this->return_expression( $callable );
		if ( null === $return ) {
			return null;
		}
		$ctx = [ 'vars' => $this->bind( $callable, $args ), 'this' => $this_object ];
		return $this->evaluate( $return, $callable, $ctx );
	}

	/**
	 * Build an object, running the constructor's promoted properties and its
	 * `$this->prop = ...;` assignments.
	 */
	private function instantiate( string $class, array $args ): stdClass {
		$object = $this->bare( $class );
		$ctor   = $this->find_method( $class, '__construct' );
		if ( ! $ctor ) {
			return $object;
		}

		$vars = $this->bind( $ctor, $args );
		foreach ( $ctor['params'] as $param ) {
			if ( $param['promoted'] ) {
				$object->props[ $param['name'] ] = $vars[ $param['name'] ] ?? null;
			}
		}

		$tokens = $this->tokens( $ctor['file'] );
		$ctx    = [ 'vars' => $vars, 'this' => $object ];
		for ( $i = $ctor['start'] + 1; $i < $ctor['end']; $i++ ) {
			$i = $this->skip_inner_scope( $ctor, $i );
			$this->spend();
			if ( '$this' === ( $tokens[ $i ] ?? null )?->text
				&& T_OBJECT_OPERATOR === ( $tokens[ $i + 1 ] ?? null )?->id
				&& self::is_name( $tokens[ $i + 2 ] ?? null )
				&& ( $tokens[ $i + 3 ] ?? null )?->is( '=' )
			) {
				$end = $this->statement_end( $tokens, $i + 4 );
				if ( null !== $end ) {
					$object->props[ $tokens[ $i + 2 ]->text ] = $this->evaluate( array_slice( $tokens, $i + 4, $end - $i - 4 ), $ctor, $ctx );
				}
			}
		}
		return $object;
	}

	/**
	 * Bind call arguments to a function's parameters, evaluating defaults for missing ones.
	 */
	private function bind( array $callable, array $args ): array {
		$vars     = [];
		$position = 0;
		foreach ( $callable['params'] as $param ) {
			$name = $param['name'];
			if ( $param['variadic'] ) {
				$vars[ $name ] = array_values( array_filter( $args, 'is_int', ARRAY_FILTER_USE_KEY ) );
				$vars[ $name ] = array_slice( $vars[ $name ], $position );
				break;
			}
			if ( array_key_exists( $name, $args ) ) {
				$vars[ $name ] = $args[ $name ];
			} elseif ( array_key_exists( $position, $args ) ) {
				$vars[ $name ] = $args[ $position ];
			} elseif ( $param['default'] ) {
				$vars[ $name ] = $this->evaluate( $this->slice( $this->tokens( $callable['file'] ), $param['default'] ), $callable, [ 'vars' => $vars, 'this' => null ] );
			} else {
				$vars[ $name ] = null;
			}
			$position++;
		}
		return $vars;
	}

	/**
	 * A variable's value: bound (parameter or loop variable), or else its first
	 * plain assignment in the scope.
	 */
	private function variable( string $name, array $scope, array $ctx ) {
		if ( array_key_exists( $name, $ctx['vars'] ) ) {
			return $ctx['vars'][ $name ];
		}
		if ( in_array( $name, array_column( $scope['params'], 'name' ), true ) ) {
			return null;
		}

		$tokens = $this->tokens( $scope['file'] );
		for ( $i = $scope['start'] + 1, $end = min( $scope['end'], count( $tokens ) ); $i < $end; $i++ ) {
			$i = $this->skip_inner_scope( $scope, $i );
			$this->spend();
			if ( T_VARIABLE !== ( $tokens[ $i ] ?? null )?->id || "\$$name" !== $tokens[ $i ]->text || ! ( $tokens[ $i + 1 ] ?? null )?->is( '=' ) ) {
				continue;
			}
			$prev = $tokens[ $i - 1 ] ?? null;
			if ( $prev && T_DOUBLE_COLON === $prev->id ) {
				continue;
			}
			$stop = $this->statement_end( $tokens, $i + 2 );
			if ( null === $stop ) {
				return null;
			}
			// Bind it to unknown while evaluating, so `$x = f( $x )` can't recurse.
			$ctx['vars'][ $name ] = null;
			return $this->evaluate( array_slice( $tokens, $i + 2, $stop - $i - 2 ), $scope, $ctx );
		}
		return null;
	}

	/**
	 * The value of the first `$this->name = ...;` in a scope, for properties
	 * the constructor didn't set, e.g. `$this->cache ??= $this->build(); return $this->cache;`.
	 */
	private function assigned_property( string $name, array $scope, array $ctx ) {
		$tokens = $this->tokens( $scope['file'] );
		for ( $i = $scope['start'] + 1, $end = min( $scope['end'], count( $tokens ) ); $i < $end; $i++ ) {
			$i = $this->skip_inner_scope( $scope, $i );
			$this->spend();
			if ( '$this' !== ( $tokens[ $i ] ?? null )?->text
				|| T_OBJECT_OPERATOR !== ( $tokens[ $i + 1 ] ?? null )?->id
				|| $name !== ( $tokens[ $i + 2 ] ?? null )?->text
			) {
				continue;
			}
			$op = $tokens[ $i + 3 ] ?? null;
			if ( ! $op || ( ! $op->is( '=' ) && T_COALESCE_EQUAL !== $op->id ) ) {
				continue;
			}
			$stop = $this->statement_end( $tokens, $i + 4 );
			if ( null === $stop ) {
				return null;
			}
			// Mark it unknown while evaluating, so `$this->x = f( $this->x )` can't recurse.
			$ctx['this']->props[ $name ] = null;
			$value = $this->evaluate( array_slice( $tokens, $i + 4, $stop - $i - 4 ), $scope, $ctx );
			unset( $ctx['this']->props[ $name ] );
			return $value;
		}
		return null;
	}

	private function constant( string $name ) {
		$name  = ltrim( $name, '\\' );
		$lower = strtolower( $name );
		if ( 'true' === $lower ) {
			return true;
		}
		if ( 'false' === $lower ) {
			return false;
		}
		if ( ! isset( $this->defines[ $name ] ) ) {
			return null;
		}
		[ $file, $range ] = $this->defines[ $name ];
		$key = "define:$name";
		if ( array_key_exists( $key, $this->memo ) ) {
			return $this->memo[ $key ];
		}
		$this->memo[ $key ] = null;
		return $this->memo[ $key ] = $this->evaluate( $this->slice( $this->tokens( $file ), $range ), $this->scope_at( $file, $range[0] ), [ 'vars' => [], 'this' => null ] );
	}

	private function class_constant( string $class, string $name ) {
		for ( $c = $class, $hops = 0; $c && $hops < 20; $c = $this->parent_of( $c ), $hops++ ) {
			$range = $this->classes[ $c ]['consts'][ $name ] ?? null;
			if ( ! $range ) {
				continue;
			}
			$key = "const:$c::$name";
			if ( array_key_exists( $key, $this->memo ) ) {
				return $this->memo[ $key ];
			}
			$this->memo[ $key ] = null;
			$file  = $this->classes[ $c ]['file'];
			$scope = $this->scope_at( $file, $range[0] );
			$scope['class'] = $c;
			return $this->memo[ $key ] = $this->evaluate( $this->slice( $this->tokens( $file ), $range ), $scope, [ 'vars' => [], 'this' => null ] );
		}
		return null;
	}

	/**
	 * The expression a function returns: its only `return`, or the last one in its outermost block.
	 *
	 * @return PhpToken[]|null
	 */
	private function return_expression( array $callable ): ?array {
		$key = "return:{$callable['file']}:{$callable['start']}";
		if ( array_key_exists( $key, $this->memo ) ) {
			return $this->memo[ $key ];
		}

		$tokens  = $this->tokens( $callable['file'] );
		$returns = [];
		for ( $i = $callable['start'] + 1, $depth = 0; $i < $callable['end']; $i++ ) {
			$this->spend();
			$skipped = $this->skip_inner_scope( $callable, $i );
			if ( $skipped !== $i ) {
				$i = $skipped;
				continue;
			}
			if ( self::is_open( $tokens[ $i ] ) ) {
				$depth++;
			} elseif ( self::is_close( $tokens[ $i ] ) ) {
				$depth--;
			} elseif ( T_RETURN === $tokens[ $i ]->id ) {
				$returns[] = [ $i, $depth ];
			}
		}
		if ( ! $returns ) {
			return $this->memo[ $key ] = null;
		}

		$top    = array_filter( $returns, static fn( $r ) => 0 === $r[1] );
		[ $at ] = $top ? end( $top ) : end( $returns );
		$end    = $this->statement_end( $tokens, $at + 1 );
		return $this->memo[ $key ] = null === $end ? null : array_slice( $tokens, $at + 1, $end - $at - 1 );
	}

	/*
	 * Classes and functions.
	 */

	private function bare( string $class ): stdClass {
		$object        = new stdClass();
		$object->class = $class;
		$object->props = [];
		return $object;
	}

	private function find_method( string $class, string $name ): ?array {
		for ( $c = $class, $hops = 0; $c && $hops < 20; $c = $this->parent_of( $c ), $hops++ ) {
			if ( isset( $this->classes[ $c ]['methods'][ $name ] ) ) {
				return $this->classes[ $c ]['methods'][ $name ];
			}
		}
		return null;
	}

	private function parent_of( string $class ): ?string {
		$info = $this->classes[ $class ] ?? null;
		if ( ! $info || ! $info['parent'] ) {
			return null;
		}
		$key = "parent:$class";
		if ( ! array_key_exists( $key, $this->memo ) ) {
			$parent             = $this->lookup_class( $info['parent'], [ 'ns' => $info['ns'], 'uses' => $info['uses'] ] );
			$this->memo[ $key ] = $parent !== $class ? $parent : null;
		}
		return $this->memo[ $key ];
	}

	private function is_a( ?string $class, ?string $ancestor ): bool {
		for ( $c = $class, $hops = 0; $c && $hops < 20; $c = $this->parent_of( $c ), $hops++ ) {
			if ( $c === $ancestor ) {
				return true;
			}
		}
		return false;
	}

	private function descendants( string $class ): array {
		$found = [];
		$queue = $this->children[ $class ] ?? [];
		while ( $queue && count( $found ) < 500 ) {
			$c = array_shift( $queue );
			if ( ! in_array( $c, $found, true ) ) {
				$found[] = $c;
				$queue   = array_merge( $queue, $this->children[ $c ] ?? [] );
			}
		}
		return $found;
	}

	/**
	 * Resolve a class name as written in a scope to a known class (lowercase FQN).
	 */
	private function lookup_class( string $name, array $scope ): ?string {
		$fqn = $this->qualify( $name, $scope );
		if ( isset( $this->classes[ $fqn ] ) ) {
			return $fqn;
		}
		$candidates = $this->short_names[ strtolower( self::short_name( $name ) ) ] ?? [];
		return 1 === count( $candidates ) ? $candidates[0] : null;
	}

	private function lookup_function( string $name, array $scope ): ?array {
		$fqn = $this->qualify( $name, $scope );
		if ( isset( $this->functions[ $fqn ] ) ) {
			return $this->functions[ $fqn ];
		}
		// Unqualified calls fall back to the global function.
		$global = strtolower( self::short_name( $name ) );
		return ! str_contains( ltrim( $name, '\\' ), '\\' ) ? ( $this->functions[ $global ] ?? null ) : null;
	}

	/**
	 * The lowercase fully-qualified form of a name, using the scope's namespace and imports.
	 */
	private function qualify( string $name, array $scope ): string {
		if ( str_starts_with( $name, '\\' ) ) {
			return strtolower( ltrim( $name, '\\' ) );
		}
		$parts = explode( '\\', $name );
		$alias = strtolower( $parts[0] );
		if ( isset( $scope['uses'][ $alias ] ) ) {
			$parts[0] = $scope['uses'][ $alias ];
			return strtolower( implode( '\\', $parts ) );
		}
		return strtolower( ltrim( $scope['ns'] . '\\' . $name, '\\' ) );
	}

	/*
	 * Indexing.
	 */

	private function index( string $file ): void {
		$parsed = $this->parse( $file );
		$this->meta[ $file ] = [
			'scopes'     => $parsed['scopes'],
			'scope_ends' => array_column( $parsed['scopes'], 'end', 'start' ),
			'namespaces' => $parsed['namespaces'],
		];
		$this->identifiers[ $file ] = $parsed['identifiers'];
		foreach ( $parsed['classes'] as $lname => $class ) {
			$this->classes[ $lname ] = $class;
			$this->short_names[ strtolower( self::short_name( $class['name'] ) ) ][] = $lname;
		}
		$this->functions += $parsed['functions'];
		$this->defines   += $parsed['defines'];
		foreach ( $parsed['sites'] as $index ) {
			$this->sites[] = [ $file, $index ];
		}
	}

	/**
	 * Tokenise a file and outline it: namespaces and imports, classes with their
	 * constants and methods, functions, closures, define() calls and
	 * wp_register_ability() call sites.
	 */
	private function parse( string $file ): array {
		$tokens = array_values( array_filter(
			PhpToken::tokenize( (string) file_get_contents( $file ) ),
			static fn( PhpToken $t ) => ! $t->isIgnorable()
		) );
		$this->remember( $file, $tokens );

		$count       = count( $tokens );
		$namespaces  = [ [ 'start' => 0, 'ns' => '', 'uses' => [] ] ];
		$ns_end      = null;
		$classes     = [];
		$functions   = [];
		$defines     = [];
		$sites       = [];
		$scopes      = [];
		$identifiers = [];
		$class_stack = [];
		$fn_stack    = [];

		for ( $i = 0; $i < $count; $i++ ) {
			while ( $class_stack && end( $class_stack )['end'] < $i ) {
				array_pop( $class_stack );
			}
			while ( $fn_stack && end( $fn_stack ) < $i ) {
				array_pop( $fn_stack );
			}
			if ( null !== $ns_end && $i > $ns_end ) {
				$namespaces[] = [ 'start' => $i, 'ns' => '', 'uses' => [] ];
				$ns_end       = null;
			}

			$token = $tokens[ $i ];
			$prev  = $tokens[ $i - 1 ] ?? null;
			$class = $class_stack ? end( $class_stack ) : null;
			// Directly in a class body, not inside one of its methods.
			$in_class_body = $class && ( ! $fn_stack || end( $fn_stack ) > $class['end'] || $this->fn_started_before( $fn_stack, $scopes, $class['open'] ) );

			if ( self::is_name( $token ) ) {
				$identifiers[ strtolower( self::short_name( $token->text ) ) ] = true;
			}

			switch ( $token->id ) {
				case T_NAMESPACE:
					$next = $tokens[ $i + 1 ] ?? null;
					if ( $next && ( T_STRING === $next->id || T_NAME_QUALIFIED === $next->id ) ) {
						$namespaces[] = [ 'start' => $i, 'ns' => $next->text, 'uses' => [] ];
						if ( ( $tokens[ $i + 2 ] ?? null )?->is( '{' ) ) {
							$ns_end = $this->match( $tokens, $i + 2 );
						}
					} elseif ( $next?->is( '{' ) ) {
						$namespaces[] = [ 'start' => $i, 'ns' => '', 'uses' => [] ];
						$ns_end       = $this->match( $tokens, $i + 1 );
					}
					break;

				case T_USE:
					if ( ! $class_stack && ! $fn_stack ) {
						$last = count( $namespaces ) - 1;
						$namespaces[ $last ]['uses'] = array_merge( $namespaces[ $last ]['uses'], $this->parse_use( $tokens, $i ) );
					}
					break;

				case T_CLASS:
				case T_INTERFACE:
				case T_TRAIT:
				case T_ENUM:
					if ( $prev && in_array( $prev->id, [ T_DOUBLE_COLON, T_NEW ], true ) ) {
						break;
					}
					$name_token = $tokens[ $i + 1 ] ?? null;
					$open       = $this->find_open_brace( $tokens, $i );
					if ( T_STRING !== $name_token?->id || null === $open ) {
						break;
					}
					$end    = $this->match( $tokens, $open );
					$ns     = end( $namespaces );
					$fqn    = ltrim( $ns['ns'] . '\\' . $name_token->text, '\\' );
					$lname  = strtolower( $fqn );
					$parent = null;
					for ( $j = $i + 2; $j < $open; $j++ ) {
						if ( T_EXTENDS === $tokens[ $j ]->id && self::is_name( $tokens[ $j + 1 ] ?? null ) ) {
							$parent = $tokens[ $j + 1 ]->text;
							break;
						}
					}
					$abstract = false;
					for ( $j = $i - 1; $j >= 0 && in_array( $tokens[ $j ]->id, [ T_ABSTRACT, T_FINAL, T_READONLY ], true ); $j-- ) {
						$abstract = $abstract || T_ABSTRACT === $tokens[ $j ]->id;
					}
					$classes[ $lname ] = [
						'name'     => $fqn,
						'file'     => $file,
						'kind'     => strtolower( $token->text ),
						'abstract' => $abstract,
						'parent'   => $parent,
						'ns'       => $ns['ns'],
						'uses'     => $ns['uses'],
						'consts'   => [],
						'methods'  => [],
					];
					$class_stack[] = [ 'lname' => $lname, 'open' => $open, 'end' => $end ?? $count ];
					break;

				case T_CONST:
					if ( $class && $in_class_body ) {
						$end = $this->statement_end( $tokens, $i + 1 );
						foreach ( self::split_ranges( $tokens, $i + 1, ( $end ?? $count ) - 1 ) as [ $s, $e ] ) {
							for ( $j = $s; $j <= $e; $j++ ) {
								if ( $tokens[ $j ]->is( '=' ) && $j > $s && T_STRING === $tokens[ $j - 1 ]->id ) {
									$classes[ $class['lname'] ]['consts'][ $tokens[ $j - 1 ]->text ] = [ $j + 1, $e ];
									break;
								}
							}
						}
					}
					break;

				case T_FUNCTION:
					$j = $i + 1;
					if ( ( $tokens[ $j ] ?? null )?->is( '&' ) ) {
						$j++;
					}
					$name = null;
					if ( ! ( $tokens[ $j ] ?? null )?->is( '(' ) ) {
						if ( ! ( $tokens[ $j + 1 ] ?? null )?->is( '(' ) ) {
							break;
						}
						$name = $tokens[ $j ]->text;
						$j++;
					}
					$close = $this->match( $tokens, $j );
					if ( null === $close ) {
						break;
					}
					$params = $this->parse_params( $tokens, $j, $close );
					$open   = null;
					for ( $k = $close + 1; $k < $count; $k++ ) {
						if ( $tokens[ $k ]->is( '(' ) ) {
							$k = $this->match( $tokens, $k ) ?? $count;
						} elseif ( $tokens[ $k ]->is( '{' ) ) {
							$open = $k;
							break;
						} elseif ( $tokens[ $k ]->is( ';' ) || T_DOUBLE_ARROW === $tokens[ $k ]->id ) {
							break;
						}
					}
					if ( null === $open ) {
						break; // Abstract or interface method.
					}
					$end   = $this->match( $tokens, $open ) ?? $count;
					$kind  = null === $name ? 'closure' : ( $class && $in_class_body ? 'method' : 'function' );
					$ns    = end( $namespaces );
					$scope = [
						'file'   => $file,
						'start'  => $open,
						'end'    => $end,
						'params' => $params,
						'name'   => $name,
						'kind'   => $kind,
						'class'  => $class['lname'] ?? null,
						'ns'     => $ns['ns'],
						'uses'   => $ns['uses'],
					];
					$scopes[] = $scope;
					if ( 'method' === $kind ) {
						$classes[ $class['lname'] ]['methods'][ strtolower( $name ) ] = $scope;
					} elseif ( 'function' === $kind ) {
						$scope['class'] = null;
						$functions[ strtolower( ltrim( $ns['ns'] . '\\' . $name, '\\' ) ) ] = $scope;
					}
					$fn_stack[] = $end;
					break;

				case T_STRING:
				case T_NAME_FULLY_QUALIFIED:
					$lower = strtolower( ltrim( $token->text, '\\' ) );
					if ( ! ( $tokens[ $i + 1 ] ?? null )?->is( '(' ) ) {
						break;
					}
					$is_call = ! $prev || ! in_array( $prev->id, [ T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW ], true );
					if ( 'wp_register_ability' === $lower && $is_call ) {
						$sites[] = $i;
					} elseif ( 'register' === $lower && $this->is_registry_call( $tokens, $i ) ) {
						$sites[] = $i;
					} elseif ( 'define' === $lower && $is_call
						&& T_CONSTANT_ENCAPSED_STRING === ( $tokens[ $i + 2 ] ?? null )?->id
						&& ( $tokens[ $i + 3 ] ?? null )?->is( ',' )
					) {
						$args = $this->split_args( $tokens, $i + 1 );
						if ( isset( $args[1] ) ) {
							$defines[ self::unescape( $tokens[ $i + 2 ]->text ) ] = [ $file, $args[1] ];
						}
					}
					break;
			}
		}

		return compact( 'namespaces', 'classes', 'functions', 'defines', 'sites', 'scopes', 'identifiers' );
	}

	/**
	 * Whether the innermost open function started before the class body opened,
	 * i.e. the class is declared inside that function and we're in its body.
	 */
	private function fn_started_before( array $fn_stack, array $scopes, int $class_open ): bool {
		$end = end( $fn_stack );
		foreach ( array_reverse( $scopes ) as $scope ) {
			if ( $scope['end'] === $end ) {
				return $scope['start'] < $class_open;
			}
		}
		return false;
	}

	/**
	 * `WP_Abilities_Registry::get_instance()->register(`, with $i at `register`.
	 */
	private function is_registry_call( array $tokens, int $i ): bool {
		return T_OBJECT_OPERATOR === ( $tokens[ $i - 1 ] ?? null )?->id
			&& ( $tokens[ $i - 2 ] ?? null )?->is( ')' )
			&& ( $tokens[ $i - 3 ] ?? null )?->is( '(' )
			&& 'get_instance' === strtolower( $tokens[ $i - 4 ]->text ?? '' )
			&& T_DOUBLE_COLON === ( $tokens[ $i - 5 ] ?? null )?->id
			&& 'wp_abilities_registry' === strtolower( self::short_name( $tokens[ $i - 6 ]->text ?? '' ) );
	}

	/**
	 * Imports from a top-level `use` statement: alias (lowercase) => fully-qualified name.
	 */
	private function parse_use( array $tokens, int $i ): array {
		$next = $tokens[ $i + 1 ] ?? null;
		if ( ! $next || in_array( $next->id, [ T_FUNCTION, T_CONST ], true ) ) {
			return [];
		}
		$end = $this->statement_end( $tokens, $i + 1 );
		if ( null === $end ) {
			return [];
		}

		$imports = [];
		$prefix  = '';
		$clause  = [];
		$flush   = static function () use ( &$clause, &$imports, &$prefix ) {
			if ( ! $clause ) {
				return;
			}
			$alias = null;
			$name  = '';
			foreach ( $clause as $k => $t ) {
				if ( T_AS === $t->id ) {
					$alias = $clause[ $k + 1 ]->text ?? null;
					break;
				}
				$name .= $t->text;
			}
			$fqn = ltrim( ( $prefix ? $prefix . '\\' : '' ) . ltrim( $name, '\\' ), '\\' );
			$imports[ strtolower( $alias ?? self::short_name( $fqn ) ) ] = $fqn;
			$clause = [];
		};
		for ( $j = $i + 1; $j < $end; $j++ ) {
			$t = $tokens[ $j ];
			if ( $t->is( '{' ) ) {
				$prefix = rtrim( implode( '', array_map( static fn( $c ) => $c->text, $clause ) ), '\\' );
				$clause = [];
			} elseif ( $t->is( ',' ) || $t->is( '}' ) ) {
				$flush();
			} else {
				$clause[] = $t;
			}
		}
		$flush();
		return $imports;
	}

	private function parse_params( array $tokens, int $open, int $close ): array {
		$params = [];
		foreach ( self::split_ranges( $tokens, $open + 1, $close - 1 ) as [ $s, $e ] ) {
			$param = [ 'name' => null, 'promoted' => false, 'variadic' => false, 'default' => null ];
			for ( $j = $s; $j <= $e; $j++ ) {
				$t = $tokens[ $j ];
				if ( in_array( $t->id, [ T_PUBLIC, T_PROTECTED, T_PRIVATE, T_READONLY ], true ) ) {
					$param['promoted'] = true;
				} elseif ( T_ELLIPSIS === $t->id ) {
					$param['variadic'] = true;
				} elseif ( T_VARIABLE === $t->id && null === $param['name'] ) {
					$param['name'] = substr( $t->text, 1 );
					if ( ( $tokens[ $j + 1 ] ?? null )?->is( '=' ) && $j + 2 <= $e ) {
						$param['default'] = [ $j + 2, $e ];
					}
					break;
				}
			}
			if ( null !== $param['name'] ) {
				$params[] = $param;
			}
		}
		return $params;
	}

	/*
	 * Scopes and tokens.
	 */

	/**
	 * The innermost function, method or closure containing $index, or the file's top level.
	 */
	private function scope_at( string $file, int $index ): array {
		$this->spend( count( $this->meta[ $file ]['scopes'] ) );
		$best = null;
		foreach ( $this->meta[ $file ]['scopes'] as $scope ) {
			if ( $scope['start'] < $index && $index < $scope['end'] && ( ! $best || $scope['start'] > $best['start'] ) ) {
				$best = $scope;
			}
		}
		if ( $best ) {
			return $best;
		}

		$ns = $this->meta[ $file ]['namespaces'][0];
		foreach ( $this->meta[ $file ]['namespaces'] as $segment ) {
			if ( $segment['start'] <= $index ) {
				$ns = $segment;
			}
		}
		return [
			'file'   => $file,
			'start'  => -1,
			'end'    => PHP_INT_MAX,
			'params' => [],
			'name'   => null,
			'kind'   => 'top',
			'class'  => null,
			'ns'     => $ns['ns'],
			'uses'   => $ns['uses'],
		];
	}

	/**
	 * If $i starts a function nested inside $scope, the index of its closing brace; else $i.
	 */
	private function skip_inner_scope( array $scope, int $i ): int {
		$end = $this->meta[ $scope['file'] ]['scope_ends'][ $i ] ?? null;
		return null !== $end && $i > $scope['start'] && $end < $scope['end'] ? $end : $i;
	}

	/**
	 * @return PhpToken[] Significant tokens of a file.
	 */
	private function tokens( string $file ): array {
		if ( isset( $this->cache[ $file ] ) ) {
			return $this->cache[ $file ];
		}
		$tokens = array_values( array_filter(
			PhpToken::tokenize( (string) file_get_contents( $file ) ),
			static fn( PhpToken $t ) => ! $t->isIgnorable()
		) );
		$this->remember( $file, $tokens );
		return $tokens;
	}

	private function remember( string $file, array $tokens ): void {
		if ( count( $this->cache ) >= self::CACHE_SIZE ) {
			array_shift( $this->cache );
		}
		$this->cache[ $file ] = $tokens;
		$this->pair( $tokens );
	}

	private function slice( array $tokens, array $range ): array {
		return array_slice( $tokens, $range[0], $range[1] - $range[0] + 1 );
	}

	/**
	 * The `[start, end]` ranges of a call's arguments, given the index of its `(`.
	 */
	private function split_args( array $tokens, int $open ): array {
		$close = $this->match( $tokens, $open );
		return null === $close ? [] : self::split_ranges( $tokens, $open + 1, $close - 1 );
	}

	/**
	 * Index of the `;` (or `?>`) ending the statement that starts at $from, or
	 * of the bracket closing the expression it's in.
	 */
	private function statement_end( array $tokens, int $from ): ?int {
		for ( $i = $from, $n = count( $tokens ); $i < $n; $i++ ) {
			$this->spend();
			$t = $tokens[ $i ];
			if ( self::is_open( $t ) ) {
				$close = $this->match( $tokens, $i );
				if ( null === $close ) {
					return null;
				}
				$i = $close;
			} elseif ( self::is_close( $t ) || $t->is( ';' ) || T_CLOSE_TAG === $t->id ) {
				return $i;
			}
		}
		return null;
	}

	private function find_token( array $tokens, int $from, int $id ): ?int {
		for ( $i = $from, $n = count( $tokens ); $i < $n; $i++ ) {
			if ( $id === $tokens[ $i ]->id ) {
				return $i;
			}
		}
		return null;
	}

	private function find_open_brace( array $tokens, int $from ): ?int {
		for ( $i = $from, $n = count( $tokens ); $i < $n; $i++ ) {
			if ( $tokens[ $i ]->is( '{' ) ) {
				return $i;
			}
			if ( $tokens[ $i ]->is( ';' ) ) {
				return null;
			}
		}
		return null;
	}

	/**
	 * Index of the bracket closing the one at $open, in a file's tokens or any slice of them.
	 */
	private function match( array $tokens, int $open ): ?int {
		$token = $tokens[ $open ] ?? null;
		$span  = $token ? ( $this->spans[ $token ] ?? null ) : null;
		return null !== $span && isset( $tokens[ $open + $span ] ) && $tokens[ $open + $span ] === ( $this->closers[ $token ] ?? null ) ? $open + $span : null;
	}

	/**
	 * Record, for each opening bracket in a file, the distance to its closing
	 * bracket, so matching is a lookup rather than a scan. Tokens are shared
	 * between a file's list and its slices, so this works for both.
	 */
	private function pair( array $tokens ): void {
		$stack = [];
		foreach ( $tokens as $i => $token ) {
			if ( self::is_open( $token ) ) {
				$stack[] = $i;
			} elseif ( self::is_close( $token ) && $stack ) {
				$open                              = array_pop( $stack );
				$this->spans[ $tokens[ $open ] ]   = $i - $open;
				$this->closers[ $tokens[ $open ] ] = $token;
			}
		}
	}

	/**
	 * Split tokens $from..$to (inclusive) at top-level commas into `[start, end]` ranges.
	 */
	private static function split_ranges( array $tokens, int $from, int $to ): array {
		$ranges = [];
		$start  = $from;
		for ( $i = $from, $depth = 0; $i <= $to; $i++ ) {
			$t = $tokens[ $i ];
			if ( self::is_open( $t ) ) {
				$depth++;
			} elseif ( self::is_close( $t ) ) {
				$depth--;
			} elseif ( 0 === $depth && $t->is( ',' ) ) {
				if ( $i > $start ) {
					$ranges[] = [ $start, $i - 1 ];
				}
				$start = $i + 1;
			}
		}
		if ( $to >= $start ) {
			$ranges[] = [ $start, $to ];
		}
		return $ranges;
	}

	/**
	 * @return PhpToken[][]
	 */
	private static function split( array $tokens ): array {
		return array_map(
			static fn( $range ) => array_slice( $tokens, $range[0], $range[1] - $range[0] + 1 ),
			self::split_ranges( $tokens, 0, count( $tokens ) - 1 )
		);
	}

	private static function is_open( PhpToken $t ): bool {
		return $t->is( [ '(', '[', '{' ] ) || in_array( $t->id, [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE ], true );
	}

	private static function is_close( PhpToken $t ): bool {
		return $t->is( [ ')', ']', '}' ] );
	}

	private static function is_name( ?PhpToken $t ): bool {
		return $t && in_array( $t->id, [ T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ], true );
	}

	/**
	 * A minimal sprintf(): only `%s`, `%d`, `%%` and positional `%1$s` / `%1$d`.
	 * Anything else (widths, precisions, padding, other conversions) is
	 * unknown, so a format can never be used to build a large string.
	 */
	private static function format( string $format, array $args ): ?string {
		if ( strlen( $format ) > self::MAX_STRING ) {
			return null;
		}
		$next   = 0;
		$failed = false;
		$result = preg_replace_callback(
			'/%(?:(\d{1,2})\$)?(.?)/s',
			static function ( $m ) use ( $args, &$next, &$failed ) {
				if ( '%' === $m[2] && '' === $m[1] ) {
					return '%';
				}
				if ( 's' !== $m[2] && 'd' !== $m[2] ) {
					$failed = true;
					return '';
				}
				$index = '' === $m[1] ? $next++ : (int) $m[1] - 1;
				$value = $args[ $index ] ?? null;
				if ( ! is_scalar( $value ) ) {
					$failed = true;
					return '';
				}
				return 'd' === $m[2] ? (string) (int) $value : (string) $value;
			},
			$format
		);
		return $failed || null === $result || strlen( $result ) > self::MAX_STRING ? null : $result;
	}

	private static function short_name( string $name ): string {
		$pos = strrpos( $name, '\\' );
		return false === $pos ? $name : substr( $name, $pos + 1 );
	}

	private static function unescape( string $literal ): string {
		if ( strlen( $literal ) < 2 ) {
			return $literal;
		}
		$body = substr( $literal, 1, -1 );
		return "'" === $literal[0] ? str_replace( [ "\\'", '\\\\' ], [ "'", '\\' ], $body ) : stripcslashes( $body );
	}

	/*
	 * Ability records.
	 */

	/**
	 * Find array literals in PHP source that look like ability definitions.
	 *
	 * Two shapes are recognised, both requiring a 'description' key (core
	 * requires one to register an ability; 'label' is optional because some
	 * registrars fall back to the name):
	 *
	 * - Keyed by name:  'ns/name' => array( 'label' => ..., 'description' => ... ),
	 *                   or $defs['ns/name'] = array( 'label' => ..., 'description' => ... );
	 * - Name property:  array( 'name' => 'ns/name', 'label' => ..., 'description' => ... )
	 *
	 * Names in the core/ namespace are skipped; they belong to WordPress, and
	 * arrays keyed by core block names are a common false positive.
	 *
	 * @param string   $contents PHP source code.
	 * @param int|null $budget   Optional. Work allowed, in tokens; reduced by the work done.
	 * @return object[]
	 */
	public static function find_definitions( string $contents, ?int &$budget = null ): array {
		if ( false === stripos( $contents, 'description' ) ) {
			return [];
		}

		$is_definition = static fn( $value ) => is_array( $value ) && array_key_exists( 'description', $value );

		$abilities = [];
		foreach ( Tokenisation_Helpers::find_array_literals( $contents, $budget ) as $array ) {
			if ( $is_definition( $array ) && is_string( $array['name'] ?? null ) ) {
				$abilities[] = self::make_ability( $array['name'], $array );
			}
			foreach ( $array as $key => $value ) {
				if ( $is_definition( $value ) ) {
					$abilities[] = self::make_ability( (string) $key, $value );
				}
			}
		}

		return array_values( array_filter(
			$abilities,
			static fn( $ability ) => $ability && ! str_starts_with( $ability->name, 'core/' )
		) );
	}

	/**
	 * Build an ability record from a name and its args, as passed to wp_register_ability().
	 *
	 * @return object|null The ability, or null if the name isn't one core would accept.
	 */
	public static function make_ability( $name, $args ): ?object {
		if ( ! is_string( $name ) || ! preg_match( self::NAME_PATTERN, $name ) ) {
			return null;
		}
		$args = is_array( $args ) ? $args : [];
		return (object) [
			'name'        => $name,
			'label'       => is_string( $args['label'] ?? null ) ? $args['label'] : null,
			'description' => is_string( $args['description'] ?? null ) ? $args['description'] : null,
		];
	}

	/**
	 * Collapse abilities found in several places into one entry per name. The
	 * first one found wins, with missing properties filled in from later ones.
	 *
	 * @param object[] $abilities
	 * @return object[] Unique abilities, in the order first seen.
	 */
	public static function merge( array $abilities ): array {
		$merged = [];
		foreach ( array_filter( $abilities ) as $ability ) {
			if ( ! isset( $merged[ $ability->name ] ) ) {
				$merged[ $ability->name ] = clone $ability;
				continue;
			}
			foreach ( get_object_vars( $ability ) as $prop => $value ) {
				if ( null === ( $merged[ $ability->name ]->$prop ?? null ) ) {
					$merged[ $ability->name ]->$prop = $value;
				}
			}
		}
		return array_values( $merged );
	}
}

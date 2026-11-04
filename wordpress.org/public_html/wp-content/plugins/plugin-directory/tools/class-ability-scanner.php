<?php
/**
 * Ability detection for the plugin importer.
 *
 * @package WordPressdotorg\Plugin_Directory\Tools
 */

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

	/** The ability category slug format core accepts. */
	const CATEGORY_PATTERN = '#^[a-z0-9]+(?:-[a-z0-9]+)*$#';

	/** Directories holding bundled third-party code. Abilities found in them are flagged as bundled. */
	const BUNDLED_DIRECTORIES = [ 'vendor', 'vendor-prefixed', 'vendor_prefixed', 'jetpack_vendor' ];

	/** Nested evaluations (calls, constants, variables) before giving up on a value. */
	private const MAX_DEPTH = 24;

	/** Wrapper functions followed back through their callers. */
	private const MAX_CALL_DEPTH = 4;

	/**
	 * Work allowed for a whole plugin: one unit per expression evaluated and per
	 * token scanned while evaluating, so a pathological plugin can't stall the import.
	 */
	private const BUDGET = 5000000;

	/**
	 * Work allowed per wp_register_ability() call, and again per context it's
	 * evaluated in, so one expensive call can't starve the rest.
	 */
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

	/** Objects a constructor may build in turn (e.g. dependency injection containers), before they're left unconstructed. */
	private const MAX_CONSTRUCTION_DEPTH = 3;

	/** Tokenised files kept in memory. */
	private const CACHE_SIZE = 64;

	/** Functions that return their first argument, translated. */
	private const PASSTHROUGH_FUNCTIONS = [
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
		'esc_html',
		'esc_attr',
		'trim',
		'strval',
	];

	/**
	 * The plugin's PHP files, sorted.
	 *
	 * @var string[]
	 */
	private array $files = [];
	/**
	 * The plugin's root directory, for reporting paths relative to it.
	 *
	 * @var string
	 */
	private string $base_dir = '';
	/**
	 * Tokenised files, by path; the least recently added are evicted.
	 *
	 * @var array
	 */
	private array $cache = [];
	/**
	 * Per file: its functions ('scopes'), their closing indexes ('scope_ends') and namespaces.
	 *
	 * @var array
	 */
	private array $meta = [];
	/**
	 * Per file: the lowercase names that appear in it, for finding call sites quickly.
	 *
	 * @var array
	 */
	private array $identifiers = [];
	/**
	 * Known classes, by lowercase fully-qualified name.
	 *
	 * @var array
	 */
	private array $classes = [];
	/**
	 * Known classes by lowercase short name.
	 *
	 * @var array
	 */
	private array $short_names = [];
	/**
	 * Direct subclasses, by parent class.
	 *
	 * @var array
	 */
	private array $children = [];
	/**
	 * The plugin's functions, by lowercase fully-qualified name.
	 *
	 * @var array
	 */
	private array $functions = [];
	/**
	 * Constants set with define(): name => [ file, value range ].
	 *
	 * @var array
	 */
	private array $defines = [];
	/**
	 * Calls to wp_register_ability(): [ file, index ].
	 *
	 * @var array
	 */
	private array $sites = [];
	/**
	 * Calls to wp_register_ability_category(): [ file, index ].
	 *
	 * @var array
	 */
	private array $category_sites = [];
	/**
	 * Filter callbacks added with add_filter(): hook => list of [ file, callback range ].
	 *
	 * @var array
	 */
	private array $filters = [];
	/**
	 * Memoised lookups (constants, call sites, receivers, returns).
	 *
	 * @var array
	 */
	private array $memo = [];
	/**
	 * Work left for the whole plugin.
	 *
	 * @var int
	 */
	private int $budget = self::BUDGET;
	/**
	 * Work left for the current wp_register_ability() call.
	 *
	 * @var int
	 */
	private int $site_budget = self::SITE_BUDGET;
	/**
	 * Current nesting of evaluations.
	 *
	 * @var int
	 */
	private int $depth = 0;
	/**
	 * Constructors currently running, nested.
	 *
	 * @var int
	 */
	private int $constructing = 0;
	/**
	 * Whether work is being charged (it isn't while indexing).
	 *
	 * @var bool
	 */
	private bool $evaluating = false;
	/**
	 * Opening bracket token => distance to its closing bracket.
	 *
	 * @var \WeakMap
	 */
	private \WeakMap $spans;
	/**
	 * Opening bracket token => its closing bracket token.
	 *
	 * @var \WeakMap
	 */
	private \WeakMap $closers;

	/**
	 * Find the abilities a plugin registers.
	 *
	 * @param string $base_dir The plugin's root directory.
	 * @return object[] Unique abilities; see make_ability().
	 */
	public static function scan( string $base_dir ): array {
		return self::scan_plugin( $base_dir )['abilities'];
	}

	/**
	 * Find the abilities and ability categories a plugin registers.
	 *
	 * Bundled third-party code (see BUNDLED_DIRECTORIES) is scanned too, but
	 * only packages that mention abilities, and what it registers is flagged
	 * as bundled. Whether to list it is up to whoever displays the data.
	 *
	 * @param string $base_dir The plugin's root directory.
	 * @return array {
	 *     @type object[] $abilities  Unique abilities; see make_ability().
	 *     @type object[] $categories Unique ability categories, each with slug, label, description, bundled, file and line.
	 * }
	 */
	public static function scan_plugin( string $base_dir ): array {
		$base_dir = rtrim( $base_dir, '/' );
		$files    = array_values(
			array_filter(
				Filesystem::list_files( $base_dir, true, '!\.php$!i' ),
				static fn( $file ) => filesize( $file ) <= self::MAX_FILE_SIZE
			)
		);
		// Directory order varies by filesystem; sort so duplicate registrations merge the same way every time.
		sort( $files );

		// Most plugins register no abilities: a plain text check comes first, so they're never tokenised.
		$relevant = [];
		foreach ( $files as $file ) {
			$contents = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file from the plugin export.
			if ( false !== stripos( $contents, 'register_ability' ) || false !== stripos( $contents, 'abilities_registry' ) ) {
				$relevant[ self::package_of( substr( $file, strlen( $base_dir ) ) ) ] = true;
			}
		}
		if ( ! $relevant ) {
			return [
				'abilities'  => [],
				'categories' => [],
			];
		}

		// Bundled packages are only indexed when they mention abilities themselves.
		$files = array_values(
			array_filter(
				$files,
				static function ( $file ) use ( $base_dir, $relevant ) {
					$package = self::package_of( substr( $file, strlen( $base_dir ) ) );
					return '' === $package || isset( $relevant[ $package ] );
				}
			)
		);

		$scanner           = new self( $files );
		$scanner->base_dir = $base_dir;
		return $scanner->run();
	}

	/**
	 * The bundled package a path belongs to, e.g. `/vendor/acme/tools`, or '' for the plugin's own code.
	 *
	 * @param string $path A path relative to the plugin's root, starting with `/`.
	 */
	private static function package_of( string $path ): string {
		$parts = explode( '/', ltrim( $path, '/' ) );
		foreach ( $parts as $i => $part ) {
			if ( in_array( $part, self::BUNDLED_DIRECTORIES, true ) ) {
				return '/' . implode( '/', array_slice( $parts, 0, min( $i + 3, count( $parts ) - 1 ) ) );
			}
		}
		return '';
	}

	/**
	 * Set up a scan of the given files.
	 *
	 * @param string[] $files The plugin's PHP files.
	 */
	private function __construct( array $files ) {
		$this->files   = $files;
		$this->spans   = new \WeakMap();
		$this->closers = new \WeakMap();
	}

	/**
	 * Whether a built array is small enough to keep. Charges its size, since
	 * memoised values (e.g. class constants) can be combined repeatedly.
	 *
	 * @param array $values The array.
	 */
	private function array_fits( array $values ): bool {
		$this->spend( count( $values ) );
		return count( $values ) <= self::MAX_ARRAY;
	}

	/**
	 * Charge work against the budget. Only evaluation is charged; indexing is
	 * linear in the plugin's size.
	 *
	 * @param int $units Units of work to charge.
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
	 * Index the plugin, then evaluate each wp_register_ability() and wp_register_ability_category() call.
	 *
	 * @return array See scan_plugin().
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
				$definitions = self::find_definitions( (string) file_get_contents( $file ), $budget ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file from the plugin export.
				foreach ( $definitions as $ability ) {
					$ability->file    = $this->relative( $file );
					$ability->bundled = $this->is_bundled( $file );
				}
				$found = array_merge( $found, $definitions );
				if ( $budget <= 0 ) {
					break;
				}
			}
		}

		$categories = [];
		foreach ( $this->category_sites as [ $file, $index ] ) {
			if ( $this->budget <= 0 ) {
				break;
			}
			$this->site_budget = self::SITE_BUDGET;
			$this->depth       = 0;
			try {
				$categories = array_merge( $categories, $this->evaluate_category_site( $file, $index ) );
			} catch ( OverflowException $e ) {
				continue;
			}
		}

		return [
			'abilities'  => self::merge( $found ),
			'categories' => self::merge( $categories, 'slug' ),
		];
	}

	/*
	 * Registration call sites.
	 */

	/**
	 * Evaluate one wp_register_ability() call in every context it can run in.
	 *
	 * @param string $file Path of one of the plugin's PHP files.
	 * @param int    $index Index of a token in the file.
	 * @param array  $args The `[start, end]` ranges of the call's arguments.
	 * @return object[]
	 * @throws OverflowException When the plugin's whole budget is spent.
	 */
	private function evaluate_site( string $file, int $index, array $args ): array {
		$tokens = $this->tokens( $file );
		$scope  = $this->scope_at( $file, $index );
		$name   = $this->slice( $tokens, $args[0] );
		$params = $this->uses_params( $name, $scope );

		$found = [];
		foreach ( $this->contexts( $scope, $index, $params, 0 ) as $ctx ) {
			// Each context (a subclass, a loop iteration, a call site) is its own registration, with its own share of the budget.
			$this->site_budget = self::SITE_BUDGET;
			$this->depth       = 0;
			try {
				$value = $this->evaluate( $name, $scope, $ctx );
				if ( ! is_string( $value ) || ! preg_match( self::NAME_PATTERN, $value ) ) {
					continue;
				}
				$options = isset( $args[1] ) ? $this->evaluate( $this->slice( $tokens, $args[1] ), $scope, $ctx ) : null;
				$ability = self::make_ability( $value, $options );
				if ( null === $ability->category && is_array( $options ) && is_string( $options['ability_class'] ?? null ) ) {
					$ability->category = $this->ability_class_category( $options['ability_class'], $value, $options, $scope );
				}
				[ $source_file, $ability->line ] = $this->source( $file, $index, $scope, $ctx );
				$ability->file                   = $this->relative( $source_file );
				$ability->bundled                = $this->is_bundled( $source_file );
				$found[]                         = $ability;
			} catch ( OverflowException $e ) {
				if ( $this->budget < 0 ) {
					throw $e;
				}
			}
		}
		return $found;
	}

	/**
	 * Evaluate one wp_register_ability_category() call in every context it can run in.
	 *
	 * @param string $file  Path of one of the plugin's PHP files.
	 * @param int    $index Index of a token in the file.
	 * @return object[] Categories, each with slug, label, description, bundled, file and line.
	 */
	private function evaluate_category_site( string $file, int $index ): array {
		$tokens = $this->tokens( $file );
		$args   = $this->split_args( $tokens, $index + 1 );
		if ( ! $args ) {
			return [];
		}
		$scope = $this->scope_at( $file, $index );
		$slug  = $this->slice( $tokens, $args[0] );
		$found = [];
		foreach ( $this->contexts( $scope, $index, $this->uses_params( $slug, $scope ), 0 ) as $ctx ) {
			$value = $this->evaluate( $slug, $scope, $ctx );
			if ( ! is_string( $value ) || ! preg_match( self::CATEGORY_PATTERN, $value ) ) {
				continue;
			}
			$options                = isset( $args[1] ) ? $this->evaluate( $this->slice( $tokens, $args[1] ), $scope, $ctx ) : null;
			$options                = is_array( $options ) ? $options : [];
			[ $source_file, $line ] = $this->source( $file, $index, $scope, $ctx );
			$found[]                = (object) [
				'slug'        => $value,
				'label'       => is_string( $options['label'] ?? null ) ? $options['label'] : null,
				'description' => is_string( $options['description'] ?? null ) ? $options['description'] : null,
				'bundled'     => $this->is_bundled( $source_file ),
				'file'        => $this->relative( $source_file ),
				'line'        => $line,
			];
		}
		return $found;
	}

	/**
	 * The category a custom `ability_class` sets, when the args don't: read
	 * from the arguments its constructor passes to `parent::__construct()`.
	 *
	 * @param string $class_name The `ability_class` value.
	 * @param string $name       The ability name.
	 * @param array  $options    The ability's args.
	 * @param array  $scope      The scope of the registration.
	 */
	private function ability_class_category( string $class_name, string $name, array $options, array $scope ): ?string {
		$class = $this->lookup_class_value( $class_name, $scope );
		$ctor  = $class ? $this->find_method( $class, '__construct' ) : null;
		if ( ! $ctor ) {
			return null;
		}
		$tokens = $this->tokens( $ctor['file'] );
		for ( $i = $ctor['start'] + 1; $i < $ctor['end']; $i++ ) {
			$this->spend();
			if ( 'parent' === strtolower( $tokens[ $i ]->text ) && T_DOUBLE_COLON === ( $tokens[ $i + 1 ] ?? null )?->id
				&& '__construct' === strtolower( $tokens[ $i + 2 ]->text ?? '' ) && ( $tokens[ $i + 3 ] ?? null )?->is( '(' )
			) {
				$args = $this->split_args( $tokens, $i + 3 );
				if ( ! isset( $args[1] ) ) {
					return null;
				}
				$ctx    = [
					'vars' => $this->bind( $ctor, [ $name, $options ] ),
					'this' => $this->bare( $class ),
					'at'   => $i,
				];
				$values = $this->evaluate( $this->slice( $tokens, $args[1] ), $ctor, $ctx );
				return is_array( $values ) && is_string( $values['category'] ?? null ) ? $values['category'] : null;
			}
		}
		return null;
	}

	/**
	 * Where a registration comes from, for reviewing it: the outermost call
	 * through the plugin's wrappers, else the class whose ability it is, else
	 * the call itself.
	 *
	 * @param string $file  Path of the file with the call.
	 * @param int    $index Index of the call's name token.
	 * @param array  $scope The scope of the call.
	 * @param array  $ctx   The context it was evaluated in.
	 * @return array [ file, line ].
	 */
	private function source( string $file, int $index, array $scope, array $ctx ): array {
		if ( isset( $ctx['origin'] ) ) {
			[ $origin_file, $origin_index ] = $ctx['origin'];
			return [ $origin_file, $this->tokens( $origin_file )[ $origin_index ]->line ?? null ];
		}
		$class = $ctx['this']->class ?? null;
		if ( $class && $class !== $scope['class'] && isset( $this->classes[ $class ] ) ) {
			return [ $this->classes[ $class ]['file'], $this->classes[ $class ]['line'] ];
		}
		return [ $file, $this->tokens( $file )[ $index ]->line ?? null ];
	}

	/**
	 * A path relative to the plugin's root.
	 *
	 * @param string $file An absolute path.
	 */
	private function relative( string $file ): string {
		return ltrim( substr( $file, strlen( $this->base_dir ) ), '/' );
	}

	/**
	 * Whether a file is in a bundled package.
	 *
	 * @param string $file An absolute path.
	 */
	private function is_bundled( string $file ): bool {
		return '' !== self::package_of( '/' . $this->relative( $file ) );
	}

	/**
	 * Contexts the code at $index can run in: each binding of the enclosing
	 * function's parameters (from its call sites, when $expand), each `$this`
	 * class, and each iteration of the foreach loops around $index.
	 *
	 * @param array $scope The scope the code runs in.
	 * @param int   $index Index of a token in the file.
	 * @param bool  $expand Whether to bind the function's parameters from its call sites.
	 * @param int   $depth How many callers have been followed so far.
	 * @return array[] Each with 'vars' (name => value) and 'this' (object|null).
	 */
	private function contexts( array $scope, int $index, bool $expand, int $depth ): array {
		$bindings = [
			[
				'vars' => [],
				'this' => null,
			],
		];

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
						'vars'   => $this->bind( $scope, $values ),
						'this'   => $this->receiver( $receiver, $scope, $caller_tokens, $caller_scope, $caller_ctx ),
						'origin' => $caller_ctx['origin'] ?? [ $file, $call ],
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
				$ctx = [
					'vars' => $binding['vars'],
					'this' => $this_object,
					'at'   => $index,
				];
				if ( isset( $binding['origin'] ) ) {
					$ctx['origin'] = $binding['origin'];
				}
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
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param array      $scope The scope the code runs in.
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
	 *
	 * @param array $scope The scope the code runs in.
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
		$this->memo[ $key ] = $sites;
		return $this->memo[ $key ];
	}

	/**
	 * For `Foo::method()`, whether Foo is the method's class (or a subclass).
	 * `self::`, `static::` and `parent::` are accepted; the scope check happens when evaluating.
	 *
	 * @param string $file Path of one of the plugin's PHP files.
	 * @param int    $index Index of a token in the file.
	 * @param array  $scope The scope the code runs in.
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
	 *
	 * @param ?int       $index Index of a token in the file.
	 * @param array      $scope The scope the code runs in.
	 * @param PhpToken[] $caller_tokens Tokens of the calling file.
	 * @param array      $caller_scope Scope of the calling code.
	 * @param array      $caller_ctx Evaluation context of the calling code.
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
			$class = is_string( $value ) ? $this->lookup_class_value( $value, $caller_scope ) : null;
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
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 * @return stdClass[]
	 */
	private function receivers_for( string $class_name ): array {
		$key = "receivers:$class_name";
		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}
		$this->memo[ $key ] = [ $this->bare( $class_name ) ]; // Guards against recursion through instantiations().

		$concrete = array_values(
			array_filter(
				array_merge( [ $class_name ], $this->descendants( $class_name ) ),
				fn( $c ) => empty( $this->classes[ $c ]['abstract'] ) && 'class' === ( $this->classes[ $c ]['kind'] ?? 'class' )
			)
		);
		$concrete = $concrete ?: [ $class_name ];

		$receivers = [];
		foreach ( array_slice( $concrete, 0, 50 ) as $c ) {
			foreach ( $this->instantiations( $c ) as $object ) {
				$receivers[] = $object;
			}
			// Also as built with no arguments, for classes instantiated from a list of class names.
			$receivers[] = $this->instantiate( $c, [] );
		}
		$this->memo[ $key ] = array_slice( $receivers, 0, 60 );
		return $this->memo[ $key ];
	}

	/**
	 * Objects built by `new Class( ... )` anywhere in the plugin, with the
	 * arguments evaluated where they're written.
	 *
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 * @return stdClass[]
	 */
	private function instantiations( string $class_name ): array {
		$short   = strtolower( self::short_name( $this->classes[ $class_name ]['name'] ?? $class_name ) );
		$objects = [];
		foreach ( $this->files as $file ) {
			if ( ! isset( $this->identifiers[ $file ][ $short ] ) ) {
				continue;
			}
			$tokens = $this->tokens( $file );
			$this->spend( count( $tokens ) );
			foreach ( $tokens as $i => $token ) {
				$named    = self::is_name( $tokens[ $i + 1 ] ?? null );
				$variable = T_VARIABLE === ( $tokens[ $i + 1 ] ?? null )?->id && '$this' !== $tokens[ $i + 1 ]->text;
				if ( T_NEW !== $token->id || ( ! $named && ! $variable ) ) {
					continue;
				}
				$scope = $this->scope_at( $file, $i );
				if ( $named && $this->lookup_class( $tokens[ $i + 1 ]->text, $scope ) !== $class_name ) {
					continue;
				}
				$base = [
					'vars' => [],
					'this' => $scope['class'] ? $this->bare( $scope['class'] ) : null,
					'at'   => $i,
				];
				// `new $class_name( … )`: each loop iteration whose variable names this class.
				$contexts = $named ? [ $base ] : array_filter(
					array_slice( $this->unroll_loops( $scope, $i, $base ), 0, 20 ),
					function ( $ctx ) use ( $tokens, $i, $scope, $class_name ) {
						$value = $this->evaluate( [ $tokens[ $i + 1 ] ], $scope, $ctx );
						return is_string( $value ) && $this->lookup_class_value( $value, $scope ) === $class_name;
					}
				);
				foreach ( $contexts as $ctx ) {
					$values = [];
					if ( ( $tokens[ $i + 2 ] ?? null )?->is( '(' ) ) {
						$slices = array_map( fn( $range ) => $this->slice( $tokens, $range ), $this->split_args( $tokens, $i + 2 ) );
						$values = $this->evaluate_args( $slices, $scope, $ctx );
					}
					$objects[] = $this->instantiate( $class_name, $values );
					if ( count( $objects ) >= 10 ) {
						return $objects;
					}
				}
			}
		}
		return $objects;
	}

	/**
	 * Expand a context into one per iteration of each foreach loop enclosing $index.
	 *
	 * @param array $scope The scope the code runs in.
	 * @param int   $index Index of a token in the file.
	 * @param array $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
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
	 * @param array $scope The scope the code runs in.
	 * @param int   $index Index of a token in the file.
	 * @return array[] Each with 'expr' (tokens), 'key' and 'value' (variable names or null).
	 */
	private function enclosing_loops( array $scope, int $index ): array {
		$key = "loops:{$scope['file']}:{$scope['start']}:$index";
		if ( ! isset( $this->memo[ $key ] ) ) {
			$this->memo[ $key ] = $this->find_enclosing_loops( $scope, $index );
		}
		return $this->memo[ $key ];
	}

	/**
	 * Uncached enclosing_loops().
	 *
	 * @param array $scope The scope the code runs in.
	 * @param int   $index Index of a token in the file.
	 * @return array[]
	 */
	private function find_enclosing_loops( array $scope, int $index ): array {
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
					++$depth;
				} elseif ( self::is_close( $tokens[ $j ] ) ) {
					--$depth;
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
	 * @param PhpToken[] $tokens Tokens of the expression.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
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
		++$this->depth;
		try {
			$i     = 0;
			$value = $this->parse_expression( $tokens, $i, $scope, $ctx );
			return count( $tokens ) === $i ? $value : null;
		} finally {
			--$this->depth;
		}
	}

	/**
	 * Parse an expression: an operand followed by any operators.
	 *
	 * @param PhpToken[] $t Tokens of the expression being parsed.
	 * @param int        &$i Index of the current token; moved past what's consumed.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
	private function parse_expression( array $t, int &$i, array $scope, array $ctx ) {
		$left = $this->parse_postfix( $t, $i, $scope, $ctx );
		$n    = count( $t );
		while ( $i < $n ) {
			$token = $t[ $i ];
			if ( $token->is( '.' ) ) {
				++$i;
				$right = $this->parse_postfix( $t, $i, $scope, $ctx );
				$left  = is_scalar( $left ) && is_scalar( $right ) && strlen( (string) $left ) + strlen( (string) $right ) <= self::MAX_STRING ? $left . $right : null;
			} elseif ( T_COALESCE === $token->id ) {
				++$i;
				$right = $this->parse_expression( $t, $i, $scope, $ctx );
				$left  = $left ?? $right;
			} elseif ( $token->is( '=' ) || T_COALESCE_EQUAL === $token->id ) {
				// An assignment's value is what's assigned: `return $this->cache = new Foo();`.
				++$i;
				$right = $this->parse_expression( $t, $i, $scope, $ctx );
				$left  = $token->is( '=' ) ? $right : ( $left ?? $right );
			} elseif ( in_array( $token->id, [ T_IS_IDENTICAL, T_IS_NOT_IDENTICAL, T_IS_EQUAL, T_IS_NOT_EQUAL ], true ) ) {
				++$i;
				$right = $this->parse_postfix( $t, $i, $scope, $ctx );
				$left  = self::compare( $token->id, $left, $right );
			} elseif ( T_BOOLEAN_AND === $token->id || T_BOOLEAN_OR === $token->id ) {
				++$i;
				$right = $this->parse_postfix( $t, $i, $scope, $ctx );
				$left  = self::logical( T_BOOLEAN_AND === $token->id, self::truthy( $left ), self::truthy( $right ) );
			} elseif ( $token->is( '?' ) ) {
				return $this->ternary( $left, $t, $i, $scope, $ctx );
			} else {
				// Arithmetic, comparisons: not followed.
				$i = $n;
				return null;
			}
		}
		return $left;
	}

	/**
	 * `cond ? a : b` or `cond ?: b`, with $i at the `?`; consumes the rest of the expression.
	 *
	 * When the condition can't be known, the branch that resolves is used
	 * (the first, if both do): e.g. `defined( $c . '::NAME' ) ? $c::NAME : $c::get_name()`
	 * resolves for classes with either.
	 *
	 * @param mixed      $condition The condition's value, or null when unknown.
	 * @param PhpToken[] $t Tokens of the expression being parsed.
	 * @param int        &$i Index of the current token; moved past what's consumed.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
	private function ternary( $condition, array $t, int &$i, array $scope, array $ctx ) {
		$n     = count( $t );
		$colon = null;
		for ( $k = $i + 1, $nested = 0; $k < $n; $k++ ) {
			if ( self::is_open( $t[ $k ] ) ) {
				$k = $this->match( $t, $k ) ?? $n;
			} elseif ( $t[ $k ]->is( '?' ) ) {
				++$nested;
			} elseif ( $t[ $k ]->is( ':' ) && 0 === $nested-- ) {
				$colon = $k;
				break;
			}
		}
		$then = null === $colon ? [] : array_slice( $t, $i + 1, $colon - $i - 1 );
		$else = null === $colon ? [] : array_slice( $t, $colon + 1 );
		$i    = $n;
		if ( null === $colon ) {
			return null;
		}

		$known = self::truthy( $condition );
		if ( ! $then ) {
			// Short ternary: `cond ?: b`.
			return true === $known ? $condition : ( false === $known ? $this->evaluate( $else, $scope, $ctx ) : ( $condition ?? $this->evaluate( $else, $scope, $ctx ) ) );
		}
		if ( null !== $known ) {
			return $this->evaluate( $known ? $then : $else, $scope, $ctx );
		}
		return $this->evaluate( $then, $scope, $ctx ) ?? $this->evaluate( $else, $scope, $ctx );
	}

	/**
	 * Compare two values with `===`, `!==`, `==` or `!=`, or null when either is unknown.
	 *
	 * @param int   $operator The operator's token type.
	 * @param mixed $left     The left value, or null when unknown.
	 * @param mixed $right    The right value, or null when unknown.
	 */
	private static function compare( int $operator, $left, $right ): ?bool {
		if ( null === $left || null === $right || $left instanceof stdClass || $right instanceof stdClass ) {
			return null;
		}
		switch ( $operator ) {
			case T_IS_IDENTICAL:
				return $left === $right;
			case T_IS_NOT_IDENTICAL:
				return $left !== $right;
			case T_IS_EQUAL:
				return $left == $right; // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- Evaluating the plugin's own `==`.
			default:
				return $left != $right; // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- Evaluating the plugin's own `!=`.
		}
	}

	/**
	 * PHP truthiness of a known value, or null when unknown.
	 *
	 * @param mixed $value A value, or null when unknown.
	 */
	private static function truthy( $value ): ?bool {
		return null === $value ? null : (bool) $value;
	}

	/**
	 * Combine two booleans that may be unknown with `&&` or `||`.
	 *
	 * @param bool  $is_and True for `&&`, false for `||`.
	 * @param ?bool $left The left operand's truthiness, or null when unknown.
	 * @param ?bool $right The right operand's truthiness, or null when unknown.
	 */
	private static function logical( bool $is_and, ?bool $left, ?bool $right ): ?bool {
		if ( $is_and ) {
			return false === $left || false === $right ? false : ( true === $left && true === $right ? true : null );
		}
		return true === $left || true === $right ? true : ( false === $left && false === $right ? false : null );
	}

	/**
	 * Whether an expression is set: true or false when that can be known, else null.
	 *
	 * @param PhpToken[] $tokens Tokens of the expression.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
	private function is_set( array $tokens, array $scope, array $ctx ): ?bool {
		$last = count( $tokens ) - 1;
		if ( $last > 0 && $tokens[ $last ]->is( ']' ) ) {
			for ( $open = $last - 1, $depth = 0; $open > 0; $open-- ) {
				if ( self::is_close( $tokens[ $open ] ) ) {
					++$depth;
				} elseif ( self::is_open( $tokens[ $open ] ) && 0 === $depth-- ) {
					break;
				}
			}
			if ( $open > 0 && $tokens[ $open ]->is( '[' ) && $this->match( $tokens, $open ) === $last ) {
				$base = $this->evaluate( array_slice( $tokens, 0, $open ), $scope, $ctx );
				$key  = $this->evaluate( array_slice( $tokens, $open + 1, $last - $open - 1 ), $scope, $ctx );
				if ( is_array( $base ) && ( is_string( $key ) || is_int( $key ) ) ) {
					// A known array without the key isn't set; a key with an unknown value might be.
					return array_key_exists( $key, $base ) ? ( null !== $base[ $key ] ? true : null ) : false;
				}
			}
		}
		return null !== $this->evaluate( $tokens, $scope, $ctx ) ? true : null;
	}

	/**
	 * Parse an operand followed by any index, property, method or static access.
	 *
	 * @param PhpToken[] $t Tokens of the expression being parsed.
	 * @param int        &$i Index of the current token; moved past what's consumed.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
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
				// A constant or static method reached through an object or class name held in a variable.
				$class = $value instanceof stdClass ? $value->class : ( is_string( $value ) ? $this->lookup_class_value( $value, $scope ) : null );
				++$i;
				$value = $this->static_member( $class, $value instanceof stdClass ? $value : null, $t, $i, $scope, $ctx );
			} else {
				break;
			}
		}
		return $value;
	}

	/**
	 * Parse an operand: a literal, variable, call, `new`, constant or bracketed expression.
	 *
	 * @param PhpToken[] $t Tokens of the expression being parsed.
	 * @param int        &$i Index of the current token; moved past what's consumed.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
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
				++$i;
				return self::unescape( $token->text );

			case T_LNUMBER === $token->id:
				++$i;
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
				++$i;
				return '$this' === $token->text ? $ctx['this'] : $this->variable( substr( $token->text, 1 ), $scope, $ctx );

			case T_NEW === $token->id:
				$class_token = $t[ $i + 1 ] ?? null;
				$i          += 2;
				if ( T_VARIABLE === $class_token?->id ) {
					$value = $this->variable( substr( $class_token->text, 1 ), $scope, $ctx );
					$class = is_string( $value ) ? $this->lookup_class_value( $value, $scope ) : null;
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

			case $token->is( '!' ):
				++$i;
				$value = self::truthy( $this->parse_postfix( $t, $i, $scope, $ctx ) );
				return null === $value ? null : ! $value;

			case T_ISSET === $token->id || T_EMPTY === $token->id:
				$open  = $i + 1;
				$close = ( $t[ $open ] ?? null )?->is( '(' ) ? $this->match( $t, $open ) : null;
				if ( null === $close ) {
					$i = $n;
					return null;
				}
				$i     = $close + 1;
				$parts = array_map( static fn( $r ) => array_slice( $t, $r[0], $r[1] - $r[0] + 1 ), self::split_ranges( $t, $open + 1, $close - 1 ) );
				if ( T_EMPTY === $token->id ) {
					$inner = $this->evaluate( $parts[0] ?? [], $scope, $ctx );
					return null !== $inner ? empty( $inner ) : ( false === $this->is_set( $parts[0] ?? [], $scope, $ctx ) ? true : null );
				}
				$set = true;
				foreach ( $parts as $part ) {
					$set = self::logical( true, $set, $this->is_set( $part, $scope, $ctx ) );
				}
				return $set;

			case T_NS_C === $token->id:
				++$i;
				return $scope['ns'];

			case T_CLASS_C === $token->id:
				++$i;
				return $scope['class'] ? $this->classes[ $scope['class'] ]['name'] : null;

			case T_STATIC === $token->id && T_DOUBLE_COLON === ( $t[ $i + 1 ] ?? null )?->id:
			case self::is_name( $token ):
				$next = $t[ $i + 1 ] ?? null;
				if ( $next?->is( '(' ) ) {
					++$i;
					$args = $this->call_args( $t, $i, $scope, $ctx );
					return $this->call_function( $token->text, $args, $scope );
				}
				if ( T_DOUBLE_COLON === $next?->id ) {
					$i     += 2;
					$lower  = strtolower( $token->text );
					$class  = $this->class_ref( $token->text, $scope, $ctx );
					$object = in_array( $lower, [ 'self', 'static', 'parent' ], true ) ? $ctx['this'] : null;
					if ( T_CLASS === ( $t[ $i ] ?? null )?->id ) {
						++$i;
						return $class ? $this->classes[ $class ]['name'] : ltrim( $token->text, '\\' );
					}
					return $this->static_member( $class, $object, $t, $i, $scope, $ctx );
				}
				++$i;
				return $this->constant( $token->text );

			default:
				// Interpolated strings, heredocs, closures, match, etc.
				$i = $n;
				return null;
		}
	}

	/**
	 * `Class::CONST` or `Class::method( ... )`, with $i just past the `::`.
	 *
	 * @param ?string    $class_name The class, or null when unknown.
	 * @param ?stdClass  $owner The object the call is made through, if any.
	 * @param PhpToken[] $t Tokens of the expression being parsed.
	 * @param int        &$i Index of the current token; moved past what's consumed.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
	private function static_member( ?string $class_name, ?stdClass $owner, array $t, int &$i, array $scope, array $ctx ) {
		$member = $t[ $i ] ?? null;
		if ( ! self::is_name( $member ) ) {
			$i = count( $t );
			return null;
		}
		++$i;
		if ( ( $t[ $i ] ?? null )?->is( '(' ) ) {
			$args = $this->call_args( $t, $i, $scope, $ctx );
			if ( ! $class_name ) {
				return null;
			}
			$receiver = $owner && $this->is_a( $owner->class, $class_name ) ? $owner : $this->bare( $class_name );
			return $this->call_method( $receiver, $member->text, $args, $class_name );
		}
		return $class_name ? $this->class_constant( $class_name, $member->text ) : null;
	}

	/**
	 * Resolve `self`, `static`, `parent` or a class name to a known class.
	 *
	 * @param string $name A name as written in the code.
	 * @param array  $scope The scope the code runs in.
	 * @param array  $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
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
	 * @param PhpToken[] $t Tokens of the expression being parsed.
	 * @param int        &$i Index of the current token; moved past what's consumed.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
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

	/**
	 * Evaluate argument tokens: positional, spread and named.
	 *
	 * @param PhpToken[][] $slices Each argument's tokens.
	 * @param array        $scope The scope the code runs in.
	 * @param array        $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
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
	 * Evaluate the entries of an array literal.
	 *
	 * @param PhpToken[] $inner Tokens between the brackets.
	 * @param array      $scope The scope the code runs in.
	 * @param array      $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
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
					++$depth;
				} elseif ( self::is_close( $entry[ $k ] ) ) {
					--$depth;
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

	/**
	 * Evaluate a function call: a built-in the scanner understands, or one of the plugin's functions.
	 *
	 * @param string $name A name as written in the code.
	 * @param array  $args Evaluated arguments: positional, plus named ones under their names.
	 * @param array  $scope The scope the code runs in.
	 */
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
				return $this->apply_filters( $args[0] ?? null, $args[1] ?? null, array_slice( array_values( $args ), 2 ) );
			case 'is_array':
			case 'is_string':
				return null === ( $args[0] ?? null ) ? null : ( 'is_array' === $short ? is_array( $args[0] ) : is_string( $args[0] ) );
			case 'array_values':
			case 'array_keys':
				return is_array( $args[0] ?? null ) ? ( 'array_values' === $short ? array_values( $args[0] ) : array_keys( $args[0] ) ) : null;
			case 'array_unique':
				$values = $args[0] ?? null;
				return is_array( $values ) && ! array_filter( $values, static fn( $v ) => ! is_scalar( $v ) ) ? array_unique( $values ) : null;
			case 'array_filter':
				$values   = $args[0] ?? null;
				$callback = $args[1] ?? null;
				if ( ! is_array( $values ) || in_array( null, $values, true ) ) {
					return null;
				}
				if ( null === $callback && ! isset( $args[1] ) ) {
					return array_filter( $values );
				}
				// Only side-effect-free type checks as callbacks.
				if ( 'strlen' === $callback && array_filter( $values, static fn( $v ) => ! is_scalar( $v ) ) ) {
					return null;
				}
				return in_array( $callback, [ 'is_string', 'is_array', 'is_numeric', 'is_scalar', 'strlen' ], true ) ? array_filter( $values, $callback ) : null;
		}

		$function = $this->lookup_function( $name, $scope );
		return $function ? $this->invoke( $function, $args, null ) : null;
	}

	/**
	 * The value of `apply_filters( $hook, $value, … )`: passed through each
	 * callback the plugin adds to the hook, in the order they're added.
	 *
	 * @param mixed $hook  The hook name, or null when unknown.
	 * @param mixed $value The value being filtered, or null when unknown.
	 * @param array $extra Any further arguments.
	 */
	private function apply_filters( $hook, $value, array $extra ) {
		$key = is_string( $hook ) ? "filtering:$hook" : null;
		if ( ! $key || empty( $this->filters[ $hook ] ) || isset( $this->memo[ $key ] ) ) {
			return $value;
		}
		$this->memo[ $key ] = true; // A callback that applies the same filter sees the unfiltered value.
		try {
			foreach ( array_slice( $this->filters[ $hook ], 0, 50 ) as [ $file, $range ] ) {
				$result = $this->call_callback( $file, $range, array_merge( [ $value ], $extra ) );
				if ( null !== $result ) {
					$value = $result;
				}
			}
		} finally {
			unset( $this->memo[ $key ] );
		}
		return $value;
	}

	/**
	 * Call a callback as written in the code: a closure, a function name, or
	 * an `array( $object_or_class, 'method' )` or `'Class::method'` callable.
	 *
	 * @param string $file  The file the callback is written in.
	 * @param int[]  $range The callback expression's token range.
	 * @param array  $args  Evaluated arguments.
	 */
	private function call_callback( string $file, array $range, array $args ) {
		$tokens = $this->tokens( $file );
		$first  = $tokens[ $range[0] ] ?? null;
		if ( T_STATIC === $first?->id ) {
			$first = $tokens[ $range[0] + 1 ] ?? null;
		}
		if ( T_FUNCTION === $first?->id ) {
			foreach ( $this->meta[ $file ]['scopes'] as $closure ) {
				if ( 'closure' === $closure['kind'] && $closure['start'] > $range[0] && $closure['end'] <= $range[1] ) {
					return $this->invoke( $closure, $args, $closure['class'] ? $this->bare( $closure['class'] ) : null );
				}
			}
			return null;
		}

		$scope    = $this->scope_at( $file, $range[0] );
		$ctx      = [
			'vars' => [],
			'this' => $scope['class'] ? $this->bare( $scope['class'] ) : null,
			'at'   => $range[0],
		];
		$callable = $this->evaluate( $this->slice( $tokens, $range ), $scope, $ctx );
		if ( is_string( $callable ) && str_contains( $callable, '::' ) ) {
			$callable = explode( '::', $callable, 2 );
		}
		if ( is_string( $callable ) ) {
			$func = $this->lookup_function( $callable, $scope );
			return $func ? $this->invoke( $func, $args, null ) : null;
		}
		if ( is_array( $callable ) && 2 === count( $callable ) && is_string( $callable[1] ?? null ) ) {
			return $this->call_method( $callable[0], $callable[1], $args );
		}
		return null;
	}

	/**
	 * Call a method on an object (or class name), looking it up from class $from (default: the object's class).
	 *
	 * @param mixed   $target The object, or class name, to call the method on.
	 * @param string  $name A name as written in the code.
	 * @param array   $args Evaluated arguments.
	 * @param ?string $from Optional. Class to look the method up from; defaults to the object's class.
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
	 *
	 * @param array     $func A function or method, as recorded when indexing.
	 * @param array     $args Evaluated arguments: positional, plus named ones under their names.
	 * @param ?stdClass $this_object The object to use as `$this`, if any.
	 */
	private function invoke( array $func, array $args, ?stdClass $this_object ) {
		$return = $this->return_expression( $func );
		if ( null === $return ) {
			return null;
		}
		$ctx = [
			'vars' => $this->bind( $func, $args ),
			'this' => $this_object,
			'at'   => $return['at'],
		];
		return $this->evaluate( $return['tokens'], $func, $ctx );
	}

	/**
	 * Build an object, running the constructor's promoted properties and its
	 * `$this->prop = ...;` assignments.
	 *
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 * @param array  $args Evaluated constructor arguments.
	 */
	private function instantiate( string $class_name, array $args ): stdClass {
		$object = $this->bare( $class_name );
		$ctor   = $this->find_method( $class_name, '__construct' );
		if ( ! $ctor || $this->constructing >= self::MAX_CONSTRUCTION_DEPTH ) {
			return $object;
		}
		++$this->constructing;
		try {
			return $this->construct( $object, $ctor, $args );
		} finally {
			--$this->constructing;
		}
	}

	/**
	 * Run a constructor's promoted properties and `$this->prop = ...;` assignments on an object.
	 *
	 * @param stdClass $owner The object being built.
	 * @param array    $ctor  The constructor, as recorded when indexing.
	 * @param array    $args  Evaluated constructor arguments.
	 */
	private function construct( stdClass $owner, array $ctor, array $args ): stdClass {

		$vars = $this->bind( $ctor, $args );
		foreach ( $ctor['params'] as $param ) {
			if ( $param['promoted'] ) {
				$owner->props[ $param['name'] ] = $vars[ $param['name'] ] ?? null;
			}
		}

		$tokens = $this->tokens( $ctor['file'] );
		$ctx    = [
			'vars' => $vars,
			'this' => $owner,
		];
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
					$owner->props[ $tokens[ $i + 2 ]->text ] = $this->evaluate( array_slice( $tokens, $i + 4, $end - $i - 4 ), $ctor, $ctx );
				}
			}
		}
		return $owner;
	}

	/**
	 * Bind call arguments to a function's parameters, evaluating defaults for missing ones.
	 *
	 * @param array $func A function or method, as recorded when indexing.
	 * @param array $args Evaluated arguments: positional, plus named ones under their names.
	 */
	private function bind( array $func, array $args ): array {
		$vars     = [];
		$position = 0;
		foreach ( $func['params'] as $param ) {
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
				$vars[ $name ] = $this->evaluate(
					$this->slice( $this->tokens( $func['file'] ), $param['default'] ),
					$func,
					[
						'vars' => $vars,
						'this' => null,
					]
				);
			} else {
				$vars[ $name ] = null;
			}
			++$position;
		}
		return $vars;
	}

	/**
	 * A variable's value, from its binding or its assignments in the scope.
	 *
	 * The value starts from the variable's binding (a parameter or loop
	 * variable), or else its first plain assignment, and later assignments
	 * that build on it are applied in order, once per iteration of any loop
	 * around them: `$x = array_merge( $x, … )` (or anything else using `$x`),
	 * `$x[] = …` and `$x['key'] = …`. Other later assignments, typically
	 * fallbacks such as `if ( ! is_array( $x ) ) { $x = array(); }`, are ignored.
	 *
	 * Only assignments before the code being evaluated (the context's 'at')
	 * count, and each is skipped when an `if` condition around it is known not
	 * to hold. When a condition can't be known, building on the value still
	 * applies, but a key assignment only fills in a missing value, so a fallback
	 * like `if ( empty( $x['label'] ) ) { $x['label'] = $name; }` can't replace
	 * a real one.
	 *
	 * @param string $name  A name as written in the code.
	 * @param array  $scope The scope the code runs in.
	 * @param array  $ctx   Evaluation context: 'vars' (name => value), 'this' (object or null) and 'at'.
	 */
	private function variable( string $name, array $scope, array $ctx ) {
		$is_param = in_array( $name, array_column( $scope['params'], 'name' ), true );
		$bound    = array_key_exists( $name, $ctx['vars'] );
		if ( $bound && ! empty( $ctx['building'][ $name ] ) ) {
			return $ctx['vars'][ $name ];
		}
		if ( $is_param && ! $bound ) {
			return null;
		}

		$tokens = $this->tokens( $scope['file'] );
		$var    = "\$$name";
		$value  = $bound ? $ctx['vars'][ $name ] : null;
		$first  = ! $bound;
		$until  = min( $scope['end'], count( $tokens ), $ctx['at'] ?? PHP_INT_MAX );
		// While building it, the variable reads as its binding (or unknown), so `$x = f( $x )` can't recurse.
		$ctx['vars'][ $name ]     = $value;
		$ctx['building'][ $name ] = true;

		for ( $i = $scope['start'] + 1; $i < $until; $i++ ) {
			$i = $this->skip_inner_scope( $scope, $i );
			$this->spend();
			if ( T_VARIABLE !== ( $tokens[ $i ] ?? null )?->id || $var !== $tokens[ $i ]->text || T_DOUBLE_COLON === ( $tokens[ $i - 1 ] ?? null )?->id ) {
				continue;
			}

			// A plain assignment, an append, or an assignment to a key.
			$key_range = null;
			$rhs       = null;
			if ( ( $tokens[ $i + 1 ] ?? null )?->is( '=' ) ) {
				$rhs = $i + 2;
			} elseif ( ( $tokens[ $i + 1 ] ?? null )?->is( '[' ) && ! $first ) {
				$close = $this->match( $tokens, $i + 1 );
				if ( null !== $close && ( $tokens[ $close + 1 ] ?? null )?->is( '=' ) ) {
					$key_range = [ $i + 2, $close - 1 ];
					$rhs       = $close + 2;
				}
			}
			if ( null === $rhs ) {
				continue;
			}
			$stop = $this->statement_end( $tokens, $rhs );
			if ( null === $stop ) {
				continue;
			}
			$expr = array_slice( $tokens, $rhs, $stop - $rhs );

			if ( $first ) {
				$value                = $this->evaluate( $expr, $scope, $ctx );
				$ctx['vars'][ $name ] = $value;
				$first                = false;
				continue;
			}

			$self_referential = (bool) array_filter( $expr, static fn( $t ) => $var === $t->text );
			if ( null === $key_range && ! $self_referential ) {
				continue;
			}

			$conditions = $this->enclosing_conditions( $scope, $i );
			foreach ( $this->unroll_loops( $scope, $i, $ctx ) as $iteration ) {
				$iteration['vars'][ $name ] = $value;
				$holds                      = $this->conditions_hold( $conditions, $scope, $iteration );
				if ( false === $holds ) {
					continue;
				}
				$next = $this->evaluate( $expr, $scope, $iteration );
				if ( null === $key_range ) {
					// Building on the value (`$x = array_merge( $x, … )`) applies even under an unknown condition:
					// guards like `if ( class_exists( $c ) )` usually hold.
					$value = $next ?? $value;
				} elseif ( is_array( $value ) ) {
					if ( $key_range[0] > $key_range[1] ) {
						$value[] = $next;
					} else {
						$key = $this->evaluate( $this->slice( $tokens, $key_range ), $scope, $iteration );
						// An unknown value doesn't replace a known one (`$args['description'] = $this->decorate();`
						// most likely builds on what was there), and under an unknown condition only gaps are filled.
						$fill_only = true !== $holds || null === $next;
						if ( ( is_string( $key ) || is_int( $key ) ) && ( ! $fill_only || ! isset( $value[ $key ] ) ) ) {
							$value[ $key ] = $next;
						}
					}
					if ( ! $this->array_fits( $value ) ) {
						return null;
					}
				}
			}
			$ctx['vars'][ $name ] = $value;
		}
		return $value;
	}

	/**
	 * The `if`/`elseif`/`else` conditions that must hold for code at $index to run.
	 *
	 * @param array $scope The scope the code runs in.
	 * @param int   $index Index of a token in the file.
	 * @return array[] Each with 'expr' (tokens) and 'want' (the truthiness it needs).
	 */
	private function enclosing_conditions( array $scope, int $index ): array {
		$key = "conditions:{$scope['file']}:{$scope['start']}:$index";
		if ( ! isset( $this->memo[ $key ] ) ) {
			$this->memo[ $key ] = $this->find_enclosing_conditions( $scope, $index );
		}
		return $this->memo[ $key ];
	}

	/**
	 * Uncached enclosing_conditions().
	 *
	 * @param array $scope The scope the code runs in.
	 * @param int   $index Index of a token in the file.
	 * @return array[]
	 */
	private function find_enclosing_conditions( array $scope, int $index ): array {
		$tokens     = $this->tokens( $scope['file'] );
		$conditions = [];
		for ( $i = $scope['start'] + 1; $i < $index; $i++ ) {
			$i = $this->skip_inner_scope( $scope, $i );
			$this->spend();
			$token = $tokens[ $i ] ?? null;
			// Start of a chain: an `if` that isn't the `if` of `else if`.
			if ( T_IF !== $token?->id || T_ELSE === ( $tokens[ $i - 1 ] ?? null )?->id ) {
				continue;
			}

			// Each branch of the chain: [ condition tokens, or null for `else`; body start; body end ].
			$branches = [];
			$at       = $i;
			while ( true ) {
				$cond = null;
				if ( T_ELSE === $tokens[ $at ]->id ) {
					$body_start = $at + 1;
				} else {
					$open  = $at + 1;
					$close = ( $tokens[ $open ] ?? null )?->is( '(' ) ? $this->match( $tokens, $open ) : null;
					if ( null === $close ) {
						break;
					}
					$cond       = array_slice( $tokens, $open + 1, $close - $open - 1 );
					$body_start = $close + 1;
				}
				$body_end = ( $tokens[ $body_start ] ?? null )?->is( '{' ) ? $this->match( $tokens, $body_start ) : $this->statement_end( $tokens, $body_start );
				if ( null === $body_end ) {
					break;
				}
				$branches[] = [ $cond, $body_start, $body_end ];

				$next = $tokens[ $body_end + 1 ] ?? null;
				if ( null === $cond || ! $next ) {
					break; // A plain `else` ends the chain.
				}
				if ( T_ELSEIF === $next->id ) {
					$at = $body_end + 1;
				} elseif ( T_ELSE === $next->id ) {
					// `else if` continues the chain like `elseif`.
					$at = T_IF === ( $tokens[ $body_end + 2 ] ?? null )?->id ? $body_end + 2 : $body_end + 1;
				} else {
					break;
				}
			}

			$prior = [];
			foreach ( $branches as [ $cond, $body_start, $body_end ] ) {
				if ( $index > $body_start && $index <= $body_end ) {
					foreach ( $prior as $expr ) {
						$conditions[] = [
							'expr' => $expr,
							'want' => false,
						];
					}
					if ( null !== $cond ) {
						$conditions[] = [
							'expr' => $cond,
							'want' => true,
						];
					}
					break;
				}
				if ( null !== $cond ) {
					$prior[] = $cond;
				}
			}
		}
		return $conditions;
	}

	/**
	 * Whether conditions hold: true or false when that can be known, else null.
	 *
	 * @param array[] $conditions As returned by enclosing_conditions().
	 * @param array   $scope      The scope the code runs in.
	 * @param array   $ctx        Evaluation context.
	 */
	private function conditions_hold( array $conditions, array $scope, array $ctx ): ?bool {
		$holds = true;
		foreach ( $conditions as $condition ) {
			$value = self::truthy( $this->evaluate( $condition['expr'], $scope, $ctx ) );
			if ( null === $value ) {
				$holds = null;
			} elseif ( $value !== $condition['want'] ) {
				return false;
			}
		}
		return $holds;
	}

	/**
	 * The value of the first `$this->name = ...;` in a scope, for properties
	 * the constructor didn't set, e.g. `$this->cache ??= $this->build(); return $this->cache;`.
	 *
	 * @param string $name A name as written in the code.
	 * @param array  $scope The scope the code runs in.
	 * @param array  $ctx Evaluation context: 'vars' (name => value) and 'this' (object or null).
	 */
	private function assigned_property( string $name, array $scope, array $ctx ) {
		$tokens = $this->tokens( $scope['file'] );
		for ( $i = $scope['start'] + 1, $end = min( $scope['end'], count( $tokens ) ); $i < $end; $i++ ) {
			$i = $this->skip_inner_scope( $scope, $i );
			$this->spend();
			if ( '$this' !== ( $tokens[ $i ] ?? null )?->text
				|| T_OBJECT_OPERATOR !== ( $tokens[ $i + 1 ] ?? null )?->id
				|| ( $tokens[ $i + 2 ] ?? null )?->text !== $name
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
			$value                       = $this->evaluate( array_slice( $tokens, $i + 4, $stop - $i - 4 ), $scope, $ctx );
			unset( $ctx['this']->props[ $name ] );
			return $value;
		}
		return null;
	}

	/**
	 * The value of a `define()`d constant, `true` or `false`.
	 *
	 * @param string $name A name as written in the code.
	 */
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
		$key              = "define:$name";
		if ( array_key_exists( $key, $this->memo ) ) {
			return $this->memo[ $key ];
		}
		$this->memo[ $key ] = null;
		$this->memo[ $key ] = $this->evaluate(
			$this->slice( $this->tokens( $file ), $range ),
			$this->scope_at( $file, $range[0] ),
			[
				'vars' => [],
				'this' => null,
			]
		);
		return $this->memo[ $key ];
	}

	/**
	 * The value of a class constant, looking through the class's parents.
	 *
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 * @param string $name The constant's name.
	 */
	private function class_constant( string $class_name, string $name ) {
		for ( $c = $class_name, $hops = 0; $c && $hops < 20; $c = $this->parent_of( $c ), $hops++ ) {
			$range = $this->classes[ $c ]['consts'][ $name ] ?? null;
			if ( ! $range ) {
				continue;
			}
			$key = "const:$c::$name";
			if ( array_key_exists( $key, $this->memo ) ) {
				return $this->memo[ $key ];
			}
			$this->memo[ $key ] = null;
			$file               = $this->classes[ $c ]['file'];
			$scope              = $this->scope_at( $file, $range[0] );
			$scope['class']     = $c;
			$this->memo[ $key ] = $this->evaluate(
				$this->slice( $this->tokens( $file ), $range ),
				$scope,
				[
					'vars' => [],
					'this' => null,
				]
			);
			return $this->memo[ $key ];
		}
		return null;
	}

	/**
	 * The expression a function returns: its only `return`, or the last one in its outermost block.
	 *
	 * @param array $func A function or method, as recorded when indexing.
	 * @return array|null The expression's 'tokens', and 'at', the index of its `return`.
	 */
	private function return_expression( array $func ): ?array {
		$key = "return:{$func['file']}:{$func['start']}";
		if ( array_key_exists( $key, $this->memo ) ) {
			return $this->memo[ $key ];
		}

		$tokens  = $this->tokens( $func['file'] );
		$returns = [];
		for ( $i = $func['start'] + 1, $depth = 0; $i < $func['end']; $i++ ) {
			$this->spend();
			$skipped = $this->skip_inner_scope( $func, $i );
			if ( $skipped !== $i ) {
				$i = $skipped;
				continue;
			}
			if ( self::is_open( $tokens[ $i ] ) ) {
				++$depth;
			} elseif ( self::is_close( $tokens[ $i ] ) ) {
				--$depth;
			} elseif ( T_RETURN === $tokens[ $i ]->id ) {
				$returns[] = [ $i, $depth ];
			}
		}
		if ( ! $returns ) {
			$this->memo[ $key ] = null;
			return $this->memo[ $key ];
		}

		$top                = array_filter( $returns, static fn( $r ) => 0 === $r[1] );
		[ $at ]             = $top ? end( $top ) : end( $returns );
		$end                = $this->statement_end( $tokens, $at + 1 );
		$this->memo[ $key ] = null === $end ? null : [
			'tokens' => array_slice( $tokens, $at + 1, $end - $at - 1 ),
			'at'     => $at,
		];
		return $this->memo[ $key ];
	}

	/*
	 * Classes and functions.
	 */

	/**
	 * An object of a class with no properties set.
	 *
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 */
	private function bare( string $class_name ): stdClass {
		$object        = new stdClass();
		$object->class = $class_name;
		$object->props = [];
		return $object;
	}

	/**
	 * A method of a class, looking through the class's parents.
	 *
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 * @param string $name The method's name, lowercase.
	 */
	private function find_method( string $class_name, string $name ): ?array {
		for ( $c = $class_name, $hops = 0; $c && $hops < 20; $c = $this->parent_of( $c ), $hops++ ) {
			if ( isset( $this->classes[ $c ]['methods'][ $name ] ) ) {
				return $this->classes[ $c ]['methods'][ $name ];
			}
		}
		return null;
	}

	/**
	 * A class's parent, if it's a known class.
	 *
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 */
	private function parent_of( string $class_name ): ?string {
		$info = $this->classes[ $class_name ] ?? null;
		if ( ! $info || ! $info['parent'] ) {
			return null;
		}
		$key = "parent:$class_name";
		if ( ! array_key_exists( $key, $this->memo ) ) {
			$parent             = $this->lookup_class(
				$info['parent'],
				[
					'ns'   => $info['ns'],
					'uses' => $info['uses'],
				]
			);
			$this->memo[ $key ] = $parent !== $class_name ? $parent : null;
		}
		return $this->memo[ $key ];
	}

	/**
	 * Whether a class is, or extends, another.
	 *
	 * @param ?string $class_name A known class, or null.
	 * @param ?string $ancestor The class it may be or extend.
	 */
	private function is_a( ?string $class_name, ?string $ancestor ): bool {
		for ( $c = $class_name, $hops = 0; $c && $hops < 20; $c = $this->parent_of( $c ), $hops++ ) {
			if ( $c === $ancestor ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Every known class that extends a class, directly or not.
	 *
	 * @param string $class_name A known class (lowercase fully-qualified name).
	 */
	private function descendants( string $class_name ): array {
		$found = [];
		$queue = $this->children[ $class_name ] ?? [];
		for ( $found_count = 0; $queue && $found_count < 500; ) {
			$c = array_shift( $queue );
			if ( ! in_array( $c, $found, true ) ) {
				$found[] = $c;
				$queue   = array_merge( $queue, $this->children[ $c ] ?? [] );
				++$found_count;
			}
		}
		return $found;
	}

	/**
	 * Resolve a class name held in a string (e.g. from `Foo::class`) to a known class.
	 * Names in strings are always fully qualified; imports and the namespace don't apply.
	 *
	 * @param string $name  The class name.
	 * @param array  $scope The scope the code runs in.
	 */
	private function lookup_class_value( string $name, array $scope ): ?string {
		return $this->lookup_class( '\\' . ltrim( $name, '\\' ), $scope );
	}

	/**
	 * Resolve a class name as written in a scope to a known class (lowercase FQN).
	 *
	 * @param string $name A name as written in the code.
	 * @param array  $scope The scope the code runs in.
	 */
	private function lookup_class( string $name, array $scope ): ?string {
		$fqn = $this->qualify( $name, $scope );
		if ( isset( $this->classes[ $fqn ] ) ) {
			return $fqn;
		}
		$candidates = $this->short_names[ strtolower( self::short_name( $name ) ) ] ?? [];
		return 1 === count( $candidates ) ? $candidates[0] : null;
	}

	/**
	 * Resolve a function name as written in a scope to one of the plugin's functions.
	 *
	 * @param string $name A name as written in the code.
	 * @param array  $scope The scope the code runs in.
	 */
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
	 *
	 * @param string $name A name as written in the code.
	 * @param array  $scope The scope the code runs in.
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

	/**
	 * Parse a file and add what it declares to the plugin-wide index.
	 *
	 * @param string $file Path of one of the plugin's PHP files.
	 */
	private function index( string $file ): void {
		$parsed                     = $this->parse( $file );
		$this->meta[ $file ]        = [
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
		foreach ( $parsed['category_sites'] as $index ) {
			$this->category_sites[] = [ $file, $index ];
		}
		foreach ( $parsed['filters'] as $hook => $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$this->filters[ $hook ][] = $callback;
			}
		}
	}

	/**
	 * Tokenise a file and outline it: namespaces and imports, classes with their
	 * constants and methods, functions, closures, define() calls and
	 * wp_register_ability() call sites.
	 *
	 * @param string $file Path of one of the plugin's PHP files.
	 */
	private function parse( string $file ): array {
		$tokens = array_values(
			array_filter(
				PhpToken::tokenize( (string) file_get_contents( $file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file from the plugin export.
				static fn( PhpToken $t ) => ! $t->isIgnorable()
			)
		);
		$this->remember( $file, $tokens );

		$count          = count( $tokens );
		$namespaces     = [
			[
				'start' => 0,
				'ns'    => '',
				'uses'  => [],
			],
		];
		$ns_end         = null;
		$classes        = [];
		$functions      = [];
		$defines        = [];
		$sites          = [];
		$category_sites = [];
		$filters        = [];
		$scopes         = [];
		$identifiers    = [];
		$class_stack    = [];
		$fn_stack       = [];

		for ( $i = 0; $i < $count; $i++ ) {
			while ( $class_stack && end( $class_stack )['end'] < $i ) {
				array_pop( $class_stack );
			}
			while ( $fn_stack && end( $fn_stack ) < $i ) {
				array_pop( $fn_stack );
			}
			if ( null !== $ns_end && $i > $ns_end ) {
				$namespaces[] = [
					'start' => $i,
					'ns'    => '',
					'uses'  => [],
				];
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
						$namespaces[] = [
							'start' => $i,
							'ns'    => $next->text,
							'uses'  => [],
						];
						if ( ( $tokens[ $i + 2 ] ?? null )?->is( '{' ) ) {
							$ns_end = $this->match( $tokens, $i + 2 );
						}
					} elseif ( $next?->is( '{' ) ) {
						$namespaces[] = [
							'start' => $i,
							'ns'    => '',
							'uses'  => [],
						];
						$ns_end       = $this->match( $tokens, $i + 1 );
					}
					break;

				case T_USE:
					if ( ! $class_stack && ! $fn_stack ) {
						$last                        = count( $namespaces ) - 1;
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
					for ( $j = $i - 1; $j >= 0; $j-- ) {
						if ( ! in_array( $tokens[ $j ]->id, [ T_ABSTRACT, T_FINAL, T_READONLY ], true ) ) {
							break;
						}
						$abstract = $abstract || T_ABSTRACT === $tokens[ $j ]->id;
					}
					$classes[ $lname ] = [
						'name'     => $fqn,
						'file'     => $file,
						'line'     => $token->line,
						'kind'     => strtolower( $token->text ),
						'abstract' => $abstract,
						'parent'   => $parent,
						'ns'       => $ns['ns'],
						'uses'     => $ns['uses'],
						'consts'   => [],
						'methods'  => [],
					];
					$class_stack[]     = [
						'lname' => $lname,
						'open'  => $open,
						'end'   => $end ?? $count,
					];
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
						++$j;
					}
					$name = null;
					if ( ! ( $tokens[ $j ] ?? null )?->is( '(' ) ) {
						if ( ! ( $tokens[ $j + 1 ] ?? null )?->is( '(' ) ) {
							break;
						}
						$name = $tokens[ $j ]->text;
						++$j;
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
					$end      = $this->match( $tokens, $open ) ?? $count;
					$kind     = null === $name ? 'closure' : ( $class && $in_class_body ? 'method' : 'function' );
					$ns       = end( $namespaces );
					$scope    = [
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
					} elseif ( 'wp_register_ability_category' === $lower && $is_call ) {
						$category_sites[] = $i;
					} elseif ( 'add_filter' === $lower && $is_call && T_CONSTANT_ENCAPSED_STRING === ( $tokens[ $i + 2 ] ?? null )?->id ) {
						$args = $this->split_args( $tokens, $i + 1 );
						if ( isset( $args[1] ) && $args[0][0] === $args[0][1] ) {
							$filters[ self::unescape( $tokens[ $i + 2 ]->text ) ][] = [ $file, $args[1] ];
						}
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

		return compact( 'namespaces', 'classes', 'functions', 'defines', 'sites', 'category_sites', 'filters', 'scopes', 'identifiers' );
	}

	/**
	 * Whether the innermost open function started before the class body opened,
	 * i.e. the class is declared inside that function and we're in its body.
	 *
	 * @param array $fn_stack Closing indexes of the functions open at this point.
	 * @param array $scopes Functions recorded so far in the file.
	 * @param int   $class_open Index of the class body's opening brace.
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
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $i Index of the current token; moved past what's consumed.
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
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $i Index of the current token; moved past what's consumed.
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

	/**
	 * Parse a function's parameter list.
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $open Index of the list's `(`.
	 * @param int        $close Index of the list's `)`.
	 */
	private function parse_params( array $tokens, int $open, int $close ): array {
		$params = [];
		foreach ( self::split_ranges( $tokens, $open + 1, $close - 1 ) as [ $s, $e ] ) {
			$param = [
				'name'     => null,
				'promoted' => false,
				'variadic' => false,
				'default'  => null,
			];
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
	 *
	 * @param string $file Path of one of the plugin's PHP files.
	 * @param int    $index Index of a token in the file.
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
	 *
	 * @param array $scope The scope the code runs in.
	 * @param int   $i Index of the current token; moved past what's consumed.
	 */
	private function skip_inner_scope( array $scope, int $i ): int {
		$end = $this->meta[ $scope['file'] ]['scope_ends'][ $i ] ?? null;
		return null !== $end && $i > $scope['start'] && $end < $scope['end'] ? $end : $i;
	}

	/**
	 * A file's significant tokens.
	 *
	 * @param string $file Path of one of the plugin's PHP files.
	 * @return PhpToken[] Significant tokens of a file.
	 */
	private function tokens( string $file ): array {
		if ( isset( $this->cache[ $file ] ) ) {
			return $this->cache[ $file ];
		}
		$tokens = array_values(
			array_filter(
				PhpToken::tokenize( (string) file_get_contents( $file ) ), // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file from the plugin export.
				static fn( PhpToken $t ) => ! $t->isIgnorable()
			)
		);
		$this->remember( $file, $tokens );
		return $tokens;
	}

	/**
	 * Cache a file's tokens and record their bracket pairs.
	 *
	 * @param string     $file Path of one of the plugin's PHP files.
	 * @param PhpToken[] $tokens Significant tokens.
	 */
	private function remember( string $file, array $tokens ): void {
		if ( count( $this->cache ) >= self::CACHE_SIZE ) {
			array_shift( $this->cache );
		}
		$this->cache[ $file ] = $tokens;
		$this->pair( $tokens );
	}

	/**
	 * The tokens in a `[start, end]` range.
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int[]      $range A `[start, end]` token range, inclusive.
	 */
	private function slice( array $tokens, array $range ): array {
		return array_slice( $tokens, $range[0], $range[1] - $range[0] + 1 );
	}

	/**
	 * The `[start, end]` ranges of a call's arguments, given the index of its `(`.
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $open Index of an opening bracket.
	 */
	private function split_args( array $tokens, int $open ): array {
		$close = $this->match( $tokens, $open );
		return null === $close ? [] : self::split_ranges( $tokens, $open + 1, $close - 1 );
	}

	/**
	 * Index of the `;` (or `?>`) ending the statement that starts at $from, or
	 * of the bracket closing the expression it's in.
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $from Index to start at.
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

	/**
	 * Index of the next token of a type.
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $from Index to start at.
	 * @param int        $id The token type to look for.
	 */
	private function find_token( array $tokens, int $from, int $id ): ?int {
		for ( $i = $from, $n = count( $tokens ); $i < $n; $i++ ) {
			if ( $id === $tokens[ $i ]->id ) {
				return $i;
			}
		}
		return null;
	}

	/**
	 * Index of the `{` opening a declaration's body, or null if its statement ends first.
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $from Index to start at.
	 */
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
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $open Index of the opening bracket.
	 */
	private function match( array $tokens, int $open ): ?int {
		$token = $tokens[ $open ] ?? null;
		$span  = $token ? ( $this->spans[ $token ] ?? null ) : null;
		return null !== $span && isset( $tokens[ $open + $span ] ) && ( $this->closers[ $token ] ?? null ) === $tokens[ $open + $span ] ? $open + $span : null;
	}

	/**
	 * Record, for each opening bracket in a file, the distance to its closing
	 * bracket, so matching is a lookup rather than a scan. Tokens are shared
	 * between a file's list and its slices, so this works for both.
	 *
	 * @param PhpToken[] $tokens A file's significant tokens.
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
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @param int        $from Index of the first token.
	 * @param int        $to Index of the last token, inclusive.
	 */
	private static function split_ranges( array $tokens, int $from, int $to ): array {
		$ranges = [];
		$start  = $from;
		for ( $i = $from, $depth = 0; $i <= $to; $i++ ) {
			$t = $tokens[ $i ];
			if ( self::is_open( $t ) ) {
				++$depth;
			} elseif ( self::is_close( $t ) ) {
				--$depth;
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
	 * Split tokens at top-level commas.
	 *
	 * @param PhpToken[] $tokens Significant tokens.
	 * @return PhpToken[][]
	 */
	private static function split( array $tokens ): array {
		return array_map(
			static fn( $range ) => array_slice( $tokens, $range[0], $range[1] - $range[0] + 1 ),
			self::split_ranges( $tokens, 0, count( $tokens ) - 1 )
		);
	}

	/**
	 * Whether a token opens a bracket.
	 *
	 * @param PhpToken $t A token.
	 */
	private static function is_open( PhpToken $t ): bool {
		return $t->is( [ '(', '[', '{' ] ) || in_array( $t->id, [ T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE ], true );
	}

	/**
	 * Whether a token closes a bracket.
	 *
	 * @param PhpToken $t A token.
	 */
	private static function is_close( PhpToken $t ): bool {
		return $t->is( [ ')', ']', '}' ] );
	}

	/**
	 * Whether a token is a name, qualified or not.
	 *
	 * @param PhpToken|null $t A token.
	 */
	private static function is_name( ?PhpToken $t ): bool {
		return $t && in_array( $t->id, [ T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED ], true );
	}

	/**
	 * A minimal sprintf(): only `%s`, `%d`, `%%` and positional `%1$s` / `%1$d`.
	 * Anything else (widths, precisions, padding, other conversions) is
	 * unknown, so a format can never be used to build a large string.
	 *
	 * @param string $format The format string.
	 * @param array  $args Values for the placeholders.
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

	/**
	 * The last segment of a namespaced name.
	 *
	 * @param string $name A name as written in the code.
	 */
	private static function short_name( string $name ): string {
		$pos = strrpos( $name, '\\' );
		return false === $pos ? $name : substr( $name, $pos + 1 );
	}

	/**
	 * Decode a PHP string literal token to its value.
	 *
	 * @param string $literal The literal, including its quotes.
	 */
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
	 * Two shapes are recognised, both requiring 'label' and 'description'
	 * keys, which core requires to register an ability. Requiring both rules
	 * out lookalikes such as block patterns and variations, which pair
	 * 'title' with 'description':
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

		$is_definition = static fn( $value ) => is_array( $value ) && array_key_exists( 'label', $value ) && array_key_exists( 'description', $value );

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

		$abilities = array_values(
			array_filter(
				$abilities,
				static fn( $ability ) => $ability && ! str_starts_with( $ability->name, 'core/' )
			)
		);
		foreach ( $abilities as $ability ) {
			foreach ( [ "'", '"' ] as $quote ) {
				$at = strpos( $contents, $quote . $ability->name . $quote );
				if ( false !== $at ) {
					$ability->line = substr_count( $contents, "\n", 0, $at ) + 1;
					break;
				}
			}
		}
		return $abilities;
	}

	/**
	 * Build an ability record from a name and its args, as passed to wp_register_ability().
	 *
	 * The label, description and category are kept when they resolve to
	 * strings, and are null otherwise. 'bundled', 'file' and 'line' are filled
	 * in by the caller.
	 *
	 * @param mixed $name The ability name.
	 * @param mixed $args The resolved args.
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
			'category'    => is_string( $args['category'] ?? null ) ? $args['category'] : null,
			'bundled'     => false,
			'file'        => null,
			'line'        => null,
		];
	}

	/**
	 * Collapse abilities found in several places into one entry per name. The
	 * first one found wins, with missing properties filled in from later ones.
	 *
	 * @param object[] $abilities Abilities (or categories) found, in order.
	 * @param string   $key       Optional. The property that identifies one. Default 'name'.
	 * @return object[] Unique abilities, in the order first seen.
	 */
	public static function merge( array $abilities, string $key = 'name' ): array {
		$merged = [];
		foreach ( array_filter( $abilities ) as $ability ) {
			$id = $ability->$key;
			if ( ! isset( $merged[ $id ] ) ) {
				$merged[ $id ] = clone $ability;
				continue;
			}
			foreach ( get_object_vars( $ability ) as $prop => $value ) {
				if ( null === ( $merged[ $id ]->$prop ?? null ) ) {
					$merged[ $id ]->$prop = $value;
				}
			}
		}
		return array_values( $merged );
	}
}

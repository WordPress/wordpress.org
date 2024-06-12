<?php
namespace WordPressdotorg\Plugin_Directory;

// Hmm
add_filter( 'option_has_jetpack_search_product', '__return_true' );

/**
 * Override Jetpack Search class with special features for the Plugin Directory
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Search {

	// Set this to true to disable the new class and use the old jetpack-search.php code.
	const USE_OLD_SEARCH = false;

	// Internal state - These are all overridden below, but here for reference purposes for a non-block english search.
	protected $locale          = 'en_US';
	protected $is_block_search = false;
	protected $is_english      = true;
	protected $en_boost        = 0.00001;
	protected $desc_boost      = 1;
	protected $desc_en_boost   = 0.00001;
	protected $title_boost     = 1;
	protected $title_en_boost  = 0.5;

	/**
	 * Fetch the instance of the Plugin_Search class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Plugin_Search constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		if ( isset( $_GET['s'] ) )
			return false;

		add_action( 'init', array( $this, 'init' ) );

		return false;
	}

	public function init() {

		if ( self::USE_OLD_SEARCH ) {
			// Instantiate our copy of the Jetpack_Search class.
			if ( class_exists( 'Jetpack' ) && ! class_exists( 'Jetpack_Search' )
				&& ! isset( $_GET['s'] ) ) { // Don't run the ES query if we're going to redirect to the pretty search URL
					require_once __DIR__ . '/libs/site-search/jetpack-search.php';
					\Jetpack_Search::instance();
			}
		} else {
			add_filter( 'jetpack_get_module', array( $this, 'jetpack_get_module' ), 10, 2 );
			add_filter( 'option_jetpack_active_modules', array( $this, 'option_jetpack_active_modules' ), 10, 1 );
			add_filter( 'pre_option_has_jetpack_search_product', array( $this, 'option_has_jetpack_search_product' ), 10, 1 );

			// add_filter( 'jetpack_search_abort', array( $this, 'log_jetpack_search_abort' ) );

			// $es_query_args = apply_filters( 'jetpack_search_es_query_args', $es_query_args, $query );

			add_filter( 'jetpack_search_es_wp_query_args', array( $this, 'jetpack_search_es_wp_query_args' ), 10, 2 );
			add_filter( 'jetpack_search_es_query_args', array( $this, 'jetpack_search_es_query_args' ), 10, 2 );
			add_filter( 'posts_pre_query', array( $this, 'set_max_num_pages' ), 15, 2 ); // After `Classic_Search::filter__posts_pre_query()`

			// Load Jetpack Search.
			include_once WP_PLUGIN_DIR . '/jetpack/vendor/autoload_packages.php';

			if ( class_exists( '\Automattic\Jetpack\Search\Classic_Search' ) ) {
				// New Jetpack
				\Automattic\Jetpack\Search\Classic_Search::instance();

			} else {
				// Old(er) Jetpack, load the classic search module, Temporarily.

				include_once WP_PLUGIN_DIR . '/jetpack/modules/search/class.jetpack-search.php';
				include_once WP_PLUGIN_DIR . '/jetpack/modules/search/class.jetpack-search-helpers.php';

				\Jetpack_Search::instance()->setup();
			}

		}

	}

	function var_export($expression, $return=FALSE) {
		$export = var_export($expression, TRUE);
		$patterns = [
			"/array \(/" => '[',
			"/^([ ]*)\)(,?)$/m" => '$1]$2',
			"/=>[ ]?\n[ ]+\[/" => '=> [',
			"/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
		];
		$export = preg_replace(array_keys($patterns), array_values($patterns), $export);
		if ((bool)$return) return $export; else echo $export;
	}

	public function option_jetpack_active_modules( $modules ) {
		if ( self::USE_OLD_SEARCH ) {
			if ( $i = array_search( 'search', $modules ) )
				unset( $modules[$i] );
		} else {
			$modules[] = 'search';
		}

		return array_unique( $modules );
	}

	public function option_has_jetpack_search_product( $option ) {
		if ( !self::USE_OLD_SEARCH ) {
			return true;
		}
		return $option;
	}

	/* Make sure the search module is available regardless of Jetpack plan.
	 * This works because search indexes were manually created for w.org.
	 */
	public function jetpack_get_module( $module, $slug ) {
		if ( !self::USE_OLD_SEARCH ) {
			if ( 'search' === $slug && isset( $module[ 'plan_classes' ] ) && !in_array( 'free', $module[ 'plan_classes' ] ) ) {
				$module[ 'plan_classes' ][] = 'free';
			}
		}

		return $module;
	}


	/**
	 * Localise the ES fields searched for localised queries.
	 */
	public function localise_es_fields( $fields ) {
		$localised_prefixes = [
			'all_content',
			'title',
			'excerpt',
			'description',
		];

		$localised_fields = array();

		foreach ( (array) $fields as $field ) {
			// title.ngram^1
			preg_match( '!^(?P<field>.+?)(?P<type>\.[a-z]+)?(?P<boost>\^(?P<boostval>[0-9.]+))?$!', $field, $m );

			$field     = $m['field'];
			$type      = $m['type'] ?? '';
			$boost     = $m['boost'] ?? '';
			$boost_val = floatval( $m['boostval'] ?? 1.0 );

			if ( ! in_array( $field, $localised_prefixes ) ) {
				$localised_fields[] = $field . $type . $boost;
				continue;
			}

			if ( $this->is_english ) {
				$localised_fields[] = $field . '_en' . $type . $boost;
				continue;
			}

			$en_boost = '^' . ( $this->en_boost * $boost_val );
			if ( 'description' === $field ) {
				$boost = '^' . ( $this->desc_boost * $boost_val );
				$en_boost = '^' . ( $this->desc_en_boost * $boost_val );
			} elseif ( 'title' === $field ) {
				$boost = '^' . ( $this->title_boost * $boost_val );
				$en_boost = '^' . ( $this->title_en_boost * $boost_val );
			}

			$localised_fields[] = $field . '_' . $this->locale . $type . $boost;
			$localised_fields[] = $field . '_en' . $type . $en_boost;
		}

		return $localised_fields;
	}

	public function jetpack_search_es_wp_query_args( $args, $query ) {

		// Block Search.
		$this->is_block_search = !empty( $query->query['block_search'] );
		if ( $this->is_block_search ) {
			$args['block_search'] = $query->query['block_search'];
		}

		// How much weighting to put on the Description field.
		// Blocks get a much lower value here, as it's more title/excerpt (short description) based.
		$this->desc_boost = $this->is_block_search ? 0.05 : 1;

		// Because most plugins don't have any translations we need to
		// correct for the very low scores that locale-specific fields.
		// end up getting. This is caused by the average field length being
		// very close to zero and thus the BM25 alg discounts fields that are
		// significantly longer.
		//
		// As of 2017-01-23 it looked like we were off by about 10,000x,
		// so rather than 0.1 we use a much smaller multiplier of en content
		$this->en_boost             = 0.00001;
		$this->desc_en_boost        = $this->desc_boost * $this->en_boost;

		// Most locales don't translate the title, so we only need to boost the title slightly lower.
		$this->title_en_boost       = $this->title_boost * 0.5;

		// We need to be locale aware for this
		$this->locale     = get_locale();
		$this->is_english = ( ! $this->locale || str_starts_with( $this->locale, 'en_' ) );

		$args['query_fields'] = $this->localise_es_fields( 'all_content' );

		return $args;
	}

	public function jetpack_search_es_query_args( $es_query_args, $query ) {
		// These are the things that jetpack_search_es_wp_query_args doesn't let us change, so we need to filter the es_query_args late in the code path to add more custom stuff.

		// Replace any existing filter with an AND for our custom filters.
		if ( ! isset( $es_query_args['filter']['and'] ) ) {
			// 'filter' will either be an `and` or term we need to wrap in an `and`.
			$es_query_args['filter'] = [
				'and' => $es_query_args['filter'] ? [ $es_query_args['filter'] ] : [],
			];
		}

		// Exclude 'disabled' plugins. This is separate from the 'status' field, which is used for the plugin status.
		$es_query_args['filter']['and'][] = [
			'term' => [
				'disabled' => false,
			]
		];

		// Limit to the Block Directory.
		if ( $this->is_block_search ) {
			$es_query_args['filter']['and'][] = [
				'term' => [
					'taxonomy.plugin_section.slug' => 'block',
				]
			];
		}

		// In phrase-search mode, the should is not present, and it's instead simply a `must` query.
		$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ] ??= [];

		// We'll always be adding function scoring.
		$es_query_args[ 'query' ][ 'function_score' ][ 'functions' ] ??= [];

		// The should match is where we add the fields to be searched in, and the weighting of them (boost).
		$should_match   = & $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ];

		// The must match is where the base query is present.
		$must_match     = & $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'must' ];

		// The function score is where calculations on fields occur.
		$function_score = & $es_query_args[ 'query' ][ 'function_score' ][ 'functions' ];

		// Determine what's actually being searched for according to ES.
		$search_phrase  = $must_match[0][ 'multi_match' ][ 'query' ] ?? ( $should_match[0][ 'multi_match' ][ 'query' ] ?? '' );

		// $phrase_search_mode = ( 'phrase' === $must_match[0][ 'multi_match' ][ 'type' ] );

		// Set boost on the match query, from jetpack_search_es_wp_query_args.
		if ( isset( $must_match[0][ 'multi_match' ] ) ) {
			$must_match[0][ 'multi_match' ][ 'boost' ] = 0.1;
		}

		// This extends the word search to additionally search in the title, excerpt, description and plugin_tags.
		// Note: This is not present in phrase searching mode.
		if ( isset( $should_match[0][ 'multi_match' ] ) ) {
			$should_match[0][ 'multi_match' ][ 'boost' ]  = 2;
			$should_match[0][ 'multi_match' ][ 'fields' ] = $this->localise_es_fields( [
				'title',
				'excerpt',
				'description^1',
				'taxonomy.plugin_tags.name',
			] );
		}

		// Setup the boosting for various fields.
		$should_match[] = [
			'multi_match' => [
				'query'  => $search_phrase,
				'fields' => $this->localise_es_fields( [ 'title.engram' ] ),
				'type'   => 'phrase',
				'boost'  => 2,
			],
		];

		// A direct slug match
		$should_match[] = [
			'multi_match' => [
				'query'  => $search_phrase,
				'fields' => $this->localise_es_fields( 'title', 'slug_text' ),
				'type'   => 'most_fields',
				'boost'  => 5,
			],
		];

		$should_match[] = [
			'multi_match' => [
				'query'  => $search_phrase,
				'fields' => $this->localise_es_fields( [
					'excerpt',
					'description^1',
					'taxonomy.plugin_tags.name',
				] ),
				'type'   => 'best_fields',
				'boost'  => 2,
			],
		];

		$should_match[] = [
			'multi_match' => [
				'query'  => $search_phrase,
				'fields' => $this->localise_es_fields( [
					'author',
					'contributors'
				] ),
				'type'   => 'best_fields',
				'boost'  => 3,
			],
		];

		// We'll overwrite the default Jetpack Search function scoring with our own.
		$function_score = [
			[
				// The more recent a plugin was updated, the more relevant it is.
				'exp' => [
					'plugin_modified' => [
						'origin' => date('Y-m-d'),
						'offset' => '180d',
						'scale'  => '360d',
						'decay'  => 0.5,
					],
				]
			],
			[
				// The older a plugins tested-up-to is, the less likely it's relevant.
				'exp' => [
					'tested' => [
						'origin' => sprintf( '%0.1f', defined( 'WP_CORE_STABLE_BRANCH' ) ? WP_CORE_STABLE_BRANCH : $GLOBALS['wp_version'] ),
						'offset' => 0.1,
						'scale'  => 0.4,
						'decay'  => 0.6,
					],
				],
			],
			[
				// A higher install base is a sign that the plugin will be relevant to the searcher.
				'field_value_factor' => [
					'field'    => 'active_installs',
					'factor'   => 0.375,
					'modifier' => 'log2p',
					'missing'  => 1,
				],
			],
			[
				// For plugins with less than 1 million installs, we need to adjust their scores a bit more.
				'filter' => [
					'range' => [
						'active_installs' => [
							'lte' => 1000000,
						],
					],
				],
				'exp' => [
					'active_installs' => [
						'origin' => 1000000,
						'offset' => 0,
						'scale'  => 900000,
						'decay'  => 0.75,
					],
				],
			],
			[
				// The more resolved support threads (as a percentage) a plugin has, the more responsive the developer is, and the better experience the end-user will have.
				'field_value_factor' => [
					'field'    => 'support_threads_resolved',
					'factor'   => 0.25,
					'modifier' => 'log2p',
					'missing'  => 0.5,
				],
			],
			[
				// A higher rated plugin is more likely to be preferred.
				'field_value_factor' => [
					'field'    => 'rating',
					'factor'   => 0.25,
					'modifier' => 'sqrt',
					'missing'  => 2.5,
				],
			],
		];

		unset( $es_query_args[ 'query' ][ 'function_score' ][ 'max_boost' ] );
		unset( $es_query_args[ 'query' ][ 'function_score' ][ 'score_mode' ] );

		// Couple of extra fields wanted in the response, mainly for debugging
		$es_query_args[ 'fields' ] = [
			'slug',
			'post_id',
		];

		return $es_query_args;
	}

	/**
	 * Limit the number of pagination links to 50.
	 *
	 * Jetpack ignores the `max_num_pages` that's set in `WP_Query` args and overrides it in
	 * `Classic_Search::filter__posts_pre_query()`. When there are more than 1,000 matches, their value causes
	 * Core's `paginate_links()` to generated 51 links, even though we redirect the user to the homepage when
	 * page 51+ is requested.
	 */
	function set_max_num_pages( $posts, $query ) {
		$post_type = (array) $query->query_vars['post_type'] ?? '';

		if ( is_admin() || ! is_search() || ! in_array( 'plugin', $post_type ) ) {
			return $posts;
		}

		if ( $query->max_num_pages > 50 ) {
			$query->max_num_pages = 50;
		}

		// Set the number of found plugins, ignoring pagination.
		$es_result = \Automattic\Jetpack\Search\Classic_Search::instance()->get_last_query_info();
		if ( $es_result && ! empty( $es_result['response']['results']['total'] ) ) {
			$query->found_posts = $es_result['response']['results']['total'];
		}

		return $posts;
	}

	public function log_search_es_wp_query_args( $es_wp_query_args, $query ) {
		error_log( '--- ' . __FUNCTION__ . ' ---' );
		error_log( $this->var_export( $es_wp_query_args, true ) );

		return $es_wp_query_args;
	}

	public function log_jetpack_search_abort( $reason ) {
		error_log( "--- jetpack_search_abort $reason ---" );
	}

	public function log_did_jetpack_search_query( $query ) {
		error_log( '--- did_jetpack_search_query ---' );
		error_log( $this->var_export( $query, true ) );
	}
}

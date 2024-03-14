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

		// We need to be locale aware for this
		$this->locale     = get_locale();
		$this->is_english = ( ! $this->locale || str_starts_with( $this->locale, 'en_' ) );

		if ( $this->is_english ) {
			$matching_fields = array(
				'all_content_en',
			);
		} else {
			$matching_fields = array(
				'all_content_' . $this->locale,
				'all_content_en^' . $this->en_boost,
			);
		}

		$args['query_fields'] = $matching_fields;

		return $args;
	}

	public function jetpack_search_es_query_args( $es_query_args, $query ) {
		// These are the things that jetpack_search_es_wp_query_args doesn't let us change, so we need to filter the es_query_args late in the code path to add more custom stuff.

		$es_query_args['filter']['and'] ??= [];

		// Exclude disabled plugins.
		$es_query_args['filter']['and'][] = [
			'term' => [
				'disabled' => [
					'value' => false,
				],
			]
		];

		if ( $this->is_block_search ) {
			// Limit to the Block Tax.
			$es_query_args['filter']['and'][] = [
				'term' => [
					'taxonomy.plugin_section.name' => [
						'value' => 'block'
					]
				]
			];
		}

		// Set boost on the match query

		if ( isset( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'must' ][0][ 'multi_match' ] ) ) {
			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'must' ][0][ 'multi_match' ][ 'boost' ] = 0.1;
		}

		// Old version had one less level here. Probably unimportant but this makes the unit tests pass.
		if ( isset( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'must' ][0] ) ) {
			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'must' ] = $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'must' ][0];
		}

		// Not sure if this matters, but again it's in the tests
		if ( isset( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][0][ 'multi_match' ][ 'operator' ] ) ) {
			unset( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][0][ 'multi_match' ][ 'operator' ] );
		}

		// Some extra fields here
		if ( isset( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][0][ 'multi_match' ] ) ) {
			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][0][ 'multi_match' ][ 'boost' ] = 2;
			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][0][ 'multi_match' ][ 'fields' ] = ( $this->is_english ? [
				0 => 'title_en',
				1 => 'excerpt_en',
				2 => 'description_en^1',
				3 => 'taxonomy.plugin_tags.name',
			] : [
				'title_' . $this->locale,
				'excerpt_' . $this->locale,
				'description_' . $this->locale . '^' . $this->desc_boost,
				'title_en^' . $this->en_boost,
				'excerpt_en^' . $this->en_boost,
				'description_en^' . $this->desc_en_boost,
				'taxonomy.plugin_tags.name',
			] );
		}

		// And some more fancy bits here
		if ( isset( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ] ) && 1 === count( $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ] ) ) {
			$search_phrase = $es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][0][ 'multi_match' ][ 'query' ];

			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][] = [
				'multi_match' => [
				'query' => $search_phrase,
				'fields' => ( $this->is_english ? [
					0 => 'title_en.ngram',
				] : [
					'title_' . $this->locale . '.ngram',
					'title_en.ngram^' . $this->en_boost,
				] ),
				'type' => 'phrase',
				'boost' => 2,
				],
			];

			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][] = [
				'multi_match' => [
				  'query' => $search_phrase,
				  'fields' => ( $this->is_english ? [
					0 => 'title_en',
					1 => 'slug_text',
				  ] : [
					'title_' . $this->locale,
					'title_en^' . $this->en_boost,
					'slug_text',
				  ] ),
				  'type' => 'most_fields',
				  'boost' => 5,
				],
			];

			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][] = [
				'multi_match' => [
				  'query' => $search_phrase,
				  'fields' => ( $this->is_english ? [
					0 => 'excerpt_en',
					1 => 'description_en^1',
					2 => 'taxonomy.plugin_tags.name',
				  ] : [
					'excerpt_' . $this->locale,
					'description_' . $this->locale . '^' . $this->desc_boost,
					'excerpt_en^' . $this->en_boost,
					'description_en^' . $this->desc_en_boost,
					'taxonomy.plugin_tags.name',
				  ] ),
				  'type' => 'best_fields',
				  'boost' => 2,
				],
			];

			$es_query_args[ 'query' ][ 'function_score' ][ 'query' ][ 'bool' ][ 'should' ][] = [
				'multi_match' => [
				  'query' => $search_phrase,
				  'fields' => [
					0 => 'author',
					1 => 'contributors',
				  ],
				  'type' => 'best_fields',
				  'boost' => 2,
				],
			];
		}

		if ( isset( $es_query_args[ 'query' ][ 'function_score' ][ 'functions' ] ) ) {
			$es_query_args[ 'query' ][ 'function_score' ][ 'functions' ] = [
				0 => [
				  'exp' => [
					'plugin_modified' => [
					  'origin' => date('Y-m-d'),
					  'offset' => '180d',
					  'scale' => '360d',
					  'decay' => 0.5,
					],
				  ],
				],
				1 => [
				  'exp' => [
					'tested' => [
					  'origin' => '5.0',
					  'offset' => 0.1,
					  'scale' => 0.4,
					  'decay' => 0.6,
					],
				  ],
				],
				2 => [
				  'field_value_factor' => [
					'field' => 'active_installs',
					'factor' => 0.375,
					'modifier' => 'log2p',
					'missing' => 1,
				  ],
				],
				3 => [
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
					  'scale' => 900000,
					  'decay' => 0.75,
					],
				  ],
				],
				4 => [
				  'field_value_factor' => [
					'field' => 'support_threads_resolved',
					'factor' => 0.25,
					'modifier' => 'log2p',
					'missing' => 0.5,
				  ],
				],
				5 => [
				  'field_value_factor' => [
					'field' => 'rating',
					'factor' => 0.25,
					'modifier' => 'sqrt',
					'missing' => 2.5,
				  ],
				],
			];
		}

		// Old version didn't have these
		unset( $es_query_args[ 'query' ][ 'function_score' ][ 'score_mode' ] );
		unset( $es_query_args[ 'query' ][ 'function_score' ][ 'max_boost' ] );
		unset( $es_query_args[ 'aggregations' ] );

		// Couple of extra fields wanted in the response, mainly for debugging
		$es_query_args[ 'fields' ] = [
			0 => 'slug',
			1 => 'post_id',
			2 => 'blog_id',
		];

		// Old version had things wrapped in an extra query => filtered layer.
		$es_query_args[ 'query' ] = [
			'filtered' => [
				'query' => $es_query_args[ 'query' ]
			]
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

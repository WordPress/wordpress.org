<?php

use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_Search;

class TestJetpackSearchClass extends WP_UnitTestCase {

	protected $plugin_search;

	function setUp(): void {
		if ( is_null( $this->plugin_search ) ) {
			require_once( ABSPATH . 'wp-content/plugins/jetpack/jetpack.php' );
			$this->plugin_search = Plugin_Search::instance();
			$this->plugin_search->init();


		}

		if ( !defined( 'WP_CORE_STABLE_BRANCH' ) ) {
			define( 'WP_CORE_STABLE_BRANCH', '5.0' );
		}
	}

	function test_assumptions() {
		$this->assertTrue( class_exists( 'Jetpack' ) );
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

	public function data_wp_es_to_es_args() {
		return [
			[
				[], // input
				[
					'blog_id' => 1,
					'size' => 10,
					'query' => [
					  'filtered' => [
						'query' => [
						  'function_score' => [
							'query' => [
							  'match_all' => [
							  ],
							],
							'functions' => [
							  0 => [
								'exp' => [
								  'plugin_modified' => [
									'origin' => 'YYYY-MM-DD',
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
							],
							'boost_mode' => 'multiply',
						  ],
						],
					  ],
					],
					'sort' => [
					  0 => [
						'date' => [
						  'order' => 'desc',
						],
					  ],
					],
					'filter' => [
					  'match_all' =>
					   [
					  ],
					],
				]
			],
			[
				[
					'query'          => 'an example search',
					'posts_per_page' => 27,
					'paged'          => 2,
					'orderby'        => '',
					'order'          => '',
					'filters'        => array(
						array( 'term' => array( 'disabled' => array( 'value' => false ) ) ),
					)
				],
				[
					'blog_id' => 1,
					'size' => 27,
					'from' => 27,
					'query' => [
					  'filtered' => [
						'query' => [
						  'function_score' => [
							'query' => [
							  'bool' => [
								'must' => [
								  'multi_match' => [
									'query' => 'an example search',
									'fields' => [
									  0 => 'all_content_en',
									],
									'boost' => 0.1,
									'operator' => 'and',
								  ],
								],
								'should' => [
								  0 => [
									'multi_match' => [
									  'query' => 'an example search',
									  'fields' => [
										0 => 'title_en',
										1 => 'excerpt_en',
										2 => 'description_en^1',
										3 => 'taxonomy.plugin_tags.name',
									  ],
									  'type' => 'phrase',
									  'boost' => 2,
									],
								  ],
								  1 => [
									'multi_match' => [
									  'query' => 'an example search',
									  'fields' => [
										0 => 'title_en.ngram',
									  ],
									  'type' => 'phrase',
									  'boost' => 0.2,
									],
								  ],
								  2 => [
									'multi_match' => [
									  'query' => 'an example search',
									  'fields' => [
										0 => 'title_en',
										1 => 'slug_text',
									  ],
									  'type' => 'best_fields',
									  'boost' => 2,
									],
								  ],
								  3 => [
									'multi_match' => [
									  'query' => 'an example search',
									  'fields' => [
										0 => 'excerpt_en',
										1 => 'description_en^1',
										2 => 'taxonomy.plugin_tags.name',
									  ],
									  'type' => 'best_fields',
									  'boost' => 2,
									],
								  ],
								  4 => [
									'multi_match' => [
									  'query' => 'an example search',
									  'fields' => [
										0 => 'author',
										1 => 'contributors',
									  ],
									  'type' => 'best_fields',
									  'boost' => 2,
									],
								  ],
								],
							  ],
							],
							'functions' => [
							  0 => [
								'exp' => [
								  'plugin_modified' => [
									'origin' => 'YYYY-MM-DD',
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
							],
							'boost_mode' => 'multiply',
						  ],
						],
					  ],
					],
					'sort' => [
					  0 => [
						'_score' => [
						  'order' => 'desc',
						],
					  ],
					],
					'filter' => [
					  'and' => [
						0 => [
						  'term' => [
							'disabled' => [
							  'value' => false,
							],
						  ],
						],
					  ],
					],
				]
			],

		];
	}

	public function normalize_es_arg_callback( &$item, $key ) {
		// Change an empty object to an array, because assertSame rejects equivalent objects
		if ( 'match_all' === $key && is_object( $item ) ) {
			$item = [];
		}
		// Mask any dates
		if ( is_string( $item ) && preg_match( '/^\d\d\d\d-\d\d-\d\d$/', $item ) ) {
			$item = 'YYYY-MM-DD';
		}

	}
	public function normalize_es_args( $args ) {
		array_walk_recursive( $args, [ $this, 'normalize_es_arg_callback' ] );
		#ksort( $args );
		return $args;
	}

	/**
	 * @dataProvider data_wp_es_to_es_args
	 * Note: doesn't actually require the function. This test only makes sense with the old class, and filter__posts_request is a way to detect it.
	 * @requires function Jetpack_Search::filter__posts_request
	 */
	public function test_convert_wp_es_to_es_args( $input, $expected ) {
		$actual = Jetpack_Search::instance()->convert_wp_es_to_es_args( $input );

		$this->assertSame( $this->normalize_es_args( $expected ), $this->normalize_es_args( $actual ) );

	}


	public function test_filter__posts_request() {
		$search = $this->getMockBuilder( Jetpack_Search::class )->disableOriginalConstructor()->setMethods( ['search'] )->getMock();

		// This is what should get passed to the Jetpack_Search::search() function.
		$expects_args = [
			'blog_id' => 1,
			'size' => 10,
			'from' => 0,
			'query' => [
			  'filtered' => [
				'query' => [
				  'function_score' => [
					'query' => [
					  'bool' => [
						'must' => [
						  'multi_match' => [
							'query' => 'a test search',
							'fields' => [
							  0 => 'all_content_en',
							],
							'boost' => 0.1,
							'operator' => 'and',
						  ],
						],
						'should' => [
						  0 => [
							'multi_match' => [
							  'query' => 'a test search',
							  'fields' => [
								0 => 'title_en',
								1 => 'excerpt_en',
								2 => 'description_en^1',
								3 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'phrase',
							  'boost' => 2,
							],
						  ],
						  1 => [
							'multi_match' => [
							  'query' => 'a test search',
							  'fields' => [
								0 => 'title_en.ngram',
							  ],
							  'type' => 'phrase',
							  'boost' => 0.2,
							],
						  ],
						  2 => [
							'multi_match' => [
							  'query' => 'a test search',
							  'fields' => [
								0 => 'title_en',
								1 => 'slug_text',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  3 => [
							'multi_match' => [
							  'query' => 'a test search',
							  'fields' => [
								0 => 'excerpt_en',
								1 => 'description_en^1',
								2 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  4 => [
							'multi_match' => [
							  'query' => 'a test search',
							  'fields' => [
								0 => 'author',
								1 => 'contributors',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						],
					  ],
					],
					'functions' => [
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
					],
					'boost_mode' => 'multiply',
				  ],
				],
			  ],
			],
			'sort' => [
			  0 => [
				'_score' => [
				  'order' => 'desc',
				],
			  ],
			],
			'filter' => [
			  'and' => [
				0 => [
				  'term' => [
					'disabled' => [
					  'value' => false,
					],
				  ],
				],
			  ],
			],
			'fields' => [
			  0 => 'slug',
			  1 => 'post_id',
			  2 => 'blog_id',
			],
		];

		$search->expects( $this->once() )->method( 'search' )->with( $this->equalTo( $expects_args ) );

		// Construct a simple search query
		$query = new WP_Query( [ 's' => 'a test search' ] );
		global $wp_the_query;
		$wp_the_query = $query; // so is_main_query() is true, otherwise the filter will short-circuit.

		// Manually call the filter function, which should satisfy the expects() condition above.
		if ( method_exists( $search, 'filter__posts_pre_query' ) ) {
			$out = $search->filter__posts_pre_query( $query->request, $query );
		} else {
			$out = $search->filter__posts_request( $query->request, $query );
		}

	}

	public function test_filter__posts_request_locale() {
		$search = $this->getMockBuilder( Jetpack_Search::class )->disableOriginalConstructor()->setMethods( ['search'] )->getMock();

		// This is what should get passed to the Jetpack_Search::search() function.
		$expects_args = [
			'blog_id' => 1,
			'size' => 10,
			'from' => 0,
			'query' => [
			  'filtered' => [
				'query' => [
				  'function_score' => [
					'query' => [
					  'bool' => [
						'must' => [
						  'multi_match' => [
							'query' => 'una búsqueda de locale',
							'fields' => [
							  0 => 'all_content_es',
							  1 => 'all_content_en^1.0E-5',
							],
							'boost' => 0.1,
							'operator' => 'and',
						  ],
						],
						'should' => [
						  0 => [
							'multi_match' => [
							  'query' => 'una búsqueda de locale',
							  'fields' => [
								0 => 'title_es',
								1 => 'excerpt_es',
								2 => 'description_es^1',
								3 => 'title_en^1.0E-5',
								4 => 'excerpt_en^1.0E-5',
								5 => 'description_en^1.0E-5',
								6 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'phrase',
							  'boost' => 2,
							],
						  ],
						  1 => [
							'multi_match' => [
							  'query' => 'una búsqueda de locale',
							  'fields' => [
								0 => 'title_es.ngram',
								1 => 'title_en.ngram^1.0E-5',
							  ],
							  'type' => 'phrase',
							  'boost' => 0.2,
							],
						  ],
						  2 => [
							'multi_match' => [
							  'query' => 'una búsqueda de locale',
							  'fields' => [
								0 => 'title_es',
								1 => 'title_en^1.0E-5',
								2 => 'slug_text',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  3 => [
							'multi_match' => [
							  'query' => 'una búsqueda de locale',
							  'fields' => [
								0 => 'excerpt_es',
								1 => 'description_es^1',
								2 => 'excerpt_en^1.0E-5',
								3 => 'description_en^1.0E-5',
								4 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  4 => [
							'multi_match' => [
							  'query' => 'una búsqueda de locale',
							  'fields' => [
								0 => 'author',
								1 => 'contributors',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						],
					  ],
					],
					'functions' => [
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
					],
					'boost_mode' => 'multiply',
				  ],
				],
			  ],
			],
			'sort' => [
			  0 => [
				'_score' => [
				  'order' => 'desc',
				],
			  ],
			],
			'filter' => [
			  'and' => [
				0 => [
				  'term' => [
					'disabled' => [
					  'value' => false,
					],
				  ],
				],
			  ],
			],
			'fields' => [
			  0 => 'slug',
			  1 => 'post_id',
			  2 => 'blog_id',
			],
		];


		$search->expects( $this->once() )->method( 'search' )->with( $this->equalTo( $expects_args ) );

		// Set the locale to Spanish
		global $locale;
		$locale = 'es';

		// Construct a simple search query
		$query = new WP_Query( [ 's' => 'una búsqueda de locale' ] );
		global $wp_the_query;
		$wp_the_query = $query; // so is_main_query() is true, otherwise the filter will short-circuit.

		// Manually call the filter function, which should satisfy the expects() condition above.
		if ( method_exists( $search, 'filter__posts_pre_query' ) ) {
			$out = $search->filter__posts_pre_query( $query->request, $query );
		} else {
			$out = $search->filter__posts_request( $query->request, $query );
		}

	}

	public function test_filter__posts_request_block() {
		$search = $this->getMockBuilder( Jetpack_Search::class )->disableOriginalConstructor()->setMethods( ['search'] )->getMock();

		// This is what should get passed to the Jetpack_Search::search() function.
		$expects_args = [
			'blog_id' => 1,
			'size' => 10,
			'from' => 0,
			'query' => [
			  'filtered' => [
				'query' => [
				  'function_score' => [
					'query' => [
					  'bool' => [
						'must' => [
						  'multi_match' => [
							'query' => 'my/block',
							'fields' => [
							  0 => 'all_content_es',
							  1 => 'all_content_en^1.0E-5',
							],
							'boost' => 0.1,
							'operator' => 'and',
						  ],
						],
						'should' => [
						  0 => [
							'multi_match' => [
							  'query' => 'my/block',
							  'fields' => [
								0 => 'title_es',
								1 => 'excerpt_es',
								2 => 'description_es^0.05',
								3 => 'title_en^1.0E-5',
								4 => 'excerpt_en^1.0E-5',
								5 => 'description_en^5.0E-7',
								6 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'phrase',
							  'boost' => 2,
							],
						  ],
						  1 => [
							'multi_match' => [
							  'query' => 'my/block',
							  'fields' => [
								0 => 'title_es.ngram',
								1 => 'title_en.ngram^1.0E-5',
							  ],
							  'type' => 'phrase',
							  'boost' => 0.2,
							],
						  ],
						  2 => [
							'multi_match' => [
							  'query' => 'my/block',
							  'fields' => [
								0 => 'title_es',
								1 => 'title_en^1.0E-5',
								2 => 'slug_text',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  3 => [
							'multi_match' => [
							  'query' => 'my/block',
							  'fields' => [
								0 => 'excerpt_es',
								1 => 'description_es^0.05',
								2 => 'excerpt_en^1.0E-5',
								3 => 'description_en^5.0E-7',
								4 => 'taxonomy.plugin_tags.name',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						  4 => [
							'multi_match' => [
							  'query' => 'my/block',
							  'fields' => [
								0 => 'author',
								1 => 'contributors',
							  ],
							  'type' => 'best_fields',
							  'boost' => 2,
							],
						  ],
						],
					  ],
					],
					'functions' => [
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
					],
					'boost_mode' => 'multiply',
				  ],
				],
			  ],
			],
			'sort' => [
			  0 => [
				'_score' => [
				  'order' => 'desc',
				],
			  ],
			],
			'filter' => [
			  'and' => [
				0 => [
				  'term' => [
					'disabled' => [
					  'value' => false,
					],
				  ],
				],
				1 => [
				  'term' => [
					'taxonomy.plugin_section.name' => [
					  'value' => 'block',
					],
				  ],
				],
			  ],
			],
			'fields' => [
			  0 => 'slug',
			  1 => 'post_id',
			  2 => 'blog_id',
			],
		];


		$search->expects( $this->once() )->method( 'search' )->with( $this->equalTo( $expects_args ) );

		// Construct a block search query
		$query = new WP_Query( [ 's' => 'my/block', 'block_search' => true ] );
		global $wp_the_query;
		$wp_the_query = $query; // so is_main_query() is true, otherwise the filter will short-circuit.

		// Manually call the filter function, which should satisfy the expects() condition above.
		if ( method_exists( $search, 'filter__posts_pre_query' ) ) {
			$out = $search->filter__posts_pre_query( $query->request, $query );
		} else {
			$out = $search->filter__posts_request( $query->request, $query );
		}

	}
}
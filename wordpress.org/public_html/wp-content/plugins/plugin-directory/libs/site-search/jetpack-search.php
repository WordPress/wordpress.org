<?php
/*
 * WARNING: This file is distributed verbatim in Jetpack.
 * There should be nothing WordPress.com specific in this file.
 * Extend WPCOM_Search instead.
 *
 * @hide-in-jetpack
 */

/*
 * This is a preliminary version of Jetpack Search.
 * It is highly likely that 95% of the time the search will not be using the loop.
 *
 */

/*
 * Known Bugs:
 *  - no real Jetpack support yet: missing public API
 *  - lots of TODO
 *  - phrase searching, and other searches that WP already supports
 *  - paging (especially with max number of results)
 *  - reduce number of queries for getting posts? use an IN query when possible?
 *  - check that post is displayable
 *  - infinite scroll?
 *  - tags/cats/author filtering from query args not working
 *  - multi-lingual field searches
 *  - multi-lingual needs more testing
 *  - Jetpack Query Parser currently depends on wp.com data to do the parsing. hmmm...
 *  - username parsing from query args is wp.com specific
 *
 *
 * O2 Known Bugs:
 *  - sticky posts in search results?
 *  - should we override the results display so that highlighted results are shown?
 *
 * Missing Features:
 *  - Filtering of what gets searched (hooks for dates, authors, tags, etc)
 *  - other search syntx that WP already supports
 *  - sorting by date or whatever
 *  - tracking clicks
 *  - highlighting using the ES highlighter? Offload from user's server?
 *    - can we make this pretty? use the excerpts to correct the original content?
 *  - show images in results
 *
 * Untested:
 *  - test different taxonomy filters
 *  - author filtering
 *  - faceting
 *
 */

require_once __DIR__ . '/class.jetpack-searchresult-posts-iterator.php';

class Jetpack_Search {

	protected $do_found_posts;
	protected $found_posts = 0;

	protected $search_query;
	protected $search_result;

	protected $original_blog_id;
	protected $jetpack_blog_id;

	protected $blog_lang;

	protected static $instance;

	// Languages with custom analyzers, other languages are supported,
	// but are analyzed with the default analyzer.
	public static $analyzed_langs = array( 'ar', 'bg', 'ca', 'cs', 'da', 'de', 'el', 'en', 'es', 'eu', 'fa', 'fi', 'fr', 'he', 'hi', 'hu', 'hy', 'id', 'it', 'ja', 'ko', 'nl', 'no', 'pt', 'ro', 'ru', 'sv', 'tr', 'zh' );

	const CACHE_GROUP        = 'jetpack-search';
	const CACHE_EXPIRY       = 300;
	const ERROR_COUNT_KEY    = 'error-count-';
	const ERROR_COUNT_WINDOW = 60; // seconds

	protected function __construct() {
		/* Don't do anything, needs to be initialized via instance() method */
	}

	public function __clone() {
		wp_die( "Please don't __clone WPCOM_elasticsearch" ); }

	public function __wakeup() {
		wp_die( "Please don't __wakeup WPCOM_elasticsearch" ); }

	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Jetpack_Search();
			self::$instance->setup();
		}
		return self::$instance;
	}

	public function setup() {
		// TODO: only enable if this site is public (otherwise we don't have content)
		// TODO: check that the module is activated
		$this->jetpack_blog_id = Jetpack::get_option( 'id' );

		if ( ! is_admin() ) {
			$this->init_hooks();
		}
	}

	public function set_lang( $lang = false ) {
		if ( ! $lang ) {
			// TODO: don't think this works for Jetpack
			$blog = get_blog_details( $blog_id );
			$lang = get_lang_code_by_id( $blog->lang_id );
		}
		$this->blog_lang = $lang;
	}

	//
	// Lots of hooks
	public function init_hooks() {
		// Checks to see if we need to worry about found_posts
		add_filter( 'post_limits_request', array( $this, 'filter__post_limits_request' ), 999, 2 );

		// Note: Advanced Post Cache hooks in at 10 so it's important to hook in before that
		// Force $q['cache_results'] = false; this prevents the un-inflated WP_Post objects from being stored in cache
		add_action( 'pre_get_posts', array( $this, 'action__pre_get_posts' ), 5 );

		// Run the ES query and kill the standard search query - allow the 'the_posts' filter to handle inflation
		add_filter( 'posts_request', array( $this, 'filter__posts_request' ), 5, 2 );

		// Nukes the FOUND_ROWS() database query
		add_filter( 'found_posts_query', array( $this, 'filter__found_posts_query' ), 5, 2 );

		// Since the FOUND_ROWS() query was nuked, we need to supply the total number of found posts
		add_filter( 'found_posts', array( $this, 'filter__found_posts' ), 5, 2 );

		// Hook into the_posts to return posts from the ES results
		add_filter( 'the_posts', array( $this, 'filter__the_posts' ), 5, 2 );

		// Let ES worry about stopwords
		add_filter( 'wp_search_stopwords', '__return_empty_array', 5 );

		add_filter( 'jetpack_search_es_wp_query_args', array( $this, 'filter__add_date_filter_to_query' ), 10, 2 );

		// Debug
		if ( file_exists( __DIR__ . '/jetpack-search-debug.php' ) ) {
			include_once __DIR__ . '/jetpack-search-debug.php';
		}
	}

	/**
	 * Register the hooks needed to transparently handle posts in The Loop
	 *
	 * Handles inflating the post, switching to the appropriate blog context, and setting up post data
	 */
	public function register_loop_hooks() {
		add_action( 'loop_start', array( $this, 'action__loop_start' ) );
		add_action( 'loop_end', array( $this, 'action__loop_end' ) );
	}

	/**
	 * Unregister the hooks for The Loop
	 *
	 * Needs to be called when the search Loop is complete, so later queries are not affected
	 */
	public function unregister_loop_hooks() {
		remove_action( 'the_post', array( $this, 'action__the_post' ) );
		remove_action( 'loop_end', array( $this, 'action__loop_end' ) );
	}


	//
	// Raw Search Query
	/*
	 * Return a count of the number of search API errors within the last ERROR_COUNT_WINDOW seconds
	 */
	protected function get_error_volume() {
		// Use a dual-tick window like nonces
		$tick = ceil( time() / ( self::ERROR_COUNT_WINDOW / 2 ) );

		return intval( wp_cache_get( self::ERROR_COUNT_KEY . $tick, self::CACHE_GROUP ) )
			 + intval( wp_cache_get( self::ERROR_COUNT_KEY . ( $tick - 1 ), self::CACHE_GROUP ) );
	}

	/*
	 *	Increment the recent error volume by $count.
	 */
	protected function increment_error_volume( $count = 1 ) {
		// wp_cache_incr() bails if the key does not exist
		$tick = ceil( time() / ( self::ERROR_COUNT_WINDOW / 2 ) );
		wp_cache_add( self::ERROR_COUNT_KEY . $tick, 0, self::CACHE_GROUP, self::ERROR_COUNT_WINDOW );
		return wp_cache_incr( self::ERROR_COUNT_KEY . $tick, $count, self::CACHE_GROUP );
	}

	/*
	 * Determine whether or not the recent error volume is low enough to allow a fresh API call.
	 */
	protected function error_volume_is_low() {
		// This cache key keeps a global-ish count of recent errors (per memcache instance).
		// Used for random exponential backoff when errors start to pile up, as they will if there is a network outage for example.
		$error_volume = max( $this->get_error_volume(), 0 ); // >= 0

		// This gives us a threshold with a gentle curve from 10 down to 0
		// The idea being that for a small volume of errors ( < 10 ) we'll have a 100% chance of attempting a new search
		// For 10-15 errors, an 80-90% chance
		// For 20 errors, a 50% chance
		// For 40+ errors, a 0% chance
		$threshold = ceil( 10 / ( 1 + pow( $error_volume / 20, 4 ) ) );
		return mt_rand( 1, 10 ) <= $threshold;
	}

	/*
	 * Trigger a search error message and increment the recent error volume.
	 */
	protected function search_error( $reason ) {
		trigger_error( 'Plugin directory search: ' . $reason, E_USER_WARNING );
		return $this->increment_error_volume();
	}

	/*
	 * Run a search on the WP.com public API.
	 *
	 * @param object $es_args : Args conforming to the WP.com /sites/<blog_id>/search endpoint
	 * @return object : the response from the public api (could be a WP_Error)
	 */
	public function search( $es_args ) {
		$service_url  = 'https://public-api.wordpress.com/rest/v1/sites/' . $this->jetpack_blog_id . '/search';
		$json_es_args = wp_json_encode( $es_args );
		$cache_key    = md5( $json_es_args );
		$lock_key     = 'lock-' . $cache_key;

		// If the Query args were not able to be encoded, bail.
		if ( ! $json_es_args ) {
			return false;
		}

		$response = wp_cache_get( $cache_key, self::CACHE_GROUP );

		// Use a temporary lock to prevent cache stampedes.
		// This will ensure that a maximum number of processes are performing the same search, or one when the stale value is still known.
		// Other processes will use the stale cached value if it's present, even for a while after the expiration time if a fresh value is still being fetched.
		$maximum_concurrent_requests = 3;

		$do_fresh_request = false;
		if ( wp_cache_add( $lock_key, 1, self::CACHE_GROUP, 20 ) ) {
			$do_fresh_request = true;
		} elseif ( ! $response && $maximum_concurrent_requests >= wp_cache_incr( $lock_key, 1, self::CACHE_GROUP ) ) {
			// If we don't have cached data, we haven't exceeded the maximum concurrent requests and, error volume is low, still perform the request.
			$do_fresh_request = $this->error_volume_is_low();
		}

		if ( $do_fresh_request ) {

			// If the error volume is high, there's a proportionally lower chance that we'll actually attempt to hit the API.
			if ( $this->error_volume_is_low() ) {
				$request = wp_remote_post( $service_url, array(
					'headers'    => array(
						'Content-Type' => 'application/json',
					),
					'timeout'    => 10,
					'user-agent' => 'WordPress.org/jetpack_search',
					'body'       => $json_es_args,
				) );
			} else {
				trigger_error( 'Plugin directory search: skipping search due to high error volume', E_USER_WARNING );
				// Hopefully we still have a cached response to return
				return $response;
			}

			// If there's a network or HTTP error, we'll intentionally leave the temporary lock to expire in a few seconds.
			// Other requests during that window will use the stale cached value. We'll try another remote request once this lock expires.
			if ( is_wp_error( $request ) || 200 != wp_remote_retrieve_response_code( $request ) ) {

				// Lock further requests for the same search for 3-7 seconds. We probably don't need anything more complex like exponential backoff here because this is per search.
				wp_cache_set( $lock_key, 2, self::CACHE_GROUP, mt_rand( 3, 7 ) );

				if ( is_wp_error( $request ) ) {
					$this->search_error( 'http error ' . $request->get_error_message(), E_USER_WARNING );
				} else {
					$es_error = json_decode( wp_remote_retrieve_body( $request ), true );

					$this->search_error(
						sprintf( 'http status %d %s %s',
							wp_remote_retrieve_response_code( $request ),
							$es_error['error'] ?? '',
							$es_error['message'] ?? ''
						),
						E_USER_WARNING
					);
				}

				// If we have a stale cached response, return that. Otherwise, return the error object.
				if ( $response ) {
					return $response; // Stale cached response.
				}
				return $request; // Fresh error object.
			}

			$fresh_response = json_decode( wp_remote_retrieve_body( $request ), true );

			if ( ! $fresh_response || isset( $fresh_response['error'] ) ) {
				// As above, lock further requests for the same search for a few seconds
				wp_cache_set( $lock_key, 1, self::CACHE_GROUP, mt_rand( 3, 7 ) );

				if ( isset( $fresh_response['error'] ) ) {
					$this->search_error( 'remote error ' . $fresh_response['error'], E_USER_WARNING );
				} else {
					$this->search_error( 'invalid json response', E_USER_WARNING );
				}

				// Return a stale response if we have one
				if ( $response ) {
					return $response;
				}
				return $fresh_response; // Fresh error object as a last resort

			} else {
				// The cached value has a TTL twice as long as the exipration time.
				// The lock TTL serves as our expiration timer.
				wp_cache_set( $cache_key, $fresh_response, self::CACHE_GROUP, self::CACHE_EXPIRY * 2 );
				wp_cache_set( $lock_key, 1, self::CACHE_GROUP, self::CACHE_EXPIRY );
				$response = $fresh_response;
			}
		} else {
			// Stampede protection has kicked in, AND we have no stale cached value to display. That's bad - possibly indicates cache exhaustion
			if ( false === $response ) {
				trigger_error( 'Plugin directory search: no cached results available during stampede.', E_USER_WARNING );
			}
		}

		return $response;
	}

	// TODO: add secured search for posts/comments
	//
	// Insert the ES results into the Loop when searching
	//
	public function filter__post_limits_request( $limits, $query ) {
		if ( ! $query->is_search() ) {
			return $limits;
		}

		if ( empty( $limits ) || $query->get( 'no_found_rows' ) ) {
			$this->do_found_posts = false;
		} else {
			$this->do_found_posts = true;
		}

		return $limits;
	}

	public function filter__the_posts( $posts, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $posts;
		}

		if ( ! is_array( $this->search_result ) || ! isset( $this->search_result['results'] ) ) {
			return $posts;
		}

		// This class handles the heavy lifting of transparently switching blogs and inflating posts
		$this->posts_iterator = new Jetpack_SearchResult_Posts_Iterator();
		$this->posts_iterator->set_search_result( $this->search_result );

		$posts = array();

		// We have to return something in $posts for regular search templates to work, so build up an array
		// of simple, un-inflated WP_Post objects that will be inflated by Jetpack_SearchResult_Posts_Iterator in The Loop
		foreach ( $this->search_result['results']['hits'] as $result ) {
			// Create an empty WP_Post object that will be inflated later
			$post = new stdClass();

			$post->ID      = $result['fields']['post_id'];
			$post->blog_id = $result['fields']['blog_id'];

			// Run through get_post() to add all expected properties (even if they're empty)
			$post = get_post( $post );

			if ( $post ) {
				$posts[] = $post;
			}
		}

		// Listen for the start/end of The Loop, to add some action handlers for transparently loading the post
		$this->register_loop_hooks();

		return $posts;
	}

	public function filter__posts_request( $sql, $query ) {
		global $wpdb;

		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $sql;
		}

		$page           = ( $query->get( 'paged' ) ) ? absint( $query->get( 'paged' ) ) : 1;
		$posts_per_page = $query->get( 'posts_per_page' );

		// ES API does not allow more than 15 results at a time
		if ( $posts_per_page > 200 ) {
			$posts_per_page = 200;
		}

		// ES API does not allow fetching past the 9,000th post
		$page = min( $page, floor( 9000 / $posts_per_page ) );

		$date_cutoff    = strftime( '%Y-%m-%d', strtotime( '-8 years' ) );
		$date_today     = strftime( '%Y-%m-%d' );
		$version_cutoff = ( defined( 'WP_CORE_STABLE_BRANCH' ) ? sprintf( '%0.1f', WP_CORE_STABLE_BRANCH - 0.5 ) : '4.0' );

		// Start building the WP-style search query args
		// They'll be translated to ES format args later
		$es_wp_query_args = array(
			'query'          => mb_substr( $query->get( 's' ), 0, 200 ), // Limit search query to prevent "Request size too big" errors.
			'posts_per_page' => $posts_per_page,
			'paged'          => $page,
			'orderby'        => $query->get( 'orderby' ),
			'order'          => $query->get( 'order' ),
			// plugin directory specific:
			// 'date_range'    =>  array( 'field' => 'modified', 'gte' => $date_cutoff ),
			// 'tested_range'  =>  array( 'field' => 'tested', 'gte' => $version_cutoff ),
			'filters'        => array(
				array( 'term' => array( 'disabled' => array( 'value' => false ) ) ),
			),
		);

		if ( defined( 'WPORG_IS_API' ) && WPORG_IS_API ) {
			// Look for plugins excluding those already installed
			if ( isset( $query->query['installed_plugins'] ) ) {
				$es_wp_query_args['filters'][] = array( 'not' => array( 'terms' => array( 'slug' => $query->query['installed_plugins'] ) ) );
			}
		}

		// Block Search.
		if ( !empty( $query->query['block_search'] ) ) {
			$es_wp_query_args['block_search'] = $query->query['block_search'];

			// Limit to the Block Tax.
			$es_wp_query_args['filters'][] = array(
				'term' => array(
					'taxonomy.plugin_section.name' => array(
						'value' => 'block'
					)
				)
			);
		}

		$es_wp_query_args['locale'] = $query->get( 'locale' ) ?: get_locale();

		// You can use this filter to modify the search query parameters, such as controlling the post_type.
		// These arguments are in the format for convert_wp_es_to_es_args(), i.e. WP-style.
		$es_wp_query_args = apply_filters( 'jetpack_search_es_wp_query_args', $es_wp_query_args, $query );

		// Convert the WP-style args into ES args
		$es_query_args = $this->convert_wp_es_to_es_args( $es_wp_query_args );

		// Only trust ES to give us IDs, not the content since it is a mirror
		$es_query_args['fields'] = array(
			'slug',
			'support_threads_resolved',
			'support_threads_percentage',
			'support_resolution_yes',
			'support_resolution_no',
			'support_resolution_percentage',
			'active_installs',
			'contributors_active_installs',
			'tested',
			'rating',
			'plugin_modified',
			'post_id',
			'blog_id',
		);

		// This filter is harder to use if you're unfamiliar with ES but it allows complete control over the query
		$es_query_args      = apply_filters( 'jetpack_search_es_query_args', $es_query_args, $query );
		$this->search_query = $es_query_args;

		// Do the actual search query!
		$this->search_result = $this->search( $es_query_args );

		if ( is_wp_error( $this->search_result ) || ! is_array( $this->search_result ) || empty( $this->search_result['results'] ) || empty( $this->search_result['results']['hits'] ) ) {
			$this->found_posts = 0;
			return '';
		}

		// Total number of results for paging purposes
		$this->found_posts = min( $this->search_result['results']['total'], 9000 ); // The Jetpack search API errors out if we try to request past 9k

		// Don't select anything, posts are inflated by Jetpack_SearchResult_Posts_Iterator
		// in The Loop, to allow for multi site search
		return '';
	}


	public function filter__found_posts_query( $sql, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $sql;
		}

		return '';
	}

	public function filter__found_posts( $found_posts, $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return $found_posts;
		}

		return $this->found_posts;
	}

	public function action__pre_get_posts( $query ) {
		// Treat an API request for the recommended tab as a search, even though there is no search string in the query
		if ( defined( 'WPORG_IS_API' ) && WPORG_IS_API && isset( $query->query['browse'] ) && $query->query['browse'] === 'recommended' ) {
			$query->is_search = true;
		}

		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$query->set( 'cache_results', false );
	}

	public function action__loop_start( $query ) {
		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		add_action( 'the_post', array( $this, 'action__the_post' ) );

		$this->original_blog_id = get_current_blog_id();
	}

	public function action__loop_end( $query ) {
		// Once The Loop is finished, remove any hooks so future queries are unaffected by our shenanigans
		$this->unregister_loop_hooks();

		if ( ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		// Restore the original blog, if we're not on it
		if ( get_current_blog_id() !== $this->original_blog_id ) {
			switch_to_blog( $this->original_blog_id );
		}
	}

	public function action__the_post( &$post ) {
		global $id, $post, $wp_query, $authordata, $currentday, $currentmonth, $page, $pages, $multipage, $more, $numpages;

		$post = $this->get_post_by_index( $wp_query->current_post );

		if ( ! $post ) {
			return;
		}

		// Do some additional setup that normally happens in setup_postdata(), but gets skipped
		// in this plugin because the posts hadn't yet been inflated.
		$authordata = get_userdata( $post->post_author );

		$currentday   = mysql2date( 'd.m.y', $post->post_date, false );
		$currentmonth = mysql2date( 'm', $post->post_date, false );

		$numpages  = 1;
		$multipage = 0;
		$page      = get_query_var( 'page' );
		if ( ! $page ) {
			$page = 1;
		}
		if ( is_single() || is_page() || is_feed() ) {
			$more = 1;
		}
		$content = $post->post_content;
		if ( false !== strpos( $content, '<!--nextpage-->' ) ) {
			if ( $page > 1 ) {
				$more = 1;
			}
			$content = str_replace( "\n<!--nextpage-->\n", '<!--nextpage-->', $content );
			$content = str_replace( "\n<!--nextpage-->", '<!--nextpage-->', $content );
			$content = str_replace( "<!--nextpage-->\n", '<!--nextpage-->', $content );
			// Ignore nextpage at the beginning of the content.
			if ( 0 === strpos( $content, '<!--nextpage-->' ) ) {
				$content = substr( $content, 15 );
			}
			$pages    = explode( '<!--nextpage-->', $content );
			$numpages = count( $pages );
			if ( $numpages > 1 ) {
				$multipage = 1;
			}
		} else {
			$pages = array( $post->post_content );
		}
	}

	/**
	 * Retrieve a full post by it's index in search results
	 */
	public function get_post_by_index( $index ) {
		return $this->posts_iterator[ $index ];
	}

	public function get_search_result( $raw = false ) {
		if ( $raw ) {
			return $this->search_result;
		}

		return ( ! empty( $this->search_result ) && ! is_wp_error( $this->search_result ) && is_array( $this->search_result ) && ! empty( $this->search_result['results'] ) ) ? $this->search_result['results'] : false;
	}

	public function get_search_query() {
		return $this->search_query;
	}

	//
	// Standard Filters Applied to the search query
	//
	public function filter__add_date_filter_to_query( $es_wp_query_args, $query ) {
		if ( $query->get( 'year' ) ) {
			if ( $query->get( 'monthnum' ) ) {
				// Padding
				$date_monthnum = sprintf( '%02d', $query->get( 'monthnum' ) );

				if ( $query->get( 'day' ) ) {
					// Padding
					$date_day = sprintf( '%02d', $query->get( 'day' ) );

					$date_start = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $date_day . ' 00:00:00';
					$date_end   = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $date_day . ' 23:59:59';
				} else {
					$days_in_month = date( 't', mktime( 0, 0, 0, $query->get( 'monthnum' ), 14, $query->get( 'year' ) ) ); // 14 = middle of the month so no chance of DST issues

					$date_start = $query->get( 'year' ) . '-' . $date_monthnum . '-01 00:00:00';
					$date_end   = $query->get( 'year' ) . '-' . $date_monthnum . '-' . $days_in_month . ' 23:59:59';
				}
			} else {
				$date_start = $query->get( 'year' ) . '-01-01 00:00:00';
				$date_end   = $query->get( 'year' ) . '-12-31 23:59:59';
			}

			$es_wp_query_args['date_range'] = array(
				'field' => 'date',
				'gte'   => $date_start,
				'lte'   => $date_end,
			);
		}

		return $es_wp_query_args;
	}

	//
	// Helpers for manipulating queries
	//
	// Someday: Should we just use ES_WP_Query???
	// Converts WP-style args to ES args
	function convert_wp_es_to_es_args( $args ) {
		$defaults = array(
			'blog_id'        => get_current_blog_id(),

			'query'          => null,    // Search phrase
			'query_fields'   => array(), // hacking to be able to do a very different type of query
			'locale'         => false,

			'post_type'      => null,  // string or an array
			'terms'          => array(), // ex: array( 'taxonomy-1' => array( 'slug' ), 'taxonomy-2' => array( 'slug-a', 'slug-b' ) )

			'author'         => null,    // id or an array of ids
			'author_name'    => array(), // string or an array

			'date_range'     => null,    // array( 'field' => 'date', 'gt' => 'YYYY-MM-dd', 'lte' => 'YYYY-MM-dd' ); date formats: 'YYYY-MM-dd' or 'YYYY-MM-dd HH:MM:SS'
			'tested_range'   => null,
			'filters'        => array(),

			'orderby'        => null,    // Defaults to 'relevance' if query is set, otherwise 'date'. Pass an array for multiple orders.
			'order'          => 'DESC',

			'posts_per_page' => 10,
			'offset'         => null,
			'paged'          => null,

			/**
			 * Facets. Examples:
			 * array(
			 *     'Tag'       => array( 'type' => 'taxonomy', 'taxonomy' => 'post_tag', 'count' => 10 ) ),
			 *     'Post Type' => array( 'type' => 'post_type', 'count' => 10 ) ),
			 * );
			 */
			'facets'         => null,
		);

		$raw_args = $args; // Keep a copy

		$args = wp_parse_args( $args, $defaults );

		$es_query_args = array(
			'blog_id' => absint( $args['blog_id'] ),
			'size'    => absint( $args['posts_per_page'] ),
		);

		// TODO: limit size to 15
		// ES "from" arg (offset)
		if ( $args['offset'] ) {
			$es_query_args['from'] = absint( $args['offset'] );
		} elseif ( $args['paged'] ) {
			$es_query_args['from'] = max( 0, ( absint( $args['paged'] ) - 1 ) * $es_query_args['size'] );
		}

		if ( ! is_array( $args['author_name'] ) ) {
			$args['author_name'] = array( $args['author_name'] );
		}

		// ES stores usernames, not IDs, so transform
		if ( ! empty( $args['author'] ) ) {
			if ( ! is_array( $args['author'] ) ) {
				$args['author'] = array( $args['author'] );
			}
			foreach ( $args['author'] as $author ) {
				$user = get_user_by( 'id', $author );

				if ( $user && ! empty( $user->user_login ) ) {
					$args['author_name'][] = $user->user_login;
				}
			}
		}

		//
		// Build the filters from the query elements.
		// Filters rock because they are cached from one query to the next
		// but they are cached as individual filters, rather than all combined together.
		// May get performance boost by also caching the top level boolean filter too.
		$filters = array();

		if ( $args['post_type'] ) {
			if ( ! is_array( $args['post_type'] ) ) {
				$args['post_type'] = array( $args['post_type'] );
			}
			$filters[] = array( 'terms' => array( 'post_type' => $args['post_type'] ) );
		}

		if ( $args['author_name'] ) {
			$filters[] = array( 'terms' => array( 'author_login' => $args['author_name'] ) );
		}

		if ( ! empty( $args['date_range'] ) && isset( $args['date_range']['field'] ) ) {
			$field = $args['date_range']['field'];
			unset( $args['date_range']['field'] );
			$filters[] = array( 'range' => array( $field => $args['date_range'] ) );
		}

		if ( ! empty( $args['tested_range'] ) && isset( $args['tested_range']['field'] ) ) {
			$field = $args['tested_range']['field'];
			unset( $args['tested_range']['field'] );
			$filters[] = array( 'range' => array( $field => $args['tested_range'] ) );
		}

		if ( is_array( $args['filters'] ) ) {
			$filters = array_merge( $filters, $args['filters'] );
		}

		if ( is_array( $args['terms'] ) ) {
			foreach ( $args['terms'] as $tax => $terms ) {
				$terms = (array) $terms;
				if ( count( $terms ) && mb_strlen( $tax ) ) {
					switch ( $tax ) {
						case 'post_tag':
							$tax_fld = 'tag.slug';
							break;
						case 'category':
							$tax_fld = 'category.slug';
							break;
						default:
							$tax_fld = 'taxonomy.' . $tax . '.slug';
							break;
					}
					foreach ( $terms as $term ) {
						$filters[] = array( 'term' => array( $tax_fld => $term ) );
					}
				}
			}
		}

		$is_block_search = ! empty( $args['block_search'] );

		if ( $args['locale'] && $args['locale'] !== 'en' && substr( $args['locale'], 0, 3 ) !== 'en_' ) {
			$locale = $args['locale'];

			// Because most plugins don't have any translations we need to
			// correct for the very low scores that locale-specific fields.
			// end up getting. This is caused by the average field length being
			// very close to zero and thus the BM25 alg discounts fields that are
			// significantly longer.
			//
			// As of 2017-01-23 it looked like we were off by about 10,000x,
			// so rather than 0.1 we use a much smaller multiplier of en content
			$en_boost             = 0.00001;
			$matching_fields      = array(
				'all_content_' . $locale,
				'all_content_en^' . $en_boost,
			);
			$boost_phrase_fields  = array(
				'title_' . $locale,
				'excerpt_' . $locale,
				'description_' . $locale,
				'title_en^' . $en_boost,
				'excerpt_en^' . $en_boost,
				'description_en^' . $en_boost,
				'taxonomy.plugin_tags.name',
			);
			if ( $is_block_search ) {
				$boost_phrase_fields[] = 'block_title_' . $locale;
				$boost_phrase_fields[] = 'block_title_en^' . $en_boost;
			}
			$boost_ngram_fields   = array(
				'title_' . $locale . '.ngram',
				'title_en.ngram^' . $en_boost,
			);
			$boost_title_fields   = array(
				'title_' . $locale,
				'title_en^' . $en_boost,
				'slug_text',
			);
			if ( $is_block_search ) {
				$boost_title_fields[] = 'block_title_' . $locale;
				$boost_title_fields[] = 'block_title_en^' . $en_boost;
				$boost_title_fields[] = 'block_name';
			}
			$boost_content_fields = array(
				'excerpt_' . $locale,
				'description_' . $locale,
				'excerpt_en^' . $en_boost,
				'description_en^' . $en_boost,
				'taxonomy.plugin_tags.name',
			);
		} else {
			$matching_fields      = array(
				'all_content_en',
			);
			$boost_phrase_fields  = array(
				'title_en',
				'excerpt_en',
				'description_en',
				'taxonomy.plugin_tags.name',
			);
			if ( $is_block_search ) {
				$boost_phrase_fields[] = 'block_title_en';
			}
			$boost_ngram_fields   = array(
				'title_en.ngram',
			);
			$boost_title_fields   = array(
				'title_en',
				'slug_text',
			);
			if ( $is_block_search ) {
				$boost_title_fields[] = 'block_title_en';
				$boost_title_fields[] = 'block_name';
			}
			$boost_content_fields = array(
				'excerpt_en',
				'description_en',
				'taxonomy.plugin_tags.name',
			);
		}

		//
		// Build the query - potentially extracting more filters
		// TODO: add auto phrase searching
		// TODO: add fuzzy searching to correct for spelling mistakes
		// TODO: boost title, tag, and category matches
		if ( $args['query'] ) {
			$analyzer = Jetpack_Search::get_analyzer_name( $this->blog_lang );
			$query    = array(
				'bool' => array(
					'must'   => array(
						'multi_match' => array(
							'query'    => $args['query'],
							'fields'   => $matching_fields,
							'boost'    => 0.1,
							'operator' => 'and',
						),
					),
					'should' => array(
						array(
							'multi_match' => array(
								'query'  => $args['query'],
								'fields' => $boost_phrase_fields,
								'type'   => 'phrase',
								'boost'  => 2,
							),
						),
						array(
							'multi_match' => array(
								'query'  => $args['query'],
								'fields' => $boost_ngram_fields,
								'type'   => 'phrase',
								'boost'  => 0.2,
							),
						),
						array(
							'multi_match' => array(
								'query'  => $args['query'],
								'fields' => $boost_title_fields,
								'type'   => 'best_fields',
								'boost'  => 2,
							),
						),
						array(
							'multi_match' => array(
								'query'  => $args['query'],
								'fields' => $boost_content_fields,
								'type'   => 'best_fields',
								'boost'  => 2,
							),
						),
						array(
							'multi_match' => array(
								'query'  => $args['query'],
								'fields' => array(
									'author',
									'contributors',
								),
								'type'   => 'best_fields',
								'boost'  => 2,
							),
						),
					),
				),
			);

			$es_query_args['query'] = Jetpack_Search::score_query_by_recency( $query );

			if ( ! $args['orderby'] ) {
				$args['orderby'] = array( 'relevance' );
			}
		} else {
			$query                  = array( 'match_all' => array() );
			$es_query_args['query'] = Jetpack_Search::score_query_by_recency( $query );
			if ( ! $args['orderby'] ) {
				$args['orderby'] = array( 'date' );
			}
		}

		// Validate the "order" field
		switch ( strtolower( $args['order'] ) ) {
			case 'asc':
				$args['order'] = 'asc';
				break;
			case 'desc':
			default:
				$args['order'] = 'desc';
				break;
		}

		$es_query_args['sort'] = array();
		foreach ( (array) $args['orderby'] as $orderby ) {
			// Translate orderby from WP field to ES field
			// todo: add support for sorting by title, num likes, num comments, num views, etc
			switch ( $orderby ) {
				case 'relevance':
					// never order by score ascending
					$es_query_args['sort'][] = array( '_score' => array( 'order' => 'desc' ) );
					break;
				case 'date':
					$es_query_args['sort'][] = array( 'date' => array( 'order' => $args['order'] ) );
					break;
				case 'ID':
					$es_query_args['sort'][] = array( 'id' => array( 'order' => $args['order'] ) );
					break;
				case 'author':
					$es_query_args['sort'][] = array( 'author.raw' => array( 'order' => $args['order'] ) );
					break;
			}
		}
		if ( empty( $es_query_args['sort'] ) ) {
			unset( $es_query_args['sort'] );
		}

		if ( ! empty( $filters ) ) {
			$es_query_args['filter'] = array( 'and' => $filters );
		} else {
			$es_query_args['filter'] = array( 'match_all' => new stdClass() );
		}

		return $es_query_args;
	}

	public static function get_analyzer_name( $lang_code ) {
		$analyzer = 'default';
		if ( in_array( $lang_code, Jetpack_Search::$analyzed_langs ) ) {
			$analyzer = $lang_code . '_analyzer';
		} else {
			$split_lang = explode( '-', $lang_code );
			if ( in_array( $split_lang[0], Jetpack_Search::$analyzed_langs ) ) {
				$analyzer = $split_lang[0] . '_analyzer';
			}
		}
		return $analyzer;
	}

	//
	// ES Filter Manipulation
	/*
	 * And an existing filter object with a list of additional filters.
	 *   Attempts to optimize the filters somewhat.
	 */
	public static function and_es_filters( $curr_filter, $filters ) {
		if ( ! is_array( $curr_filter ) || isset( $curr_filter['match_all'] ) ) {
			if ( 1 == count( $filters ) ) {
				return $filters[0];
			}

			return array( 'and' => $filters );
		}

		return array( 'and' => array_merge( array( $curr_filter ), $filters ) );
	}

	//
	// ES Query Manipulation
	public static function score_query_by_recency( $query ) {
		// Newer content gets weighted slightly higher
		$date_scale  = '360d';
		$date_offset = '180d';
		$date_decay  = 0.5;
		$date_origin = date( 'Y-m-d' );

		return array(
			'filtered' => array(
				'query' => array(
					'function_score' => array(
						'query'      => $query,
						'functions'  => array(
							array(
								'exp' => array(
									'plugin_modified' => array(
										'origin' => $date_origin,
										'offset' => $date_offset,
										'scale'  => $date_scale,
										'decay'  => $date_decay,
									),
								),
							),
							array(
								'exp' => array(
									'tested' => array(
										'origin' => sprintf( '%0.1f', WP_CORE_STABLE_BRANCH ),
										'offset' => 0.1,
										'scale'  => 0.4,
										'decay'  => 0.6,
									),
								),
							),
							array(
								'field_value_factor' => array(
									'field'    => 'active_installs',
									'factor'   => 0.375,
									'modifier' => 'log2p',
									'missing'  => 1,
								),
							),
							array(
								// there aren't that many plugins with more than 1 million (6 total)
								// we don't need to differentiate them as much
								'filter' => array(
									'range' => array(
										'active_installs' => array(
											'lte' => 1000000,
										),
									),
								),
								'exp'    => array(
									'active_installs' => array(
										'origin' => 1000000,
										'offset' => 0,
										'scale'  => 900000,
										'decay'  => 0.75,
									),
								),
							),
							array(
								'field_value_factor' => array(
									'field'    => 'support_threads_resolved',
									'factor'   => 0.25,
									'modifier' => 'log2p',
									'missing'  => 0.5,
								),
							),
							array(
								'field_value_factor' => array(
									'field'    => 'rating',
									'factor'   => 0.25,
									'modifier' => 'sqrt',
									'missing'  => 2.5,
								),
							),
						),
						'boost_mode' => 'multiply',
					),
				),
			),
		);
	}

}

<?php
/**
 * Favorites functionality.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Favorites {

	/**
	 * The relative path rewrite endpoint for listing a user's favorite photos.
	 */
	const PATH = 'favorites';

	/**
	 * The name of the taxonomy used for user favorites.
	 */
	const FAVORITES_TAXONOMY = 'user_favorites';

	/**
	 * The query variable denoting a request for an archive of user's favorited photos.
	 */
	const QUERY_VAR_USER_FAVORITES = 'favorites-user';

	/**
	 * The query variable denoting a request for an archive of the currnet user's favorited photos.
	 */
	const QUERY_VAR_MY_FAVORITES = 'favorites-current-user';

	/**
	 * The query variable denoting the slug for a user.
	 */
	const QUERY_VAR_USER_SLUG = 'user_slug';

	/**
	 * The query variable denoting a request for the feed for a user's favorited photos.
	 */
	const USER_FAVORITES_FEED = 'favorites-user-feed';

	/**
	 * The column slug for the favorites admin post listing column.
	 */
	const COL_NAME_FAVORITES = 'wporg-photo-favorites';

	/**
	 * The prefix for a unique user term slug.
	 */
	const USER_TERM_SLUG_PREFIX = 'user_';

	/**
	 * Memoized user whose favorites listing is being shown.
	 *
	 * @var WP_User
	 */
	protected static $favorites_user = null;

	/**
	 * Initializes component.
	 */
	public static function init() {
		$post_type = Registrations::get_post_type();

		// Register taxonomy.
		add_action( 'init',                                    [ __CLASS__, 'register_taxonomy' ] );

		// Register feed.
		add_action( 'init',                                    [ __CLASS__, 'register_user_favorites_feed' ] );
		add_action( 'init',                                    [ __CLASS__, 'add_user_favorites_feed_rewrite_rule' ] );
		add_action( 'pre_get_posts',                           [ __CLASS__, 'customize_favorites_feed_query' ] );

		// Add user's favorites archive.
		add_action( 'init',                                    [ __CLASS__, 'add_rewrite_rule' ] );
		add_filter( 'query_vars',                              [ __CLASS__, 'add_query_var' ] );
		add_action( 'pre_get_posts',                           [ __CLASS__, 'pre_get_posts' ] );
		add_filter( 'template_include',                        [ __CLASS__, 'load_user_favorites_template' ] );
		add_action( 'template_redirect',                       [ __CLASS__, 'template_redirect' ] );
		add_filter( 'document_title_parts',                    [ __CLASS__, 'filter_page_title' ] );

		// Add post listing column.
		add_filter( "manage_{$post_type}_posts_columns",       [ __CLASS__, 'add_favorites_column' ] );
		add_action( "manage_{$post_type}_posts_custom_column", [ __CLASS__, 'handle_photo_column_data' ], 10, 2 );

		// Load the API routes.
		add_action( 'rest_api_init',                           [ __CLASS__, 'register_photo_favorite_route' ] );
		add_action( 'rest_api_init',                           [ __CLASS__, 'register_user_favorite_photos_route' ] );

		// Handle photo deletion.
		add_action( 'before_delete_post',                      [ __CLASS__, 'remove_from_user_favorites' ], 10, 2 );

		// Handle photo rejection (a published photo could later get rejected).
		add_action( 'wporg_photos_reject_post',                [ __CLASS__, 'handle_photo_rejection' ] );
	}

	/**
	 * Registers taxonomy.
	 */
	public static function register_taxonomy() {
		register_taxonomy( self::FAVORITES_TAXONOMY, Registrations::get_post_type(), [
			'hierarchical' => false,
			'public'       => false,
			'rewrite'      => false,
		] );
	}

	/**
	 * Registers the feed for user favorites.
	 */
	public static function register_user_favorites_feed() {
		add_feed( self::USER_FAVORITES_FEED, function() {
			load_template( ABSPATH . WPINC . '/feed-rss2.php' );
		});
	}

	/**
	 * Adds the rewrite rule for the user favorites feed.
	 */
	public static function add_user_favorites_feed_rewrite_rule() {
		add_rewrite_rule( '^' . self::PATH . '/([^/]+)/feed/?$', 'index.php?feed=' . self::USER_FAVORITES_FEED . '&' . self::QUERY_VAR_USER_SLUG . '=$matches[1]', 'top');
	}

	/**
	 * Customizes the query for user favorites feeds.
	 */
	public static function customize_favorites_feed_query( $query ) {
		if ( $query->is_feed( self::USER_FAVORITES_FEED ) ) {
			$user_slug = get_query_var( self::QUERY_VAR_USER_SLUG );
			$user = get_user_by( 'slug', $user_slug );

			if ( $user ) {
				$user_favorites_query_vars = self::get_user_favorited_photos_query( $user->ID, [
					'posts_per_page' => 20,
				] );

				foreach ( $user_favorites_query_vars as $key => $val ) {
					$query->set( $key, $val );
				}
			} else {
				// Short-circuit query since user was not found.
				$query->set( 'post__in', [ 0 ] );
			}

			remove_action( 'pre_get_posts', [ __CLASS__, 'customize_favorites_feed_query' ] );
		}
	}

	/**
	 * Adds the rewrite rule for the 'favorites' URL endpoint.
	 */
	public static function add_rewrite_rule() {
		add_rewrite_rule( '^' . self::PATH . '/([^/]*)/?', 'index.php?' . self::QUERY_VAR_USER_FAVORITES . '=$matches[1]', 'top' );
		add_rewrite_rule( '^' . self::PATH . '/?$', 'index.php?' . self::QUERY_VAR_MY_FAVORITES . '=1', 'top' );
	}

	/**
	 * Adds the query variable for denoting a request for a particular user's favorite photos.
	 *
	 * @param string[] $query_vars Array of query variables.
	 * @return string[]
	 */
	public static function add_query_var( $query_vars ) {
		$query_vars[] = self::QUERY_VAR_MY_FAVORITES;
		$query_vars[] = self::QUERY_VAR_USER_FAVORITES;
		$query_vars[] = self::QUERY_VAR_USER_SLUG;
		return $query_vars;
	}

	/**
	 * Returns the default query for retrieving a user's favorited photos.
	 *
	 * @param int      $user_id User ID.
	 * @param string[] $args    Optional. Query parameters to add/supercede the default
	 *                          post query parameters. Default empty array.
	 * @return string[] Associative array of query keys and their values.
	 */
	protected static function get_user_favorited_photos_query( $user_id, $args = [] ) {
		$term_slug = self::get_user_term_slug( $user_id );

		return wp_parse_args( $args, [
			'post_status' => 'publish',
			'post_type'   => Registrations::get_post_type(),
			'tax_query'   => [
				[
					'field'    => 'slug',
					'taxonomy' => self::FAVORITES_TAXONOMY,
					'terms'    => $term_slug,
				],
			],
		] );
	}

	/**
	 * Modifies the main query to retrieve user's favorite photos, when appropriate.
	 *
	 * @param WP_Query $query The query object.
	 */
	public static function pre_get_posts( $query ) {
		if ( ! $query->is_main_query() ) {
			return;
		}

		$favorites_username = get_query_var( self::QUERY_VAR_USER_FAVORITES );
		if ( ! $favorites_username ) {
			return;
		}

		$user = get_user_by( 'slug', $favorites_username );
		if ( ! $user ) {
			return;
		}
		self::$favorites_user = $user;

		// Query for the user's favorited photos.
		$query_args = self::get_user_favorited_photos_query( $user->ID );
		foreach ( $query_args as $key => $val ) {
			$query->set( $key, $val );
		}

		// Ensure query reflects proper conditions.
		$query->is_archive = true;
		$query->is_home    = false;
		$query->is_page    = false;
		$query->is_404     = false;
	}

	/**
	 * Loads the user-favorites.php template when appropriate, if possible.
	 *
	 * @param string $template The path of the template to include.
	 * @return string
	 */
	public static function load_user_favorites_template( $template ) {
		global $wp_query;

		$favorites_user = get_query_var( self::QUERY_VAR_USER_FAVORITES );
		if ( $favorites_user ) {
			$new_template = locate_template( [ 'user-favorites.php' ] );
			if ( $new_template ) {
				// Even if no favorites were found, don't treat the request as a 404.
				$wp_query->is_archive = true;
				$wp_query->is_404 = false;

				// Set global authordata variable to allow use of core user template functions.
				$GLOBALS['authordata'] = get_user_by( 'slug', $favorites_user );

				return $new_template;
			}
		}

		return $template;
	}

	/**
	 * Handles a request for a given user's favorite photos.
	 *
	 * Redirects to front page if no user is explicitly supplied.
	 */
	public static function template_redirect() {
		$favorites_user         = get_query_var( self::QUERY_VAR_USER_FAVORITES );
		$favorites_current_user = get_query_var( self::QUERY_VAR_MY_FAVORITES );

		if ( $favorites_current_user ) {
			if ( ! is_user_logged_in() ) {
				wp_redirect( home_url() );
				exit;
			}

			$current_user = wp_get_current_user();
			wp_redirect( home_url( '/' . self::PATH . '/' . $current_user->user_nicename ) );
			exit;
		}
	}

	/**
	 * Registers the REST API route to favorite a photo.
	 *
	 * Adapted from plugin-directory/api/routes/class-plugin-favorites.php: __construct()
	 */
	public static function register_photo_favorite_route() {
		register_rest_route( 'photos/v1', '/photo/(?P<photo_slug>[^/]+)/favorite', [
			'methods'             => [ \WP_REST_Server::READABLE, \WP_REST_Server::CREATABLE ],
			'callback'            => [ __CLASS__, 'handle_favorite_request' ],
			'args'                => [
				'photo_slug' => [
					'validate_callback' => [ __CLASS__, 'validate_photo_slug_callback' ],
				],
				'favorite'    => [
					'validate_callback' => function( $bool ) {
						return is_numeric( $bool );
					},
				],
				'unfavorite'  => [
					'validate_callback' => function( $bool ) {
						return is_numeric( $bool );
					},
				],
			],
			'permission_callback' => 'is_user_logged_in',
		] );

		add_filter( 'rest_pre_echo_response', [ __CLASS__, 'override_cookie_expired_message' ], 10, 3 );
	}

	/**
	 * Registers the REST API route to retrieve a user's favorite photos.
	 *
	 * Adapted from plugin-directory/api/routes/class-plugin-favorites.php: __construct()
	 */
	public static function register_user_favorite_photos_route() {
		register_rest_route( 'photos/v1', '/user/(?P<user_slug>[a-zA-Z0-9_-]+)/favorites', [
			'methods'  => [ \WP_REST_Server::READABLE ],
			'callback' => [ __CLASS__, 'rest_get_user_favorites' ],
			'args'     => [
				'user_slug' => [
					'required'          => true,
					'validate_callback' => function( $value ) {
						return is_string( $value ) && $value && get_user_by( 'slug', $value );
					}
				],
				'posts_per_page' => [
					'required'          => false,
					'default'           => 12,
					'validate_callback' => function( $value ) {
						return is_numeric( $value );
					}
				],
				'page' => [
					'required'          => false,
					'default'           => 1,
					'validate_callback' => function( $value ) {
						return is_numeric( $value );
					}
				],
			],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Handles REST endpoint for retrieving a user's favorite photos.
	 *
	 * Adapted from plugin-directory/api/routes/class-plugin-favorites.php: favorite()
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public static function rest_get_user_favorites( $request ) {
		$user = get_user_by( 'slug', $request['user_slug'] );

		if ( ! $user ) {
			return new \WP_Error( 'rest_user_not_found', 'User not found', [ 'status' => 404 ] );
		}

		$favorites_count = self::count_user_favorites( $user->ID );

		$response_data = [
			'favorite_photos' => [],
			'total_favorites' => $favorites_count,
			'total_pages'     => 0,
		];

		if ( $favorites_count ) {
			$favorites_query = new \WP_Query( self::get_user_favorited_photos_query( $user->ID, [
				'paged'          => $request['page'],
				'posts_per_page' => $request['posts_per_page'],
			] ) );

			$response_data = [
				'favorite_photos' => array_map( function( $post ) {
					return [
						'ID'        => $post->ID,
						'title'     => get_the_title( $post ),
						'permalink' => get_permalink( $post ),
					];
				}, $favorites_query->posts ),
				'total_favorites' => $favorites_count,
				'total_pages'     => $favorites_query->max_num_pages,
			];
		}

		return rest_ensure_response( $response_data );
	}

	/**
	 * A validation callback for REST API Requests to ensure a valid photo slug is presented.
	 *
	 * @param string $value The photo slug to be checked for.
	 * @return bool Whether the photo slug exists.
	 */
	public static function validate_photo_slug_callback( $value ) {
		return is_string( $value ) && $value && Posts::get_photo_post( $value );
	}

	/**
	 * Redirects back to the photos page when the photo favoriting endpoint is accessed with an invalid nonce.
	 *
	 * Adapted from plugin-directory/api/routes/class-plugin-favorites.php: override_cookie_expired_message()
	 */
	public static function override_cookie_expired_message( $result, $obj, $request ) {
		if (
			is_array( $result ) && isset( $result['code'] ) &&
			'rest_cookie_invalid_nonce' == $result['code'] &&
			preg_match( '!^/photos/v1/photo/([^/]+)/favorite$!', $request->get_route(), $m )
		) {
			$location = get_permalink( Posts::get_photo_post( $m[1] ) ) ?: home_url( '/' );
			header( "Location: $location" );
			// Still allow the REST API response to be rendered, browsers will follow the location header though.
		}

		return $result;
	}

	/**
	 * Endpoint to favorite a photo.
	 *
	 * Adapted from plugin-directory/api/routes/class-plugin-favorites.php: favorite()
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return bool True if the favoriting was successful.
	 */
	public static function handle_favorite_request( $request ) {
		$photo = Posts::get_photo_post( $request['photo_slug'] );

		$result = [
			'location' => wp_get_referer() ?: get_permalink( $photo ),
		];
		header( 'Location: ' . $result['location'] );
		if ( ! $photo || ( ! isset( $request['favorite'] ) && ! isset( $request['unfavorite'] ) ) ) {
			$result['error'] = 'Unknown Action';
		} else {
			$result['favorite'] = ( isset( $request['favorite'] ) && '1' === $request['favorite'] )
				? self::favorite_photo( $photo->ID, get_current_user_id() )
				: self::unfavorite_photo( $photo->ID, get_current_user_id() );
		}

		return (object) $result;
	}

	/**
	 * Returns a unique term slug for the user.
	 *
	 * @param int $user_id The user ID.
	 * @return string
	 */
	protected static function get_user_term_slug( $user_id ) {
		return self::USER_TERM_SLUG_PREFIX . $user_id;
	}

	/**
	 * Returns the count of favorites for a photo.
	 *
	 * @param int $post_id Post ID. Default is current post.
	 * @return int
	 */
	public static function count_photo_favorites( $post_id = null ) {
		if ( ! $post_id ) {
			global $post;
			$post_id = $post->ID ?? null;
		}

		if ( ! $post_id ) {
			return 0;
		}

		$term = get_the_terms( $post_id, self::FAVORITES_TAXONOMY );

		if ( is_wp_error( $term ) || empty( $term ) ) {
			return 0;
		}

		return (int) $term[0]->count;
	}

	/**
	 * Returns an array of user IDs for all users who favorited the given post.
	 *
	 * @param int $post_id Post ID. Default is current post.
	 * @return int[] Array of user IDs.
	 */
	public static function get_favoriting_users( $post_id = null ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! $post_id ) {
			return [];
		}

		$terms = wp_get_object_terms( $post_id, self::FAVORITES_TAXONOMY, [ 'fields' => 'names' ] );

		return array_map( 'intval', $terms );
	}

	/**
	 * Returns the count of favorites by a user.
	 *
	 * @param int $user_id Optional. User ID or object. Default is current user.
	 * @return int
	 */
	public static function count_user_favorites( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return 0;
		}

		$term_slug = self::get_user_term_slug( $user_id );
		$term = get_term_by( 'slug', $term_slug, self::FAVORITES_TAXONOMY );

		if ( ! $term ) {
			return 0;
		}

		return (int) $term->count;
	}

	/**
	 * Returns an array of post IDs for all photos favorited by the given user.
	 *
	 * @param int $user_id Optional. User ID. Default is current user.
	 * @return int[] Array of post IDs.
	 */
	public static function get_favorited_photos( $user_id = null ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return [];
		}

		return get_posts( self::get_user_favorited_photos_query( $user_id, [
			'fields'         => 'ids',
			'posts_per_page' => -1,
		] ) );
	}

	/**
	 * Determines if a photo is favorited by a user.
	 *
	 * @param int $post_id Optional. Post ID. Default is current post.
	 * @param int $user_id Optional. User ID. Default is current user.
	 * @return bool True if the photo was favorited by the user, else false.
	 */
	public static function is_favorited_photo( $post_id = 0, $user_id = 0 ) {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( $post_id instanceof WP_Post ) {
			$post_id = $post_id->ID;
		}

		if ( ! $post_id ) {
			return false;
		}

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		if ( ! $user_id ) {
			return false;
		}

		$term_slug = self::get_user_term_slug( $user_id );
		$terms = wp_get_post_terms( $post_id, self::FAVORITES_TAXONOMY, [ 'fields' => 'slugs' ] );

		return in_array( $term_slug, $terms );
	}

	/**
	 * Records a photo as a favorite of the user.
	 *
	 * @param int $post_id Optional. Post ID. Default is current post.
	 * @param int $user_id Optional. User ID. Default is current user.
	 * @return bool True if the photo was successfully favorited by the user, else false.
	 */
	public static function favorite_photo( $post_id, $user_id ) {
		if ( ! $post_id || ! $user_id ) {
			return false;
		}

		$term_slug = self::get_user_term_slug( $user_id );

		// Check if the term exists; if not, create it.
		if ( ! term_exists( $term_slug, self::FAVORITES_TAXONOMY ) ) {
			wp_insert_term( $user_id, self::FAVORITES_TAXONOMY, [ 'slug' => $term_slug ] );
		}

		// Associate the photo with the user's favorites term.
		wp_set_post_terms( $post_id, [ $term_slug ], self::FAVORITES_TAXONOMY, true );

		return true;
	}

	/**
	 * Removes a photo as a favorite of the user.
	 *
	 * @param int $post_id Optional. Post ID. Default is current post.
	 * @param int $user_id Optional. User ID. Default is current user.
	 * @return bool True if the photo was successfully unfavorited by the user, else false.
	 */
	public static function unfavorite_photo( $post_id, $user_id ) {
		if ( ! $post_id || ! $user_id ) {
			return false;
		}

		$term_slug = self::get_user_term_slug( $user_id );

		// Remove the photo from the user's favorites term.
		wp_remove_object_terms( $post_id, $term_slug, self::FAVORITES_TAXONOMY );

		return true;
	}

	/**
	 * Adds a column to show the count of users who have favorited a photo.
	 *
	 * @param array $posts_columns Array of post column titles.
	 * @return array The $posts_columns array with the photo column added.
	 */
	public static function add_favorites_column( $posts_columns ) {
		if (
			Admin::should_include_photo_column()
		&&
			(
				empty( $_GET['post_status'] )
			||
				! in_array( $_GET['post_status'], Photo::get_pending_post_statuses() )
			)
		 ) {
			$posts_columns[ self::COL_NAME_FAVORITES ] = __( '❤️', 'wporg-photos' );
		}

		return $posts_columns;
	}

	/**
	 * Outputs the content for the favorites column for the post.
	 *
	 * @param string $column_name The name of the column.
	 * @param int    $post_id     The id of the post being displayed.
	 */
	public static function handle_photo_column_data( $column_name, $post_id ) {
		if ( self::COL_NAME_FAVORITES !== $column_name ) {
			return;
		}

		echo '<div class="wporg-photo-favorite-count">' . number_format_i18n( self::count_photo_favorites( $post_id ) ) . '</div>';
	}

	/**
	 * Returns the URL to toggle a photo's favorites state for a user.
	 *
	 * @param int|\WP_Post $post Optional. Post ID or post object. Defaults to global $post.
	 * @param int|\WP_User $user Optional. User ID or user object to alter the favorite status for. Defaults to the current user.
	 * @return string URL to toggle status. Empty if the user or post doesn't exist.
	 */
	public static function get_favorite_link( $post = 0, $user = 0 ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return '';
		}

		if ( ! $user ) {
			$user = wp_get_current_user();
		} elseif ( is_int( $user ) ) {
			$user = get_user_by( 'id', $user );
		}

		if ( ! $user ) {
			return '';
		}

		$favorited = self::is_favorited_photo( $post->ID, $user->ID );

		return add_query_arg( [
			'_wpnonce' => wp_create_nonce( 'wp_rest' ),
			( $favorited ? 'unfavorite' : 'favorite' ) => '1',
		], home_url( 'wp-json/photos/v1/photo/' . $post->post_name . '/favorite' ) );
	}

	/**
	 * Sets the document title for user favorites listing.
	 *
	 * @param string[] $title_parts The document title parts.
	 * @return string[]
	 */
	public static function filter_page_title( $title_parts) {
		if ( self::$favorites_user ) {
			$display_name = self::$favorites_user->display_name;
			$username = self::$favorites_user->user_nicename;

			if ( $display_name !== $username ) {
				/* translators: 1: User display name, 2: Username. */
				$title_parts['title'] = sprintf( __( 'Photo favorites for %1$s (@%2$s)', 'wporg-photos' ), $display_name, $username	);
			} else {
				/* translators: %s: User display name. */
				$title_parts['title'] = sprintf( __( 'Photo favorites for %s', 'wporg-photos' ), $display_name, $username );
			}

			$paged = get_query_var( 'paged' ) ? absint( get_query_var( 'paged' ) ) : false;
			$max_page = $GLOBALS['wp_query']->max_num_pages;

			if ( $paged ) {
				/* translators: 1: current page number, 2: total number of pages. */
				$title_parts['page'] = sprintf( __( 'Page %1$s of %2$s', 'wporg-plugins' ), $pages, $max_page );
			}
		}

		return $title_parts;
	}

	/**
	 * Outputs markup for a button and associated JS to favorite or unfavorite a photo.
	 *
	 * @param int|\WP_Post|null $post Optional. Photo post ID or post object. Defaults to global $post.
	 */
	public static function get_photo_favorites_markup( $post = null ) {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$post = get_post( $post );
		if ( ! $post ) {
			return;
		}

		$is_favorited = self::is_favorited_photo( $post->ID );

		printf(
			'<div class="photo-favorite"><a href="%s" class="photo-favorite-heart%s"><span class="screen-reader-text">%s</span></a></div>' . "\n",
			esc_url( self::get_favorite_link( $post ) ),
			$is_favorited ? ' favorited' : '',
			$is_favorited ? esc_html__( 'Unfavorite this photo', 'wporg-photos' ) : esc_html__( 'Favorite this photo', 'wporg-photos' )
		);
	}

	/**
	 * Removes a photo from any favoriting user's list of favorite photos.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function remove_from_user_favorites( $post_id, $post ) {
		// Ensure the post is appropriate post type.
		if ( get_post_type( $post ) !== Registrations::get_post_type() ) {
			return;
		}

		// Delete all of photo's favorites in one go.
		// Note: Will not trigger any custom handling present in `unfavorite_photo()`.
		wp_delete_object_term_relationships( $post_id, self::FAVORITES_TAXONOMY );
	}

	/**
	 * Handles the case when a photo gets rejected.
	 *
	 * It's possible a photo gets published, gets favorited, then later gets rejected.
	 *
	 * @param WP_Post $post Post object.
	 */
	public static function handle_photo_rejection( $post ) {
		self::remove_from_user_favorites( $post->ID, $post );
	}
}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Favorites', 'init' ] );

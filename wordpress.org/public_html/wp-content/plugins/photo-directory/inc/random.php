<?php
/**
 * Random photo(s) handling.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Random {

	/**
	 * The relative path rewrite endpoint for loading a random photo.
	 */
	const PATH = 'random';

	/**
	 * The query variable denoting a request for a random photo.
	 */
	const QUERY_VAR_LOAD_RANDOM = 'load-random-photo';

	/**
	 * The cookie denoting that the current photo was randomly chosen.
	 */
	const COOKIE_WAS_RANDOM = 'random-photo';

	/**
	 * Flag to indicate if the current request was a result of a request for a random photo.
	 *
	 * @access private
	 * @var bool
	 */
	private static $was_photo_random = false;

	/**
	 * Initializer.
	 */
	public static function init() {
		// Add 'random' URL endpoint to front-end.
		add_action( 'init',              [ __CLASS__, 'add_rewrite_rule' ] );
		add_filter( 'query_vars',        [ __CLASS__, 'add_query_var' ] );
		add_action( 'template_redirect', [ __CLASS__, 'detect_random_photo_cookie' ] );
		add_action( 'template_redirect', [ __CLASS__, 'redirect_if_random' ] );
		add_filter( 'wp_robots',         [ __CLASS__, 'noindex' ] );

		// Accommodate randomization of queue.
		add_filter( 'admin_body_class',  [ __CLASS__, 'random_in_admin_body_class' ] );
	}

	/**
	 * Adds the rewrite rule for the 'random' URL endpoint.
	 */
	public static function add_rewrite_rule() {
		add_rewrite_rule( '^' . self::PATH . '/?$', 'index.php?' . self::QUERY_VAR_LOAD_RANDOM . '=1', 'top' );
	}

	/**
	 * Adds the query variable for denoting a request for a random photo.
	 *
	 * @param string[] $query_vars Array of query variables.
	 * @return string[]
	 */
	public static function add_query_var( $query_vars ) {
		$query_vars[] = self::QUERY_VAR_LOAD_RANDOM;
		return $query_vars;
	}

	/**
	 * Determines if the current query is a request for a random photo.
	 *
	 * @return bool True if the current query is a request for a random photo, else false.
	 */
	public static function is_random_photo_query() {
		return is_front_page() && is_main_query() && '1' === get_query_var( self::QUERY_VAR_LOAD_RANDOM );
	}

	/**
	 * Determines if the current photo was a randomly chosen photo.
	 *
	 * @param bool $and_clear Should the cookie used to record that a photo was randomly chosen be cleared? Default true.
	 * @return bool True if the current photo was a random photo, else false.
	 */
	public static function was_photo_random( $and_clear = true ) {
		return is_singular( Registrations::get_post_type() ) && self::$was_photo_random;
	}

	/**
	 * Handles the redirect when the random photo URL endpoint is visited.
	 */
	public static function redirect_if_random() {
		if ( self::is_random_photo_query() ) {
			$args = [
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'rand',
				'post_status'    => 'publish',
				'post_type'      => Registrations::get_post_type(),
				'posts_per_page' => 1,
			];

			$photo_query = new \WP_Query( $args );

			if ( $photo_query->have_posts() ) {
				// Set a simple cookie to denote the fact that the photo was loaded randomly.
				setcookie( self::COOKIE_WAS_RANDOM, '1', time() + MINUTE_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN );

				wp_safe_redirect( get_permalink( $photo_query->posts[0] ), 302 );
				exit;
			}
		}
	}

	/**
	 * Detects the presence of the cookie associated with denoting that a random photo
	 * was requested.
	 *
	 * If detected, it is noted and the cookie gets deleted.
	 *
	 * Use `self::was_photo_random()` to check if the loaded photo was randomly chosen.
	 */
	public static function detect_random_photo_cookie() {
		if ( isset( $_COOKIE[ self::COOKIE_WAS_RANDOM ] ) ) {
			setcookie( self::COOKIE_WAS_RANDOM, '', 1, SITECOOKIEPATH, COOKIE_DOMAIN );
			self::$was_photo_random = true;
		}
	}

	/**
	 * Prevents indexing of the random photo URL endpoint.
	 *
	 * @param array[] $robots Associative array of directives.
	 * @return array[]
	 */
	public static function noindex( $robots ) {
		if ( self::is_random_photo_query() ) {
			$robots = wp_robots_no_robots( $robots );
		}

		return $robots;
	}

	/**
	 * Determines if the current query is for an admin listing of photos
	 * that should be randomly ordered.
	 *
	 * @return bool True if the query is for an admin listing of photos that should be
	 *              randomly ordered, else false.
	 */
	public static function is_random_admin_listing() {
		return (
			is_admin()
		&&
			is_main_query()
		&&
			Registrations::get_post_type() === ( $_GET['post_type'] ?? false )
		&&
			'pending' === ( $_GET['post_status'] ?? '' )
		);
	}

	/**
	 * Adds class denoting a random listing of photos when appropriate.
	 *
	 * @param string $classes Space-separated list of CSS classes.
	 * @return string[]
	 */
	public static function random_in_admin_body_class( $classes ) {
		if ( self::is_random_admin_listing() ) {
			$classes .= ' photos-random';
		}

		return trim( $classes );
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Random', 'init' ] );

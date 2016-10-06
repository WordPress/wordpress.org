<?php

/**
 * A helper plugin for our integration with Let's Encrypt.
 */
class WordCamp_Lets_Encrypt_Helper {
	/**
	 * Initialize
	 */
	public static function load() {
		if ( ! is_main_site() ) {
			return;
		}

		add_filter( 'rest_api_init', array( __CLASS__, 'rest_api_init' ) );
	}

	/**
	 * Register REST API endpoints
	 */
	public static function rest_api_init() {
		register_rest_route(
			'wordcamp-letsencrypt/v1',
			'/domains',
			array(
				'methods'  => 'GET',
				'callback' => array( __CLASS__, 'rest_callback_domains' ),
			)
		);
	}

	/**
	 * REST: /domains
	 *
	 * Return an array of all domains that need SSL certs.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return array|WP_Error
	 */
	public static function rest_callback_domains( $request ) {
		global $wpdb;

		if ( WORDCAMP_LE_HELPER_API_KEY !== $request->get_param( 'api_key' ) ) {
			return new WP_Error( 'error', 'Invalid or empty key.', array( 'status' => 403 ) );
		}

		$domains = array();
		$blogs   = $wpdb->get_results( "
			SELECT `domain`, `path`
			FROM `$wpdb->blogs`
			WHERE
				`public`  = 1 AND
				`deleted` = 0
			ORDER BY `blog_id` ASC",
			ARRAY_A
		);

		foreach ( $blogs as $blog ) {
			if ( preg_match( '#^[0-9]{4}(?:-[^\.])?\.([^\.]+)\.wordcamp\.org$#i', $blog['domain'], $matches ) ) {
				$domains[] = sprintf( '%s.wordcamp.org', $matches[1] );
			}

			$domains[] = $blog['domain'];

			// While transitioning from city.wordcamp.org/year-extra
			if ( preg_match( '#^([^\.]+)\.wordcamp.org/([0-9]{4}(?:-[^\.])?)/?$#i', $blog['domain'] . $blog['path'], $matches ) ) {
				$domains[] = sprintf( '%s.%s.wordcamp.org', $matches[2], $matches[1] );
			}
		}

		// Back-compat domains.
		$domains[] = '2006.wordcamp.org';
		$domains[] = '2007.wordcamp.org';
		$domains[] = 'bg.wordcamp.org';
		$domains[] = 'fr.2011.montreal.wordcamp.org';
		$domains[] = 'fr.2012.montreal.wordcamp.org';
		$domains[] = 'fr.2013.montreal.wordcamp.org';
		$domains[] = '2014.fr.montreal.wordcamp.org';
		$domains[] = 'fr.2013.ottawa.wordcamp.org';
		$domains[] = 'es.2014.mallorca.wordcamp.org';
		$domains[] = 'de.2014.mallorca.wordcamp.org';
		$domains[] = 'ca.2014.mallorca.wordcamp.org';
		$domains[] = '2012.torontodev.wordcamp.org';
		$domains[] = 'utah.wordcamp.org';
		$domains[] = 'wordcampsf.org';
		$domains[] = 'wordcampsf.com';

		$domains = array_unique( $domains );
		$domains = apply_filters( 'wordcamp_letsencrypt_domains', $domains );

		return array_values( $domains );
	}
}

WordCamp_Lets_Encrypt_Helper::load();

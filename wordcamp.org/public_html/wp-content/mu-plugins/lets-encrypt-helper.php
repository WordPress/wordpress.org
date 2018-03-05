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

		/*
		 * Only request high-priority certificates right now.
		 *
		 * We're hitting LE API limits, and many certificates are failling to renew. The entire script is
		 * short-circuited when Acme throws an exception, so let's just get some high priority certs issued
		 * while we work on fixing the larger issue.
		 *
		 * Focusing on a small number of certs in the short term should also give us some breathing room on
		 * the limits.
		 *
		 * @todo OMGWTFBBQ
		 */
		return array(
			'2018.guadalajara.wordcamp.org',
			'2018.fayetteville.wordcamp.org',
			'2018.ottawa.wordcamp.org'
		);


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
		if ( is_callable( 'wcorg_get_domain_redirects' ) ) {
			$domains = array_merge( $domains, array_keys( wcorg_get_domain_redirects() ) );
		}

		$domains = array_unique( $domains );
		$domains = apply_filters( 'wordcamp_letsencrypt_domains', $domains );

		return array_values( $domains );
	}
}

WordCamp_Lets_Encrypt_Helper::load();

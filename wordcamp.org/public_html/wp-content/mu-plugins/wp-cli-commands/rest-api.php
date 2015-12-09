<?php

defined( 'WP_CLI' ) or die();

/**
 * WordCamp.org: Test REST API customizations.
 */
class WordCamp_CLI_REST_API extends WP_CLI_Command {
	/**
	 * Verify that no sensitive data is being exposed via the API.
	 *
	 * @subcommand verify-data-is-scrubbed
	 */
	public function verify_data_is_scrubbed() {
		$errors          = false;
		$start_timestamp = microtime( true );

		// These calls are not formatted in a more compact way because we don't want to short-circuit any of them if one fails
		if ( $this->post_types_exposed() ) {
			$errors = true;
		}

		if ( $this->post_meta_exposed() ) {
			$errors = true;
		}

		WP_CLI::line();
		WP_CLI::line( sprintf( 'Tests completed in %s seconds', number_format( microtime( true ) - $start_timestamp, 3 ) ) );

		if ( $errors ) {
			WP_CLI::error( 'Not all sensitive data has been scrubbed.' );
		} else {
			WP_CLI::success( 'All of the tests passed. If the tests are comprehensive and working properly, then all sensitive data has been properly scrubbed.' );
		}
	}

	/**
	 * Check if any sensitive post types are being exposed.
	 *
	 * See note in post_meta_exposed() about test data.
	 *
	 * @return bool
	 */
	protected function post_types_exposed() {
		$errors = false;

		WP_CLI::line();
		WP_CLI::line( 'Checking post types.' );

		// Check Central and a normal site, because they can have different types loaded
		$post_types_endpoints = array(
			'http://central.wordcamp.org/wp-json/posts/types',
			'http://europe.wordcamp.org/2014/wp-json/posts/types',
		);

		$whitelisted_post_types = array(
			'post', 'page', 'attachment', 'revision', 'wcb_speaker', 'wcb_session', 'wcb_sponsor', 'mes',
			'mes-sponsor-level', 'wordcamp'
		);

		foreach ( $post_types_endpoints as $request_url ) {
			$request_url = apply_filters( 'wcorg_json_api_verify_data_scrubbed_url', $request_url );    // Use this filter to override the URLs with corresponding endpoints on your sandbox
			$response    = json_decode( wp_remote_retrieve_body( wp_remote_get( $request_url ) ) );

			if ( empty( $response->post->slug ) ) {
				$errors = true;
				WP_CLI::warning( "Unable to retrieve post types from $request_url", false );
				continue;
			}

			foreach ( $response as $post_type ) {
				if ( in_array( $post_type->slug, $whitelisted_post_types ) ) {
					WP_CLI::line( "{$post_type->slug} is whitelisted." );
				} else {
					$errors = true;
					WP_CLI::warning( "{$post_type->slug} is being exposed at $request_url" );
				}
			}
		}

		return $errors;
	}

	/**
	 * Check if any sensitive post meta is being exposed.
	 *
	 * If this were a proper test we'd insert the data into a test db during setup rather than relying on the
	 * existence of production data, but this is good enough for our current needs. Just make sure to double
	 * check that the meta where checking still exists, otherwise the tests could result in a false-negative.
	 *
	 * @return bool
	 */
	protected function post_meta_exposed() {
		$errors = false;

		WP_CLI::line();
		WP_CLI::line( 'Checking post meta.' );

		// This is just a representative sample, not a complete list
		$sensitive_post_meta = array(
			'http://central.wordcamp.org/wp-json/posts/3038288'    => array( 'Email Address', 'Telephone', 'Mailing Address' ), // A wordcamp post on Central
			'http://central.wordcamp.org/wp-json/posts/2347409'    => array( 'mes_email_address' ),                             // A Multi-Event Sponsor post on Central
			'http://europe.wordcamp.org/2014/wp-json/posts/216283' => array( '_wcb_speaker_email' ),                            // A Speaker post on a camp site
		);

		foreach ( $sensitive_post_meta as $request_url => $sensitive_meta_keys ) {
			$request_url = apply_filters( 'wcorg_json_api_verify_data_scrubbed_url', $request_url );    // Use this filter to override the URLs with corresponding posts on your sandbox
			$response    = json_decode( wp_remote_retrieve_body( wp_remote_get( esc_url_raw( $request_url ) ) ) );

			if ( ! isset( $response->post_meta ) ) {
				$errors = true;
				WP_CLI::warning( "Unable to retrieve post meta from $request_url", false );
				continue;
			}

			foreach ( $response->post_meta as $post_meta ) {
				if ( in_array( $post_meta->key, $sensitive_meta_keys ) ) {
					$errors = true;
					WP_CLI::warning( "{$post_meta->key} is being exposed at $request_url" );
				} else {
					WP_CLI::line( "{$post_meta->key} is whitelisted." );
				}
			}
		}

		return $errors;
	}
}

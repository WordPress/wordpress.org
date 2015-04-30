<?php

/*
 * Customizations to the JSON REST API
 *
 * WARNING: It's very important to make sure that only data intended to be public is disclosed by the API. This
 * is particularly important when it comes to areas where we're customizing the output, like post meta.
 *
 * Before making any changes to this plugin or updating the JSON API to a new version, make sure you run the
 * automated tests -- wp wcorg-json-api verify-data-is-scrubbed -- in your sandbox, and then again on production
 * after you deploy any updates.
 *
 * You may also need to add new tests when making changes to this or other plugins, and/or update existing tests.
 */


/**
 * Unhook any endpoints that aren't whitelisted
 *
 * @param array $endpoints
 *
 * @return array
 */
function wcorg_json_whitelist_endpoints( $endpoints ) {
	$whitelisted_endpoints = array(
		'/posts'             => array( 'get_posts' ),
		'/posts/(?P<id>\d+)' => array( 'get_post'  ),
		// todo Add /posts/types too, because it's useful for debugging and there's no harm. It has a different array structure than the current ones, though, so this will need some work.
	);

	foreach ( $endpoints as $endpoint => $endpoint_data ) {
		/*
		 * Don't attempt to scan '/' because it has a different array structure than normal endpoints and is
		 * unlikely to expose anything sensitive.
		 */
		if ( '/' == $endpoint ) {
			continue;
		}

		if ( array_key_exists( $endpoint, $whitelisted_endpoints ) ) {
			// Allow the endpoint, but remove any of its callbacks that aren't whitelisted and read-only

			foreach ( $endpoint_data as $callback_index => $callback ) {
				$callback_name        = $callback[0][1];
				$callback_permissions = $callback[1];

				if ( ! in_array( $callback_name, $whitelisted_endpoints[ $endpoint ] ) || WP_JSON_Server::READABLE != $callback_permissions ) {
					unset( $endpoints[ $endpoint ][ $callback_index ] );
				}
			}
		} else {
			// Remove endpoints that aren't whitelisted

			unset( $endpoints[ $endpoint ] );
		}
	}

	return $endpoints;
}
add_filter( 'json_endpoints', 'wcorg_json_whitelist_endpoints', 999 );

/**
 * Expose a whitelisted set of meta data to unauthenticated JSON API requests
 *
 * Note: Some additional fields are added in `wcorg_json_expose_additional_post_data()`
 *
 * @param array  $prepared_post
 * @param array  $raw_post
 * @param string $context
 *
 * @return array
 */
function wcorg_json_expose_whitelisted_meta_data( $prepared_post, $raw_post, $context ) {
	$whitelisted_post_meta = array(
		'wordcamp' => array(
			'Start Date (YYYY-mm-dd)', 'End Date (YYYY-mm-dd)', 'Location', 'URL', 'Twitter', 'WordCamp Hashtag',
			'Number of Anticipated Attendees', 'Organizer Name', 'WordPress.org Username', 'Venue Name',
			'Physical Address', 'Maximum Capacity', 'Available Rooms', 'Website URL', 'Exhibition Space Available',
			// todo add Multi-Event Sponsor Region, but convert from ID to name so it will be meaningful without having to do extra lookup? Or convert to whitelisted object?
		),

		'wcb_session' => array( '_wcpt_session_time', '_wcpt_session_type' ),

		'wcb_sponsor' => array( '_wcpt_sponsor_website' ),
	);
	$targeted_post_types = array_keys( $whitelisted_post_meta );

	/*
	 * Wipe out any existing meta being exposed.
	 *
	 * It should already be empty unless $context == 'edit', but that may not be true in the future.
	 */
	$prepared_post['post_meta'] = array();

	if ( is_wp_error( $prepared_post ) || ! in_array( $prepared_post['type'], $targeted_post_types ) ) {
		return $prepared_post;
	}

	$post_meta_endpoint = new WP_JSON_Meta_Posts( $GLOBALS['wp_json_server'] );
	add_filter( 'json_check_post_edit_permission',    '__return_true'  );   // The API only exposes post meta to authenticated users, but we want to expose whitelisted items to everyone
	add_filter( 'is_protected_meta',                  '__return_false' );   // We want to include whitelisted items, even if they're marked as protected
	$post_meta = $post_meta_endpoint->get_all_meta( $raw_post['ID'] );
	remove_filter( 'is_protected_meta',               '__return_false' );
	remove_filter( 'json_check_post_edit_permission', '__return_true'  );

	foreach( $post_meta as $meta_item ) {
		if ( in_array( $meta_item['key'], $whitelisted_post_meta[ $prepared_post['type'] ] ) ) {
			$prepared_post['post_meta'][] = $meta_item;
		}
	}

	return $prepared_post;
}
add_filter( 'json_prepare_post', 'wcorg_json_expose_whitelisted_meta_data', 998, 3 );

/**
 * Expose additional data on post responses.
 *
 * Some fields can't be exposed directly for privacy or other reasons, but we can still provide interesting data
 * that is derived from those fields. For example, we can't expose a Speaker's e-mail address, but can we go ahead
 * and derive their Gravatar URL and expose that instead.
 *
 * In other cases, some data wouldn't be particularly useful or meaningful on its own, like the `_wcpt_speaker_id`
 * attached to a `wcb_session` post. Instead of providing that raw to the API, we can instead expand it into a
 * a full `wcb_speaker` object, so that clients don't have to make additional requests to fetch the data they
 * actually want.
 *
 * @param array  $prepared_post
 * @param array  $raw_post
 * @param string $context
 *
 * @return array
 */
function wcorg_json_expose_additional_post_data( $prepared_post, $raw_post, $context ) {
	if ( is_wp_error( $prepared_post ) || empty ( $prepared_post['type'] ) ) {
		return $prepared_post;
	}

	switch( $prepared_post['type'] ) {
		case 'wcb_speaker':
			$prepared_post['avatar'] = wcorg_json_get_speaker_avatar( $prepared_post['ID'] );
			break;
	}

	return $prepared_post;
}
add_filter( 'json_prepare_post', 'wcorg_json_expose_additional_post_data', 999, 3 );   // after `wcorg_json_expose_whitelisted_meta_data()`, because anything added before that method gets wiped out

/**
 * Get the avatar URL for the given speaker
 *
 * @param int $speaker_post_id
 *
 * @return string
 */
function wcorg_json_get_speaker_avatar( $speaker_post_id ) {
	$avatar = '';

	if ( $speaker_email = get_post_meta( $speaker_post_id, '_wcb_speaker_email', true ) ) {
		$avatar = json_get_avatar_url( $speaker_email );
	} elseif ( $speaker_user_id = get_post_meta( $speaker_post_id, '_wcpt_user_id', true ) ) {
		$avatar = json_get_avatar_url( $speaker_user_id );
	}

	return $avatar;
}

/**
 * Avoid nested callback conflicts by de-registering WP_JSON_Media::add_thumbnail_data().
 *
 * There's a Core bug (#17817) where nested callbacks conflict with each other. If a post has a featured image,
 * then `json_prepare_post` will be called recursively, and `wcorg_expose_whitelisted_json_api_meta_data()` will
 * be short-circuited.
 *
 * Hopefully the fix for #17817 will land in 4.3, but until then we can avoid the issue by de-registering
 * `add_thumbnail_data()`. This obviously has the downside of removing featured image data from the API responses,
 * but we don't have a compelling use case for that data right now, and I don't see a better alternative. Ensuring
 * that only whitelisted data is exposed is a privacy issue, and therefore trumps pretty much everything else.
 *
 * This is fragile, and will probably break when WP-API's `develop` branch is merged into `master`, so -- like
 * everything else in this plugin -- it should be re-tested and updated when new versions of the API are
 * released.
 *
 * See https://github.com/WP-API/WP-API/issues/1090
 * See https://core.trac.wordpress.org/ticket/17817
 */
function wcorg_json_avoid_nested_callback_conflicts() {
	/** @var $wp_json_media WP_JSON_Media */
	global $wp_json_media;

	remove_filter( 'json_prepare_post', array( $wp_json_media, 'add_thumbnail_data' ), 10, 3 );
}
add_action( 'wp_json_server_before_serve', 'wcorg_json_avoid_nested_callback_conflicts', 11 );    // after the default endpoints are added in `json_api_default_filters()`

/*
 * WP-CLI Commands
 */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	/**
	 * WordCamp.org JSON API
	 */
	class WordCamp_JSON_API_Commands extends WP_CLI_Command {
		/**
		 * Verify that no sensitive data is being exposed via the API.
		 *
		 * @subcommand verify-data-is-scrubbed
		 */
		public function verify_data_is_scrubbed() {
			$errors          = false;
			$start_timestamp = microtime( true );

			// These calls are not formatted in a more compact way because we don't want to short-circuit any of them if one fails
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

	WP_CLI::add_command( 'wcorg-json-api', 'WordCamp_JSON_API_Commands' );
}

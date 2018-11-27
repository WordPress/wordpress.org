<?php

namespace WordCamp\Utilities;
defined( 'WPINC' ) || die();

use WP_Error;
use GP_Locales;

/**
 * Class Genderize_Client
 *
 * @package WordCamp\Utilities
 */
class Genderize_Client extends API_Client {
	/**
	 * @var string The option key where the cache is saved in the database.
	 */
	const CACHE_KEY = 'genderize_cached_data';

	/**
	 * @var string The base URL for the API endpoints.
	 */
	protected $api_base = 'https://api.genderize.io/';

	/**
	 * @var string The API key.
	 */
	protected $api_key = '';

	/**
	 * @var array|null Data retrieved from the cache.
	 */
	protected $cache = null;

	/**
	 * Additional client parameters.
	 *
	 * @var array
	 */
	public $options = array();

	/**
	 * Genderize_Client constructor.
	 *
	 * @param string $api_key The API key for authenticating with Genderize.io.
	 * @param array  $options {
	 *     Optional. Additional client parameters.
	 *
	 *     @type bool $reset_cache True to delete the entire cache.
	 * }
	 */
	public function __construct( $api_key = '', array $options = [] ) {
		parent::__construct( [
			'breaking_response_codes' => [ 401, 402, 404, 422, 429 ],
		] );

		// Report-specific options.
		$this->options = wp_parse_args( $options, array(
			'reset_cache' => false,
		) );

		if ( $api_key ) {
			$this->api_key = $api_key;
		} elseif ( defined( 'GENDERIZE_IO_API_KEY' ) ) {
			$this->api_key = GENDERIZE_IO_API_KEY;
		} else {
			$this->error->add(
				'api_key_undefined',
				'The Genderize.io API Key is undefined.'
			);
		}

		if ( true === $this->options->reset_cache ) {
			delete_option( self::CACHE_KEY );
		}
	}

	/**
	 * Get gender data for a list of names, based on the locale.
	 *
	 * @param array  $names
	 * @param string $locale
	 *
	 * @return array
	 */
	public function get_gender_data( array $names, $locale ) {
		$names     = array_unique( array_map( 'strtolower', $names ) );
		$lang_code = $this->get_lang_code_from_locale( $locale );

		// Bail if there are errors.
		if ( ! empty( $this->error->get_error_messages() ) ) {
			return [];
		}

		$data         = [];
		$needs_update = [];

		foreach ( $names as $name ) {
			$item = $this->get_cache_item( $name, $lang_code );

			if ( false === $item ) {
				$needs_update[] = $name;
				continue;
			}

			$data[ $name ] = $item;
		}

		if ( ! empty( $needs_update ) ) {
			$updates = $this->send_chunked_request( $needs_update, $lang_code );

			// Bail if any errors were returned from the API.
			if ( ! empty( $this->error->get_error_messages() ) ) {
				return [];
			}

			foreach ( $updates as $update ) {
				$updated_name          = $this->update_cache_item( $update, $lang_code );
				$data[ $updated_name ] = $update;
			}

			$this->save_cached_data();
		}

		return $data;
	}

	/**
	 * Get an array of cached gender data.
	 *
	 * @return array
	 */
	protected function get_cached_data() {
		if ( is_null( $this->cache ) ) {
			$this->cache = get_option( self::CACHE_KEY, [] );
		}

		return $this->cache;
	}

	/**
	 * Save gender data back to the database.
	 *
	 * @return bool
	 */
	protected function save_cached_data() {
		if ( is_null( $this->cache ) ) {
			return false;
		}

		return update_option( self::CACHE_KEY, $this->cache, false );
	}

	/**
	 * Retrieve gender data for a particular name from the instance cache.
	 *
	 * @param string $name
	 * @param string $lang_code
	 *
	 * @return array|bool An array of gender data, or false if it's not in the cache or it's expired.
	 */
	protected function get_cache_item( $name, $lang_code ) {
		$cache     = $this->get_cached_data();
		$name      = strtolower( $name );

		if ( empty( $cache[ $lang_code ][ $name ] ) ) {
			return false;
		}

		$item = $cache[ $lang_code ][ $name ];

		if ( $this->is_cache_item_expired( $item ) ) {
			return false;
		}

		return $item;
	}

	/**
	 * Update the gender data for a particular name in the instance cache.
	 *
	 * Note that this does not save the data to the database. Use save_cached_data() for this after making a batch
	 * of updates.
	 *
	 * @param array  $data
	 * @param string $lang_code
	 *
	 * @return string The name for which data was updated.
	 */
	protected function update_cache_item( $data, $lang_code ) {
		$this->get_cached_data();

		if ( empty( $this->cache[ $lang_code ] ) ) {
			$this->cache[ $lang_code ] = [];
		}

		$name = strtolower( $data['name'] );
		unset( $data['name'] );

		$this->cache[ $lang_code ][ $name ] = $data;

		return $name;
	}

	/**
	 * Check the timestamp of the gender data for an item to see if it's expired.
	 *
	 * @param array $item
	 *
	 * @return bool True if it is expired.
	 */
	protected function is_cache_item_expired( $item ) {
		$lifespan = MONTH_IN_SECONDS * 6;
		$now      = time();

		if ( empty( $item['timestamp'] ) || $now - $item['timestamp'] > $lifespan ) {
			return true;
		}

		return false;
	}

	/**
	 * Query the Genderize API about a list of names.
	 *
	 * Submit requests in batches of 10 names, and collect all of the response data in one array. Normalize
	 * the data and insert a timestamp for each item for returning.
	 *
	 * @param array  $names
	 * @param string $lang_code
	 *
	 * @return array
	 */
	protected function send_chunked_request( array $names, $lang_code ) {
		$data   = [];
		$chunks = array_chunk( $names, 10 );

		foreach ( $chunks as $chunk ) {
			$url = add_query_arg( [
				'name'        => $chunk,
				'apikey'      => $this->api_key,
				'language_id' => $lang_code,
			], $this->api_base );

			$response = $this->tenacious_remote_get( $url );

			if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( is_array( $body ) ) {
					$data = array_merge( $data, $body );
				} else {
					$this->error->add(
						'unexpected_response_data',
						'The API response did not provide the expected data format.',
						$response
					);
					break;
				}
			} else {
				$this->handle_error_response( $response );
				break;
			}
		}

		$data = array_map( [ $this, 'normalize_data_item_from_api' ], $data );

		return $data;
	}

	/**
	 * Handle API responses containing errors.
	 *
	 * @param array|WP_Error $response
	 *
	 * @return void
	 */
	public function handle_error_response( $response ) {
		if ( parent::handle_error_response( $response ) ) {
			return;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$data          = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $data['error'] ) ) {
			$this->error->add( "error_{$response_code}", $data['error'] );
		} elseif ( $response_code ) {
			$this->error->add(
				'http_response_code',
				sprintf( 'HTTP Status: %d', absint( $response_code ) )
			);
		} else {
			$this->error->add( 'unknown_error', 'There was an unknown error.' );
		}
	}

	/**
	 * Get the ISO 639-1 language code from a WordPress locale.
	 *
	 * @param string $locale
	 *
	 * @return string
	 */
	protected function get_lang_code_from_locale( $locale ) {
		if ( ! is_readable( JETPACK__GLOTPRESS_LOCALES_PATH ) ) {
			$this->error->add(
				'locale_data_unavailable',
				'Cannot find the locale data from Jetpack.'
			);

			return 'en';
		}

		require_once( JETPACK__GLOTPRESS_LOCALES_PATH );

		$glotpress_locale = GP_Locales::by_field( 'wp_locale', $locale ?: 'en_US' );

		return $glotpress_locale->lang_code_iso_639_1;
	}

	/**
	 * Normalize the gender data provided by the API.
	 *
	 * @param array $item
	 *
	 * @return array
	 */
	protected function normalize_data_item_from_api( $item ) {
		$defaults = [
			'name'        => '',
			'gender'      => '',
			'probability' => floatval( 0 ),
			'timestamp'   => time(),
		];

		// Use shortcode_atts instead of wp_parse_args so that extra item parameters are removed.
		$item = shortcode_atts( $defaults, $item );

		$item['probability'] = floatval( $item['probability'] );

		return $item;
	}
}

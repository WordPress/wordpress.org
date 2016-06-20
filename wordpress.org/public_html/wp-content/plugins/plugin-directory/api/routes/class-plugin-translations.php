<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WP_REST_Server;

/**
 * An API Endpoint to expose translations data.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Plugin_Translations extends Plugin {

	function __construct() {
		register_rest_route( 'plugins/v1', '/plugin/(?P<plugin_slug>[^/]+)/translations/?', array(
			'methods'  => WP_REST_Server::READABLE,
			'callback' => array( $this, 'translations' ),
			'args' => array(
				'plugin_slug' => array(
					'validate_callback' => array( $this, 'validate_plugin_slug_callback' ),
				),
			),
		) );
	}

	/**
	 * Endpoint to retrieve locales in which the plugin got translated.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 * @return array A formatted array of all the data for the plugin.
	 */
	function translations( $request ) {
		global $wpdb;

		$plugin_slug = $request['plugin_slug'];

		// Get the locale subdomain associations used to link languages to their local site.
		$locale_subdomain_assoc = $wpdb->get_results(
			"SELECT locale, subdomain FROM locales WHERE locale NOT LIKE '%\_%\_%'", OBJECT_K // Don't grab variants, for now.
		);

		// Retrieve all the WordPress locales.
		$all_locales = wp_list_pluck( $locale_subdomain_assoc, 'locale' );

		// Get the active language packs of the plugin.
		$language_packs = $wpdb->get_results( $wpdb->prepare( "
			SELECT *
			FROM language_packs
			WHERE
				type = 'plugin' AND
				domain = %s AND
				active = 1
			GROUP BY language",
			$plugin_slug
		) );

		// Retrieve all the WordPress locales in which the plugin is translated.
		$translated_locales = wp_list_pluck( $language_packs, 'language' );

		// Get the WordPress locales based on the HTTP accept language header.
		$locales_from_header = $this->get_locale_from_header( $all_locales );

		$current_locale = get_locale();

		// Build a list of WordPress locales which we'll suggest to the user.
		$suggest_locales = array_values( array_intersect( $translated_locales, $locales_from_header ) );
		$current_locale_is_suggested = in_array( $current_locale, $suggest_locales );
		if ( $current_locale_is_suggested ) {
			$suggest_locales = array_diff( $suggest_locales, [ $current_locale ] );
		}

		// Get the native language names of the locales.
		$suggest_named_locales = [];
		foreach ( $suggest_locales as $locale ) {
			$slug = str_replace( '_', '-', $locale );
			$slug = strtolower( $slug );
			$name = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM languages WHERE slug = %s', $slug ) );
			if ( ! $name ) {
				$fallback_slug = explode( '-', $slug )[0]; // de-de => de
				$name = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM languages WHERE slug = %s', $fallback_slug ) );
				$suggest_named_locales[ $locale ] = $name;
			} else {
				$suggest_named_locales[ $locale ] = $name;
			}
		}

		$suggest_string = '';
		if ( 1 === count( $suggest_named_locales ) ) {
			$locale = key( $suggest_named_locales );
			$language = current( $suggest_named_locales );
			$suggest_string = sprintf(
				/* translators: %s: native language name. */
				__( 'This plugin is also available in %s.', 'wporg-plugins' ),
				sprintf(
					'<a href="https://%s.wordpress.org/plugins-wp/%s/">%s</a>',
					$locale_subdomain_assoc[ $locale ]->subdomain,
					$plugin_slug,
					$language
				)
			);
		} elseif ( ! empty( $suggest_named_locales ) ) {
			$primary_locale = key( $suggest_named_locales );
			$primary_language = current( $suggest_named_locales );
			array_shift( $suggest_named_locales );

			$other_suggest = '';
			foreach ( $suggest_named_locales as $locale => $language ) {
				$other_suggest .= sprintf(
					'<a href="https://%s.wordpress.org/plugins-wp/%s/">%s</a>, ',
					$locale_subdomain_assoc[ $locale ]->subdomain,
					$plugin_slug,
					$language
				);
			}

			$suggest_string = sprintf(
				/* translators: 1: native language name, 2: native language names */
				__( 'This plugin is also available in %1$s (also: %2$s).', 'wporg-plugins' ),
				sprintf(
					'<a href="https://%s.wordpress.org/plugins-wp/%s/">%s</a>',
					$locale_subdomain_assoc[ $primary_locale ]->subdomain,
					$plugin_slug,
					$primary_language
				),
				trim( $other_suggest, ' ,' )
			);
		} elseif ( 'en_US' !== $current_locale && ! $current_locale_is_suggested ) {
			$suggest_string = sprintf(
				/* translators: %s: URL to translate.wordpress.org */
				__( 'This plugin is not yet available in your language. <a href="%s">Can you help translating it?</a>', 'wporg-translate' ),
				'https://translate.wordpress.org/projects/wp-plugins/' . $plugin_slug
			);
		}

		$result = [
			'suggest_string' => $suggest_string,
			'translated'     => $translated_locales,
		];

		return $result;
	}

	/**
	 * Get locales matching the HTTP accept language header.
	 *
	 * @param array $available_locales List of available locales.
	 * @return array List of locales.
	 */
	function get_locale_from_header( $available_locales ) {
		$res = array();

		if ( ! $available_locales ) {
			return $res;
		}

		if ( ! isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return $res;
		}

		$http_locales = $this->get_http_locales( $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

		if ( is_array( $http_locales ) ) {
			foreach ( $http_locales as $http_locale ) {
				/*
				 * Discard English -- it's the default for all browsers,
				 * ergo not very reliable information
				 */
				if ( 'en' === $http_locale || 'en-US' === $http_locale ) {
					continue;
				}

				@list( $lang, $region ) = explode( '-', $http_locale );
				if ( is_null( $region ) ) {
					$region = $lang;
				}

				if ( $mapped = $this->map_locale( $lang, $region, $available_locales ) ) {
					$res[] = $mapped;
				}
			}

			$res = array_unique( $res );
		}

		return $res;
	}

	/**
	 * Given a HTTP Accept-Language header $header
	 * returns all the locales in it.
	 *
	 * @param string $header HTTP acccept header.
	 * @return array Matched locales.
	 */
	protected function get_http_locales( $header ) {
		$locale_part_re = '[a-z]{2,}';
		$locale_re = "($locale_part_re(\-$locale_part_re)?)";
		if ( preg_match_all( "/$locale_re/i", $header, $matches ) ) {
			return $matches[0];
		} else {
			return array();
		}
	}

	/**
	 * Tries to map a lang/region pair to one of our locales.
	 *
	 * @param string $lang              Lang part of the HTTP accept header.
	 * @param string $region            Region part of the HTTP accept header.
	 * @param array  $available_locales List of available locales.
	 * @return string|false Our locale matching $lang and $region, false otherwise.
	 */
	protected function map_locale( $lang, $region, $available_locales ) {
		$uregion = strtoupper( $region );
		$ulang   = strtoupper( $lang );
		$variants = array(
			"$lang-$region",
			"{$lang}_$region",
			"$lang-$uregion",
			"{$lang}_$uregion",
			"{$lang}_$ulang",
			$lang,
		);

		foreach ( $variants as $variant ) {
			if ( in_array( $variant, $available_locales ) ) {
				return $variant;
			}
		}

		foreach ( $available_locales as $locale ) {
			list( $lang, ) = preg_split( '/[_-]/', $locale );
			if ( $lang  ) {
				return $locale;
			}
		}

		return false;
	}
}

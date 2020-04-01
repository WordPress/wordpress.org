<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;

use WordPressdotorg\Plugin_Directory\API\Base;
use WP_REST_Server;

/**
 * An API Endpoint to expose translations data.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Locale_Banner extends Base {

	function __construct() {
		register_rest_route( 'plugins/v1', '/locale-banner', array(
			'methods'  => WP_REST_Server::ALLMETHODS,
			'callback' => array( $this, 'locale_banner' ),
			'args'     => array(
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
	function locale_banner( $request ) {
		global $wpdb;

		$plugin_slug = $request['plugin_slug'];

		// Get the locale subdomain associations used to link languages to their local site.
		$locale_subdomain_assoc = $wpdb->get_results(
			"SELECT locale, subdomain FROM wporg_locales WHERE locale NOT LIKE '%\_%\_%'", OBJECT_K // Don't grab variants, for now.
		);

		// Retrieve all the WordPress locales.
		$all_locales = wp_list_pluck( $locale_subdomain_assoc, 'locale' );

		$is_plugin_request = ! empty( $plugin_slug );

		if ( $is_plugin_request ) {
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
		} else {
			$translated_locales = $all_locales;
		}

		// Get the WordPress locales based on the HTTP accept language header.
		$locales_from_header = $this->get_locale_from_header( $all_locales );

		$current_locale = get_locale();

		require_once GLOTPRESS_LOCALES_PATH;

		// Validate the list of locales can be found by `wp_locale`.
		$translated_locales = array_filter( $translated_locales, function( $locale ) {
			return \GP_Locales::by_field( 'wp_locale', $locale );
		} );

		// Build a list of WordPress locales which we'll suggest to the user.
		$suggest_locales              = array_values( array_intersect( $locales_from_header, $translated_locales ) );
		$current_locale_is_suggested  = in_array( $current_locale, $suggest_locales );
		$current_locale_is_translated = in_array( $current_locale, $translated_locales );

		// Check to see if the plugin is localizable.
		$current_plugin_is_translatable = true;
		if ( ! $current_locale_is_translated ) {
			$current_plugin_is_translatable = (bool) $wpdb->get_var( $wpdb->prepare(
				'SELECT id
				FROM translate_projects
				WHERE path IN( %s, %s ) AND active = 1
				LIMIT 1',
				'wp-plugins/' . $plugin_slug . '/dev',
				'wp-plugins/' . $plugin_slug . '/stable'
			) );
		}

		// Get the native language names of the locales.
		$suggest_named_locales = [];
		foreach ( $suggest_locales as $locale ) {
			$suggest_named_locales[ $locale ] = \GP_Locales::by_field( 'wp_locale', $locale )->native_name;
		}

		$suggest_string = '';

		// English directory.
		if ( 'en_US' === $current_locale ) {
			$current_path   = get_site()->path;
			$referring_path = wp_parse_url( $request->get_header( 'referer' ), PHP_URL_PATH );

			if ( $referring_path && '/' === $referring_path[0] ) {
				$current_path = $referring_path;
			}

			// Only one locale suggestion.
			if ( 1 === count( $suggest_named_locales ) ) {
				$locale   = key( $suggest_named_locales );
				$language = current( $suggest_named_locales );

				if ( $is_plugin_request ) {
					$suggest_string = sprintf(
						$this->translate( 'This plugin is also available in %1$s. <a href="%2$s">Help improve the translation!</a>', $locale ),
						sprintf(
							'<a href="https://%s.wordpress.org%s">%s</a>',
							$locale_subdomain_assoc[ $locale ]->subdomain,
							esc_url( $current_path ),
							$language
						),
						esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $plugin_slug )
					);
				} else {
					$suggest_string = sprintf(
						$this->translate( 'The plugin directory is also available in %s.', $locale ),
						sprintf(
							'<a href="https://%s.wordpress.org%s">%s</a>',
							$locale_subdomain_assoc[ $locale ]->subdomain,
							esc_url( $current_path ),
							$language
						)
					);
				}

				// Multiple locale suggestions.
			} elseif ( ! empty( $suggest_named_locales ) ) {
				$primary_locale   = key( $suggest_named_locales );
				$primary_language = current( $suggest_named_locales );
				array_shift( $suggest_named_locales );

				if ( $is_plugin_request ) {
					$other_suggest = '';
					foreach ( $suggest_named_locales as $locale => $language ) {
						$other_suggest .= sprintf(
							'<a href="https://%s.wordpress.org%s/">%s</a>, ',
							$locale_subdomain_assoc[ $locale ]->subdomain,
							esc_url( $current_path ),
							$language
						);
					}

					$suggest_string = sprintf(
						$this->translate( 'This plugin is also available in %1$s (also: %2$s). <a href="%3$s">Help improve the translation!</a>', $primary_locale ),
						sprintf(
							'<a href="https://%s.wordpress.org%s">%s</a>',
							$locale_subdomain_assoc[ $primary_locale ]->subdomain,
							esc_url( $current_path ),
							$primary_language
						),
						trim( $other_suggest, ' ,' ),
						esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $plugin_slug )
					);
				} else {
					$other_suggest = '';
					foreach ( $suggest_named_locales as $locale => $language ) {
						$other_suggest .= sprintf(
							'<a href="https://%s.wordpress.org%s">%s</a>, ',
							$locale_subdomain_assoc[ $locale ]->subdomain,
							esc_url( $current_path ),
							$language
						);
					}

					$suggest_string = sprintf(
						$this->translate( 'The plugin directory is also available in %1$s (also: %2$s).', $primary_locale ),
						sprintf(
							'<a href="https://%s.wordpress.org%s">%s</a>',
							$locale_subdomain_assoc[ $primary_locale ]->subdomain,
							esc_url( $current_path ),
							$primary_language
						),
						trim( $other_suggest, ' ,' )
					);
				}

				// Non-English locale in header, no translations.
			} elseif ( $is_plugin_request && $locales_from_header ) {
				$locale = reset( $locales_from_header );

				$suggest_string = sprintf(
					$this->translate( 'This plugin is not translated into %1$s yet. <a href="%2$s">Help translate it!</a>', $locale ),
					\GP_Locales::by_field( 'wp_locale', $locale )->native_name,
					esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $plugin_slug )
				);
			}

			// Localized directory.
		} elseif ( ! $current_locale_is_suggested && ! $current_locale_is_translated && $is_plugin_request && $current_plugin_is_translatable ) {
			$suggest_string = sprintf(
				$this->translate( 'This plugin is not translated into %1$s yet. <a href="%2$s">Help translate it!</a>', $current_locale ),
				\GP_Locales::by_field( 'wp_locale', $current_locale )->native_name,
				esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $plugin_slug )
			);
		}

		$result = new \WP_REST_Response( [
			'suggest_string' => $suggest_string,
			'translated'     => $translated_locales,
		] );

		// Allow this API to be cached, varied by Accept-Language header.
		$result->header( 'Vary', 'Accept-Language' );
		$result->header( 'Expires', gmdate( 'r', time() + 3600 ) );
		$result->header( 'Cache-Control', 'max-age=3600' );

		// Don't output the Vary: Origin header, nginx doesn't like multiple Vary headers.
		// This also disables the Cross-Origin headers, but this API is only used by the same origin.
		remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );

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
				@list( $lang, $region ) = explode( '-', $http_locale );
				if ( is_null( $region ) ) {
					$region = $lang;
				}

				/*
				 * Discard English -- it's the default for all browsers,
				 * ergo not very reliable information
				 */
				if ( 'en' === $lang ) {
					continue;
				}

				// Region should be uppercase.
				$region = strtoupper( $region );

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
		$locale_re      = "($locale_part_re(\-$locale_part_re)?)";

		if ( preg_match_all( "/$locale_re/i", $header, $matches ) ) {
			return $matches[0];
		} else {
			return [];
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
		$uregion  = strtoupper( $region );
		$ulang    = strtoupper( $lang );
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
			list( $locale_lang, ) = preg_split( '/[_-]/', $locale );
			if ( $lang === $locale_lang ) {
				return $locale;
			}
		}

		return false;
	}

	/**
	 * Translates a string into a language.
	 *
	 * @param string $string The string to translate.
	 * @param string $wp_locale A WP locale of a language.
	 *
	 * @return mixed
	 */
	protected function translate( $string, $wp_locale ) {
		global $wpdb;

		$strings = array(
			5118332 => 'This plugin is also available in %1$s. <a href="%2$s">Help improve the translation!</a>',
			5118333 => 'This plugin is also available in %1$s (also: %2$s). <a href="%3$s">Help improve the translation!</a>',
			2984795 => 'This plugin is not translated into %1$s yet. <a href="%2$s">Help translate it!</a>',
			3004513 => 'The plugin directory is also available in %s.',
			3004514 => 'The plugin directory is also available in %1$s (also: %2$s).',
		);

		$original_id = array_search( $string, $strings, true );
		if ( ! $original_id ) {
			return $string;
		}

		$gp_locale = \GP_Locales::by_field( 'wp_locale', $wp_locale )->slug;

		$cache = wp_cache_get( 'original-' . $original_id, 'lang-guess-translations' );
		if ( false !== $cache ) {
			return isset( $cache[ $gp_locale ] ) ? $cache[ $gp_locale ] : $string;
		}

		// Magic number: 348841 is meta/plugins-v3.
		$translations = $wpdb->get_results( $wpdb->prepare( "
			SELECT
				locale as gp_locale, translation_0 as translation
			FROM translate_translation_sets ts
			JOIN translate_translations t
				ON ts.id = t.translation_set_id
			WHERE
				project_id = 348841 AND slug = 'default' AND t.status = 'current'
			AND original_id = %d
		", $original_id ), OBJECT_K );

		foreach ( $translations as &$translation ) {
			$translation = $translation->translation;
		}
		unset( $translation );

		wp_cache_add( 'original-' . $original_id, $translations, 'lang-guess-translations', 900 );

		return isset( $translations[ $gp_locale ] ) ? $translations[ $gp_locale ] : $string;
	}
}

// Strings for the POT file.
/* translators: %s: native language name. */
__( 'This plugin is also available in %1$s. <a href="%2$s">Help improve the translation!</a>', 'wporg-plugins' );
/* translators: 1: native language name, 2: other native language names, comma separated */
__( 'This plugin is also available in %1$s (also: %2$s). <a href="%3$s">Help improve the translation!</a>', 'wporg-plugins' );
/* translators: 1: native language name, 2: URL to translate.wordpress.org */
__( 'This plugin is not translated into %1$s yet. <a href="%2$s">Help translate it!</a>', 'wporg-plugins' );
/* translators: %s: native language name. */
__( 'The plugin directory is also available in %s.', 'wporg-plugins' );
/* translators: 1: native language name, 2: other native language names, comma separated */
__( 'The plugin directory is also available in %1$s (also: %2$s).', 'wporg-plugins' );

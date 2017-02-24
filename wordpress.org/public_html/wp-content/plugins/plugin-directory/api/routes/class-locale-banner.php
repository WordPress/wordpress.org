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
			'methods'  => WP_REST_Server::EDITABLE,
			'callback' => array( $this, 'locale_banner' ),
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
	function locale_banner( $request ) {
		global $wpdb;

		$plugin_slug = $request['plugin_slug'];

		// Get the locale subdomain associations used to link languages to their local site.
		$locale_subdomain_assoc = $wpdb->get_results(
			"SELECT locale, subdomain FROM locales WHERE locale NOT LIKE '%\_%\_%'", OBJECT_K // Don't grab variants, for now.
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

		// Build a list of WordPress locales which we'll suggest to the user.
		$suggest_locales = array_values( array_intersect( $locales_from_header, $translated_locales ) );
		$current_locale_is_suggested = in_array( $current_locale, $suggest_locales );
		$current_locale_is_translated = in_array( $current_locale, $translated_locales );

		// Get the native language names of the locales.
		$suggest_named_locales = [];
		foreach ( $suggest_locales as $locale ) {
			$name = $this->get_native_language_name( $locale );
			if ( $name ) {
				$suggest_named_locales[ $locale ] = $name;
			}
		}

		$suggest_string = '';
		if ( 'en_US' === $current_locale ) {
			if ( 1 === count( $suggest_named_locales ) ) {
				$locale   = key( $suggest_named_locales );
				$language = current( $suggest_named_locales );

				if ( $is_plugin_request ) {
					$suggest_string = sprintf(
						$this->translate( 'This plugin is also available in %s.', $locale ),
						sprintf(
							'<a href="https://%s.wordpress.org/plugins-wp/%s/">%s</a>',
							$locale_subdomain_assoc[ $locale ]->subdomain,
							$plugin_slug,
							$language
						)
					);
				} else {
					$suggest_string = sprintf(
						$this->translate( 'The plugin directory is also available in %s.', $locale ),
						sprintf(
							'<a href="https://%s.wordpress.org/plugins-wp/">%s</a>',
							$locale_subdomain_assoc[ $locale ]->subdomain,
							$language
						)
					);
				}
			} elseif ( ! empty( $suggest_named_locales ) ) {
				$primary_locale = key( $suggest_named_locales );
				$primary_language = current( $suggest_named_locales );
				array_shift( $suggest_named_locales );

				if ( $is_plugin_request ) {
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
						$this->translate( 'This plugin is also available in %1$s (also: %2$s).', $primary_locale ),
						sprintf(
							'<a href="https://%s.wordpress.org/plugins-wp/%s/">%s</a>',
							$locale_subdomain_assoc[ $primary_locale ]->subdomain,
							$plugin_slug,
							$primary_language
						),
						trim( $other_suggest, ' ,' )
					);
				} else {
					$other_suggest = '';
					foreach ( $suggest_named_locales as $locale => $language ) {
						$other_suggest .= sprintf(
							'<a href="https://%s.wordpress.org/plugins-wp/">%s</a>, ',
							$locale_subdomain_assoc[ $locale ]->subdomain,
							$language
						);
					}

					$suggest_string = sprintf(
						$this->translate( 'The plugin directory is also available in %1$s (also: %2$s).', $primary_locale ),
						sprintf(
							'<a href="https://%s.wordpress.org/plugins-wp/">%s</a>',
							$locale_subdomain_assoc[ $primary_locale ]->subdomain,
							$primary_language
						),
						trim( $other_suggest, ' ,' )
					);
				}
			}
		} elseif ( ! $current_locale_is_suggested && ! $current_locale_is_translated && $is_plugin_request ) {
			$suggest_string = sprintf(
				$this->translate( 'This plugin is not available in %1$s yet. <a href="%2$s">Help translate it!</a>', $current_locale ),
				$this->get_native_language_name( $current_locale ),
				esc_url( 'https://translate.wordpress.org/projects/wp-plugins/' . $plugin_slug )
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
			2984793 => 'This plugin is also available in %s.',
			2984794 => 'This plugin is also available in %1$s (also: %2$s).',
			2984795 => 'This plugin is not available in %1$s yet. <a href="%2$s">Help translate it!</a>',
			0 => 'The plugin directory is also available in %s.',
			0 => 'The plugin directory is also available in %1$s (also: %2$s).',
		);

		$original_id = array_search( $string, $strings, true );
		if ( ! $original_id ) {
			return $string;
		}

		require_once GLOTPRESS_LOCALES_PATH;
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
		", $original_id	), OBJECT_K );

		foreach ( $translations as &$translation ) {
			$translation = $translation->translation;
		}
		unset( $translation );

		wp_cache_add( 'original-' . $original_id, $translations, 'lang-guess-translations', 900 );

		return isset( $translations[ $gp_locale ] ) ? $translations[ $gp_locale ] : $string;
	}

	protected function get_native_language_name( $locale ) {
		global $wpdb;

		$slug = str_replace( '_', '-', $locale );
		$slug = strtolower( $slug );

		$name = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM languages WHERE slug = %s', $slug ) );
		if ( ! $name ) {
			$fallback_slug = explode( '-', $slug )[0]; // de-de => de
			$name = $wpdb->get_var( $wpdb->prepare( 'SELECT name FROM languages WHERE slug = %s', $fallback_slug ) );
			if ( $name ) {
				return $name;
			}
		} else {
			return $name;
		}

		return '';
	}
}

// Strings for the POT file.

/* translators: %s: native language name. */
__( 'This plugin is also available in %s.', 'wporg-plugins' );
/* translators: 1: native language name, 2: other native language names, comma separated */
__( 'This plugin is also available in %1$s (also: %2$s).', 'wporg-plugins' );
/* translators: 1: native language name, 2: URL to translate.wordpress.org */
__( 'This plugin is not available in %1$s yet. <a href="%2$s">Help translate it!</a>', 'wporg-plugins' );
/* translators: %s: native language name. */
__( 'The plugin directory is also available in %s.', 'wporg-plugins' );
/* translators: 1: native language name, 2: other native language names, comma separated */
__( 'The plugin directory is also available in %1$s (also: %2$s).', 'wporg-plugins' );

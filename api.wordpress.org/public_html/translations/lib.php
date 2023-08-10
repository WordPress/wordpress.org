<?php

// Used by api.wordpress.org/translations/core/1.0/
function find_all_translations_for_core( $version = null ) {
	return find_all_translations_for_type_and_domain( 'core', 'default', $version );
}

// Used by api.wordpress.org/translations/plugins/1.0/, api.wordpress.org/translations/themes/1.0/, and wordpress.org/plugins/wp-json/plugins/v1/plugin/$slug
function find_all_translations_for_type_and_domain( $type, $domain = 'default', $version = null ) {
	global $wpdb;

	if ( $type === 'core' && null === $version ) {
		$version = WP_CORE_LATEST_RELEASE;
	}

	// Optimize against junk inputs.
	if ( ! $domain || ! is_string( $domain ) ) {
		return array();
	}
	if ( $version && ! is_string( $version ) ) {
		return array();
	}

	// See wporg-gp-customizations/inc/cli/class-language-pack.php for where this is flushed from.
	$cache_group = 'translations-query';
	$cache_time  = 10800; // 3 hours, 6 hours if no results found.
	$cache_key   = "$type:$domain:$version";

	wp_cache_add_global_groups( $cache_group );

	$translations = wp_cache_get( $cache_key, $cache_group );
	if ( '_empty_' === $translations ) {
		return array();
	} elseif ( ! $translations ) {
		$translations = $wpdb->get_results( $wpdb->prepare(
			"SELECT language, version, updated FROM language_packs WHERE type = %s AND domain = %s AND active = 1",
			$type, $domain ) );

		if ( ! $translations ) {
			wp_cache_add( $cache_key, '_empty_', $cache_group, $cache_time * 2 );
			return array();
		}

		usort( $translations, function( $a, $b ) {
			return version_compare( $b->version, $a->version );
		} );

		$_translations = array();
		foreach ( $translations as $translation ) {
			if ( isset( $_translations[ $translation->language ] ) ) {
				continue;
			}
			if ( ! $version || $version === $translation->version || version_compare( $version, $translation->version, '>=' ) ) {
				$_translations[ $translation->language ] = $translation;
			}
		}
		$translations = array_values( $_translations );
	}

	require_once GLOTPRESS_LOCALES_PATH;

	$base_url = is_ssl() ? 'https' : 'http';
	$base_url .= '://downloads.wordpress.org/translation/';
	$base_url .= ( $type == 'core' ) ? 'core' : "$type/$domain";

	$_translations = array();
	if ( 'core' === $type ) {
		$continue_translations = wp_cache_get( 'continue-strings', $cache_group );
		if ( ! $continue_translations ) {
			// Magic numbers: 78 is wp/dev/admin. 326 is 'Continue'.
			$continue_translations = $wpdb->get_results(
				"SELECT
					IF(ts.slug <> 'default', CONCAT(ts.locale, '/', ts.slug), ts.locale) as slug,
					translation_0 as translation
				FROM translate_translation_sets ts
				INNER JOIN translate_translations t
				ON ts.id = t.translation_set_id
				WHERE project_id = 78
				AND original_id = 326", OBJECT_K
			);
			wp_cache_add( 'continue-strings', $continue_translations, $cache_group, $cache_time );
		}
	}

	$i = 0;
	foreach ( $translations as $translation ) {
		$locale = GP_Locales::by_field( 'wp_locale', $translation->language );
		if ( ! $locale ) {
			// Locale not known to us.
			continue;
		}

		$isos = array( 1 => false, 2 => false, 3 => false );
		// We'll use ISO codes for sorting.
		// Prefer 639-1 over 639-2 over 639-3 for this. All Spanish variants have 639-1 "es",
		// and we want to sort those together. (639-2 could be "spa".)
		if ( $locale->lang_code_iso_639_3 ) {
			$key = $isos[3] = $locale->lang_code_iso_639_3;
		}
		if ( $locale->lang_code_iso_639_2 ) {
			$key = $isos[2] = $locale->lang_code_iso_639_2;
		}
		if ( $locale->lang_code_iso_639_1 ) {
			$key = $isos[1] = $locale->lang_code_iso_639_1;
		}
		$isos = array_filter( $isos );

		if ( array() === $isos ) {
			continue; // uhhhh
		}

		// ISO codes are being used for sorting. Don't let variants stomp on each other.
		if ( isset( $_translations[ $key ] ) ) {
			$key .= ++$i;
		}

		$_translations[ $key ] = $translation;
		$_translations[ $key ]->english_name = $locale->english_name;
		$_translations[ $key ]->native_name = $locale->native_name;
		$_translations[ $key ]->package = sprintf( "$base_url/%s/%s.zip", $translation->version, $translation->language );
		$_translations[ $key ]->iso = (object) $isos;

		if ( 'core' === $type ) {
			$continue = isset( $continue_translations[ $locale->slug ] ) ? $continue_translations[ $locale->slug ]->translation : '';
			$_translations[ $key ]->strings = (object) array( 'continue' => $continue );
		}
	}
	ksort( $_translations );
	$translations = array_values( $_translations );

	wp_cache_add( $cache_key, $translations, $cache_group, $cache_time );

	return $translations;
}

// Used by check_for_translations_paired_with_update(), and check_for_translations_of_installed_items()
// Used by api.wordpress.org/plugins/update-check/, api.wordpress.org/themes/update-check, and api.wordpress.org/core/version-check/.
function find_latest_translations( $args ) {
	global $wpdb;
	extract( $args, EXTR_SKIP );

	// See wporg-gp-customizations/inc/cli/class-language-pack.php for where this is flushed from.
	$translations_cache_group = 'update-check-translations';
	$translations_cache_time  = 10800; // 3 hours, 6 hours if no results found.

	wp_cache_add_global_groups( $translations_cache_group );

	// Filter the requested languages down to only valid translated locales.
	$translated_languages = find_translated_locales_for( $type, $domain );
	$languages            = array_filter( $languages, function( $language ) use( $translated_languages ) {
		return (
			// Skip any invalid language data requested
			is_string( $language ) &&
			// and only query for languages this item is translated into
			in_array( strtolower( $language ), $translated_languages, true )
		);
	} );

	if ( ! $languages ) {
		return array();
	}

	$return = array();
	foreach ( $languages as $language ) {
		// Disable LP's for en_US for now, can re-enable later if we have non-en_US native items
		if ( 'en_US' == $language ) {
			continue;
		}

		$cache_key = "{$type}:{$language}:{$domain}";

		$results = wp_cache_get( $cache_key, $translations_cache_group );
		if ( ! $results ) {
			$query = $wpdb->prepare(
				"SELECT `version`, `updated`
				FROM `language_packs`
				WHERE `type` = %s AND `domain` = %s AND `language` = %s AND `active` = 1
				ORDER BY `version` DESC",
				$type,
				$domain,
				$language
			);

			$results = $wpdb->get_results( $query, ARRAY_A );

			if ( $results ) {
				usort( $results, function( $a, $b ) {
					return version_compare( $b['version'], $a['version'] );
				} );
			}

			if ( $results ) {
				wp_cache_set( $cache_key, $results, $translations_cache_group, $translations_cache_time );
			} else {
				$results = '_empty_';
				wp_cache_set( $cache_key, $results, $translations_cache_group, $translations_cache_time * 2 );
			}
		}

		// No language packs were found
		if ( '_empty_' == $results ) {
			continue;
		}

		if ( $version ) {
			$found = false;
			foreach ( $results as $result ) {
				if ( version_compare( $result['version'], $version, '<=' ) ) {
					$found = true;
					break;
				}
			}
		} else {
			$found = true;
			$result = $results[0];
		}

		if ( 'core' === $type ) {
			$path = "core/{$result['version']}/$language.zip";
			$urlpath = $path; // paths are identical for core
		} else {
			$path = "{$type}s/$domain/{$result['version']}/$language.zip"; // rosetta builds dir uses plural plugins/themes path
			$urlpath = "{$type}/$domain/{$result['version']}/$language.zip"; // url uses singular plugin/theme path
		}

		if ( $found && $result && file_exists( ROSETTA_BUILDS . $path ) ) {
			$return[] = array(
				'type'     => $type,
				'slug'     => $domain,
				'language' => $language,
				'version'  => $result['version'],
				'updated'  => $result['updated'],
				'package'  => maybe_ssl_url( "http://downloads.wordpress.org/translation/$urlpath" ),
				'autoupdate' => true,
			);
		}
	}

	return $return;
}

// Used by api.wordpress.org/plugins/update-check/ (disabled)
function check_for_translations_paired_with_update( $args ) {
	extract( $args );
	$translations_for_update       = array();
	$translations_found_for_update = find_latest_translations( array( 'type' => $type, 'domain' => $domain, 'version' => $version, 'languages' => $languages ) );

	foreach ( $translations_found_for_update as $language_pack ) {
		$update = true;
		if ( isset( $language_data[ $language_pack['language'] ] ) ) {
			$wporg_updated = strtotime( $language_pack['updated'] );
			$site_updated  = strtotime( $language_data[ $language_pack['language'] ]['PO-Revision-Date'] );

			// This update has a language update if: a) the PO file on WP.org is newer,
			// or b) the language pack's minimum version is higher than the currently installed version.
			// Example: version 3.7 is ready to go, but someone releases an update for 3.6.1.
			// The 3.6.1 strings are "newer" than the 3.7 strings, but the 3.7 language pack is
			// the new minimum, so it needs to be served.
			if ( $wporg_updated <= $site_updated && version_compare( $current_version, $language_pack['version'], '>=' ) ) {
				$update = false;
			}
		}

		if ( ! $update ) {
			continue;
		}

		$translations_for_update[] = $language_pack;
	}
	return $translations_for_update;
}

// Used by api.wordpress.org/plugins/update-check/, api.wordpress.org/themes/update-check, and api.wordpress.org/core/version-check/.
function check_for_translations_of_installed_items( $args ) {
	extract( $args );
	$translations_for_current = find_latest_translations( array( 'type' => $type, 'domain' => $domain, 'version' => $version, 'languages' => $languages ) );

	$translations = array();
	foreach ( $translations_for_current as $language_pack ) {
		$update = true;
		if ( isset( $language_data[ $language_pack['language'] ]['PO-Revision-Date'] ) ) {
			$wporg_updated = strtotime( $language_pack['updated'] );
			$site_updated  = strtotime( $language_data[ $language_pack['language'] ]['PO-Revision-Date'] );

			if ( $wporg_updated <= $site_updated ) {
				$update = false;
			}
		}

		if ( ! $update ) {
			continue;
		}

		$translations[] = $language_pack;
	}

	return $translations;
}

/**
 * Find the languages an item is actually translated into.
 *
 * Used by find_latest_translations().
 *
 * @param string $type   Type of item. Expects 'core', 'plugin', or 'theme'.
 * @param string $domain Item slug.
 * @return array Array of lower-case language codes.
 */
function find_translated_locales_for( $type, $domain ) {
	global $wpdb;

	$cache_group     = 'translations-query';
	$cache_key       = "{$type}:{$domain}";
	$cache_time      = 86400; // 24hrs
	$apcu_cache_time = 60;

	wp_cache_add_global_groups( $cache_group );

	$languages = apcu_fetch( "{$cache_group}:{$cache_key}", $found );
	if ( ! $found ) {
		$languages = wp_cache_get( $cache_key, $cache_group, false, $found );
		if ( $found ) {
			apcu_store( "{$cache_group}:{$cache_key}", $languages, $apcu_cache_time );
		}
	}

	if ( ! $found ) {
		$languages = $wpdb->get_col( $wpdb->prepare(
			"SELECT DISTINCT `language`
			FROM `language_packs`
			WHERE `type` = %s AND `domain` = %s AND `active` = 1",
			$type,
			$domain
		) );

		$languages = array_map( 'strtolower', $languages );

		apcu_store( "{$cache_group}:{$cache_key}", $languages, $apcu_cache_time );
		wp_cache_add( $cache_key, $languages, $cache_group, $cache_time );
	}

	return $languages ?: array();
}
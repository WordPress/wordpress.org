<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions\Routes;

use GP;
use GP_Route;
use WordPressdotorg\GlotPress\TranslationSuggestions\Translation_Memory_Client;
use const WordPressdotorg\GlotPress\TranslationSuggestions\PLUGIN_DIR;

class Other_Languages extends GP_Route {

	/**
	 * Indicates the related locales for each locale, so we will put them in the top list
	 * of "Other locales".
	 *
	 * This array should be sync with
	 * https://github.com/GlotPress/gp-translation-helpers/blob/main/helpers/helper-other-locales.php
	 *
	 * @var array
	 */
	public array $related_locales = array(
		'ca'  => array( 'ca-val', 'bal', 'es', 'oci', 'an', 'gl', 'fr', 'it', 'pt', 'ro', 'la' ),
		'es'  => array( 'gl', 'ca', 'pt', 'pt-ao', 'pt-br', 'it', 'fr', 'ro' ),
		'gl'  => array( 'es', 'pt', 'pt-ao', 'pt-br', 'ca', 'it', 'fr', 'ro' ),
		'it'  => array( 'ca', 'de', 'es', 'fr', 'pt', 'ro' ),
		'ne'  => array( 'hi', 'mr', 'as' ),
		'oci' => array( 'ca', 'fr', 'it', 'es', 'gl' ),
		'ug'  => array( 'tr', 'uz', 'az', 'zh-cn', 'zh-tw' ),
	);

	public function get_suggestions( $project_path, $locale_slug, $translation_set_slug ) {
		$original_id = gp_get( 'original' );
		$nonce       = gp_get( 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'other-languages-suggestions-' . $original_id ) ) {
			wp_send_json_error( 'invalid_nonce' );
		}

		if ( empty( $original_id ) ) {
			wp_send_json_error( 'no_original' );
		}

		$original = GP::$original->get( $original_id );
		if ( ! $original ) {
			wp_send_json_error( 'invalid_original' );
		}

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			wp_send_json_error( 'project_not_found' );
		}

		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );
		if ( ! $translation_set ) {
			wp_send_json_error( 'translation_set_found' );
		}

		$suggestions = $this->query( $original, $translation_set );

		wp_send_json_success( gp_tmpl_get_output( 'other-languages-suggestions', array( 'suggestions' => $suggestions ), PLUGIN_DIR . '/templates/' ) );
	}

	protected function query( $original, $set_to_exclude ) {
		global $wpdb;

		$sql = "
SELECT
	translation.id AS translation_id,
	translation.translation_0 AS translation,
	translation.status,
	translation.user_id,
	translation.date_added,
	translation_set.id AS translation_set_id,
	translation_set.locale,
	translation_set.slug
FROM {$wpdb->gp_translations} AS translation
	JOIN {$wpdb->gp_translation_sets} AS translation_set
		ON translation.translation_set_id = translation_set.id
WHERE
	translation.original_id = %d AND
	translation.translation_set_id != %d AND
	translation.status = 'current'
ORDER BY
	translation_set.locale LIKE %s DESC,
	translation_set.locale,
	translation_set.slug
";

		$results = $wpdb->get_results(
			$wpdb->prepare(
				$sql,
				$original->id,
				$set_to_exclude->id,
				$wpdb->esc_like( strtok( $set_to_exclude->locale, '-' ) ) . '%'
			),
			ARRAY_A
		);

		$results_with_preference = array();

		// Put the variants in the top list.
		foreach ( $results as $key => $result ) {
			if ( explode( '-', $result['locale'] )[0] === explode( '-', $set_to_exclude->locale )[0] ) {
				$results_with_preference[] = $result;
				unset( $results[ $key ] );
			}
		}

		if ( ! empty( $this->related_locales[ $set_to_exclude->locale ] ) ) {
			foreach ( $this->related_locales[ $set_to_exclude->locale ] as $locale ) {
				foreach ( $results as $key => $result ) {
					if ( $result['locale'] == $locale ) {
						$results_with_preference[] = $result;
						unset( $results[ $key ] );
						continue 2;
					}
				}
			}
		}

		return array_merge( $results_with_preference, $results );
	}
}

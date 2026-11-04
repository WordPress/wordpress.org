<?php
/**
 * Base class for routes that serve suggestions for a single original.
 *
 * @package WordPressdotorg\GlotPress\TranslationSuggestions
 */

declare( strict_types = 1 );

namespace WordPressdotorg\GlotPress\TranslationSuggestions\Routes;

use GP;
use GP_Original;
use GP_Route;
use GP_Translation_Set;

/**
 * Resolves the requested original and checks the caller may see it in the URL's translation set.
 */
abstract class Suggestions_Route extends GP_Route {

	/**
	 * Returns the requested original and the URL's translation set, or ends the request with a JSON error.
	 *
	 * The nonce only proves intent; this applies the same checks as the editor that issued it.
	 *
	 * @param string $nonce_action Nonce action prefix; the original ID is appended.
	 * @param string $project_path Project path from the URL.
	 * @param string $locale_slug  Locale slug from the URL.
	 * @param string $set_slug     Translation set slug from the URL.
	 * @return array{0: GP_Original, 1: GP_Translation_Set}
	 */
	protected function authorize_original( string $nonce_action, string $project_path, string $locale_slug, string $set_slug ): array {
		$original_id = gp_get( 'original' );
		$nonce       = gp_get( 'nonce' );

		if ( ! wp_verify_nonce( $nonce, $nonce_action . $original_id ) ) {
			wp_send_json_error( 'invalid_nonce' );
		}

		if ( empty( $original_id ) ) {
			wp_send_json_error( 'no_original' );
		}

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			wp_send_json_error( 'project_not_found' );
		}

		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $set_slug, $locale_slug );
		if ( ! $translation_set ) {
			wp_send_json_error( 'translation_set_not_found' );
		}

		if ( ! GP::$permission->current_user_can( 'edit', 'translation-set', $translation_set->id ) ) {
			wp_send_json_error( 'forbidden' );
		}

		$original = GP::$original->get( $original_id );

		// Same rule as GP_Translation::for_translation(): hidden originals are for project writers only.
		if (
			! $original ||
			(int) $original->project_id !== (int) $project->id ||
			( (int) $original->priority <= -2 && ! GP::$permission->current_user_can( 'write', 'project', $project->id ) )
		) {
			wp_send_json_error( 'invalid_original' );
		}

		return array( $original, $translation_set );
	}
}

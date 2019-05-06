<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions\Routes;

use GP;
use GP_Route;
use WordPressdotorg\GlotPress\TranslationSuggestions\Translation_Memory_Client;
use const WordPressdotorg\GlotPress\TranslationSuggestions\PLUGIN_DIR;

class Translation_Memory extends GP_Route {

	public function get_suggestions( $project_path, $locale_slug ) {
		$original_id = gp_get( 'original' );
		$nonce       = gp_get( 'nonce' );

		if ( ! wp_verify_nonce( $nonce, 'translation-memory-suggestions-' . $original_id ) ) {
			wp_send_json_error( 'invalid_nonce' );
		}

		if ( empty( $original_id ) ) {
			wp_send_json_error( 'no_original' );
		}

		$original = GP::$original->get( $original_id );
		if ( ! $original ) {
			wp_send_json_error( 'invalid_original' );
		}

		$suggestions = Translation_Memory_Client::query( $original->singular, $locale_slug );

		if ( is_wp_error( $suggestions ) ) {
			wp_send_json_error( $suggestions->get_error_code() );
		}

		wp_send_json_success( gp_tmpl_get_output( 'translation-memory-suggestions', [ 'suggestions' => $suggestions ], PLUGIN_DIR . '/templates/' ) );
	}
}

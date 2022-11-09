<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use GP;
use Text_Diff;
use WP_Error;
use WP_Http;
use WP_Text_Diff_Renderer_inline;

require_once ABSPATH . '/wp-includes/wp-diff.php';

class Translation_Memory_Client {

	const API_ENDPOINT = 'https://translate.wordpress.com/api/tm/';
	const API_BULK_ENDPOINT = 'https://translate.wordpress.com/api/tm/-bulk';

	/**
	 * Updates translation memory with new strings.
	 *
	 * @param array $translations  List of translation IDs, keyed by original ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update( array $translations ) {
		$requests = [];

		foreach ( $translations as $original_id => $translation_id ) {
			$translation = GP::$translation->get( $translation_id );

			// Check again in case the translation was changed.
			if ( 'current' !== $translation->status ) {
				continue;
			}

			$original        = GP::$original->get( $original_id );
			$translation_set = GP::$translation_set->get( $translation->translation_set_id );

			$locale = $translation_set->locale;
			if ( 'default' !== $translation_set->slug ) {
				$locale .= '_' . $translation_set->slug;
			}

			$requests[] = [
				'source'       => $original->fields(),
				'translations' => [
					[
						'singular' => $translation->translation_0,
						'plural'   => $translation->translation_1,
						'locale'   => $locale,
					],
				],
			];
		}

		if ( ! $requests ) {
			return new WP_Error( 'no_translations' );
		}

		$body = wp_json_encode( [
			'token'    => WPCOM_TM_TOKEN,
			'requests' => $requests,
		] );

		$request = wp_remote_post(
			self::API_BULK_ENDPOINT,
			[
				'timeout'    => 10,
				'user-agent' => 'WordPress.org Translate',
				'body'       => $body,
			]
		);

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		if ( WP_Http::OK !== wp_remote_retrieve_response_code( $request ) ) {
			return new WP_Error( 'response_code_not_ok' );
		}

		$body   = wp_remote_retrieve_body( $request );
		$result = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json_parse_error' );
		}

		return $result ?: new WP_Error( 'unknown_error' );
	}

	/**
	 * Queries translation memory for a string.
	 *
	 * @param string $text          Text to search translations for.
	 * @param string $target_locale Locale to search in.
	 * @return array|\WP_Error      List of suggestions on success, WP_Error on failure.
	 */
	public static function query( string $text, string $target_locale ) {
		if ( ! defined( 'WPCOM_TM_TOKEN' ) ) {
			return new WP_Error( 'no_token' );
		}

		$url = add_query_arg( urlencode_deep( [
			'text'   => $text,
			'target' => $target_locale,
			'token'  => WPCOM_TM_TOKEN,
			'ts'     => time(),
		] ), self::API_ENDPOINT );


		$request = wp_remote_get(
			$url,
			[
				'timeout'    => 4,
				'user-agent' => 'WordPress.org Translate',
			]
		);

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		if ( WP_Http::OK !== wp_remote_retrieve_response_code( $request ) ) {
			return new WP_Error( 'response_code_not_ok' );
		}

		$body   = wp_remote_retrieve_body( $request );
		$result = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error( 'json_parse_error' );
		}

		if ( empty( $result['matches'] ) ) {
			return [];
		}

		$suggestions = [];
		foreach ( $result['matches'] as $match ) {
			$suggestions[] = [
				'similarity_score' => $match['score'],
				'source'           => $match['source'],
				'translation'      => $match['text'],
				'diff'             => ( 1 === $match['score'] ) ? null : self::diff( $text, $match['source'] ),
			];
		}

		return $suggestions;
	}

	/**
	 * Generates the differences between two sequences of strings.
	 *
	 * @param string $previous_text Previous text.
	 * @param string $text          New text.
	 * @return string HTML markup for the differences between the two texts.
	 */
	protected static function diff( $previous_text, $text ) {
		$diff     = new  Text_Diff( 'auto', [ [ $text ], [ $previous_text ] ] );
		$renderer = new WP_Text_Diff_Renderer_inline();

		return $renderer->render( $diff );
	}
}

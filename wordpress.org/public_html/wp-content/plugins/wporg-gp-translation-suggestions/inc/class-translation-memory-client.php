<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use GP;
use GP_Locale;
use Text_Diff;
use WP_Error;
use WP_Http;
use WP_Text_Diff_Renderer_inline;

require_once ABSPATH . '/wp-includes/wp-diff.php';

class Translation_Memory_Client {

	const API_ENDPOINT      = 'https://translate.wordpress.com/api/tm/';
	const API_BULK_ENDPOINT = 'https://translate.wordpress.com/api/tm/-bulk';

	/**
	 * Updates translation memory with new strings.
	 *
	 * @param array $translations  List of translation IDs, keyed by original ID.
	 * @return true|\WP_Error True on success, WP_Error on failure.
	 */
	public static function update( array $translations ) {
		$requests = array();

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

			$requests[] = array(
				'source'       => $original->fields(),
				'translations' => array(
					array(
						'singular' => $translation->translation_0,
						'plural'   => $translation->translation_1,
						'locale'   => $locale,
					),
				),
			);
		}

		if ( ! $requests ) {
			return new WP_Error( 'no_translations' );
		}

		$body = wp_json_encode(
			array(
				'token'    => WPCOM_TM_TOKEN,
				'requests' => $requests,
			)
		);

		$request = wp_remote_post(
			self::API_BULK_ENDPOINT,
			array(
				'timeout'    => 10,
				'user-agent' => 'WordPress.org Translate',
				'body'       => $body,
			)
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
	 * @param string $text          Singular text to search translations for.
	 * @param string $text_plural   Plural text to search translations for.
	 * @param string $target_locale Locale to search in.
	 * @return array|\WP_Error      List of suggestions on success, WP_Error on failure.
	 */
	public static function query( string $text, string $text_plural, string $target_locale ) {
		if ( ! defined( 'WPCOM_TM_TOKEN' ) ) {
			return new WP_Error( 'no_token' );
		}

		$url = add_query_arg(
			urlencode_deep(
				array(
					'text'        => $text,
					'text_plural' => $text_plural,
					'target'      => $target_locale,
					'token'       => WPCOM_TM_TOKEN,
					'ts'          => time(),
				)
			),
			self::API_ENDPOINT
		);

		$request = wp_remote_get(
			$url,
			array(
				'timeout'    => 4,
				'user-agent' => 'WordPress.org Translate',
			)
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
			return array();
		}

		$suggestions = array();
		foreach ( $result['matches'] as $match ) {
			$suggestions[] = array(
				'similarity_score'   => $match['score'],
				'source'             => $match['source'],
				'source_plural'      => $match['source_plural'],
				'source_context'     => $match['source_context'],
				'translation'        => $match['text'],
				'translation_plural' => $match['text_plural'],
				'diff'               => ( 1 === $match['score'] ) ? null : self::diff( $text, $match['source'] ),
			);
		}

		return $suggestions;
	}

	/**
	 * Deletes a translation from translation memory.
	 *
	 * @param array  $source      Array with the original string (singular and plural) and the context.
	 * @param array  $translation Array with the translation (singular and plural).
	 * @param string $locale_slug Locale slug.
	 * @param string $set_slug    Translation set slug.
	 *
	 * @return bool
	 */
	public static function delete( array $source, array $translation, string $locale_slug, string $set_slug ):bool {
		$locale = $locale_slug;
		if ( 'default' !== $set_slug ) {
			$locale .= '_' . $set_slug;
		}
		$body    = wp_json_encode(
			array(
				'token'       => WPCOM_TM_TOKEN,
				'source'      => $source,
				'translation' => array(
					'singular' => $translation['translation'],
					'plural'   => $translation['translation_plural'],
					'locale'   => $locale,
				),
			)
		);
		$request = wp_remote_post(
			self::API_BULK_ENDPOINT,
			array(
				'method'     => 'DELETE',
				'timeout'    => 10,
				'user-agent' => 'WordPress.org Translate',
				'body'       => $body,
			)
		);
		if ( is_wp_error( $request ) ) {
			return false;
		}
		if ( WP_Http::OK !== wp_remote_retrieve_response_code( $request ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Generates the differences between two sequences of strings.
	 *
	 * @param string $previous_text Previous text.
	 * @param string $text          New text.
	 * @return string HTML markup for the differences between the two texts.
	 */
	protected static function diff( $previous_text, $text ) {
		$diff     = new Text_Diff( 'auto', array( array( $text ), array( $previous_text ) ) );
		$renderer = new WP_Text_Diff_Renderer_inline();

		return $renderer->render( $diff );
	}
}

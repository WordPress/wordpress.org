<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use GP;
use Text_Diff;
use WP_Error;
use WP_Http;
use WP_Text_Diff_Renderer_inline;

require_once ABSPATH . '/wp-includes/wp-diff.php';

class Translation_Memory_Client {

	const API_ENDPOINT      = 'https://translate.wordpress.com/api/tm/';
	const API_BULK_ENDPOINT = 'https://translate.wordpress.com/api/tm/-bulk';
	const BATCH_SIZE        = 500;

	/**
	 * Updates translation memory.
	 *
	 * Without args: claims a batch from the persistent queue.
	 * With args: processes those translation_ids directly (legacy payloads were
	 * keyed by original_id, so array_values handles both shapes).
	 *
	 * @param array|null $translations  Optional translation_ids to process.
	 * @return true|\WP_Error
	 */
	public static function update( $translations = null ) {
		if ( null === $translations ) {
			$translations = Plugin::with_lock( 'queue_lock', function () {
				$queue = Plugin::read_queue();
				if ( ! $queue ) {
					return array();
				}
				$batch = array_slice( $queue, 0, self::BATCH_SIZE, true );
				Plugin::write_queue( array_slice( $queue, count( $batch ), null, true ) );
				return array_keys( $batch );
			} );
		}

		if ( ! $translations ) {
			return true;
		}

		$translations = array_values( (array) $translations );

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::log( sprintf( 'Translation Memory: processing %d translation(s).', count( $translations ) ) );
		}

		return self::send_batch( $translations );
	}

	/**
	 * Sends a single batch of translations to the TM bulk endpoint.
	 *
	 * @param array $translation_ids
	 * @return true|\WP_Error
	 */
	protected static function send_batch( array $translation_ids ) {
		$requests = [];

		foreach ( $translation_ids as $translation_id ) {
			$translation = GP::$translation->get( (int) $translation_id );

			// Check again in case the translation was changed.
			if ( ! $translation || 'current' !== $translation->status ) {
				continue;
			}

			$original        = GP::$original->get( $translation->original_id );
			$translation_set = GP::$translation_set->get( $translation->translation_set_id );

			if ( ! $original || ! $translation_set ) {
				continue;
			}

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

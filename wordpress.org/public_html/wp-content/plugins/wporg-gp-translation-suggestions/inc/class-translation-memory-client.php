<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use Text_Diff;
use WP_Error;
use WP_Http;
use WP_Text_Diff_Renderer_inline;

require_once ABSPATH . '/wp-includes/wp-diff.php' ;

class Translation_Memory_Client {

	const API_ENDPOINT = 'https://translate.wordpress.com/api/tm/';

	/**
	 * Queries translation memory for a string.
	 *
	 * @param string $text          Text to search translations for.
	 * @param string $target_locale Locale to search in.
	 * @return array|\WP_Error      List of suggestions on success, WP_Error on failure.
	 */
	public static function query( string $text, string $target_locale ) {
		if ( ! defined ( 'WPCOM_TM_TOKEN' ) ) {
			return new WP_Error( 'no_token' );
		}

		$url = add_query_arg( urlencode_deep( [
			'text'   => $text,
			'target' => $target_locale,
			'token'  => WPCOM_TM_TOKEN,
		] ) , self::API_ENDPOINT );

		$request = wp_remote_get(
			$url,
			[
				'timeout'     => 2,
				'user-agent'  => 'WordPress.org Translate',
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
		$diff     = new  Text_Diff( 'auto', [ [ $previous_text ], [ $text ] ] );
		$renderer = new WP_Text_Diff_Renderer_inline();
		return $renderer->render( $diff );
	}
}

<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions\Routes;

use GP;
use GP_Locales;
use GP_Route;
use WordPressdotorg\GlotPress\TranslationSuggestions\Translation_Memory_Client;
use const WordPressdotorg\GlotPress\TranslationSuggestions\PLUGIN_DIR;

class Translation_Memory extends GP_Route {

	public function get_suggestions( $project_path, $locale_slug, $set_slug ) {
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

		$locale = $locale_slug;
		if ( 'default' !== $set_slug ) {
			$locale .= '_' . $set_slug;
		}

		$suggestions                     = Translation_Memory_Client::query( $original->singular, $locale );
		$current_set_slug                = 'default';
		$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $current_set_slug, $locale_slug );
		$locale_glossary                 = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );

		$openai_suggestions = $this->get_openai_suggestion( $original->singular, $locale, $locale_glossary );
		$deepl_suggestions  = $this->get_deepl_suggestion( $original->singular, $locale );
		if ( is_wp_error( $suggestions ) ) {
			wp_send_json_error( $suggestions->get_error_code() );
		}

		wp_send_json_success( gp_tmpl_get_output( 'translation-memory-suggestions', compact( 'suggestions', 'openai_suggestions', 'deepl_suggestions' ), PLUGIN_DIR . '/templates/' ) );
	}

	/**
	 * Get suggestions from OpenAI (ChatGPT).
	 *
	 * @param string       $original_singular The singular from the original string.
	 * @param string       $locale            The locale.
	 * @param \GP_Glossary $locale_glossary   The glossary for the locale.
	 *
	 * @return array
	 */
	private function get_openai_suggestion( $original_singular, $locale, $locale_glossary ): array {
		$openai_query    = '';
		$glossary_query  = '';
		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$openai_key      = gp_array_get( $gp_default_sort, 'openai_api_key' );
		if ( empty( trim( $openai_key ) ) ) {
			return array();
		}
		$openai_prompt      = gp_array_get( $gp_default_sort, 'openai_custom_prompt' );
		$openai_temperature = gp_array_get( $gp_default_sort, 'openai_temperature', 0 );
		if ( ! is_float( $openai_temperature ) || $openai_temperature < 0 || $openai_temperature > 2 ) {
			$openai_temperature = 0;
		}

		$glossary_entries = array();
		foreach ( $locale_glossary->get_entries() as $gp_glossary_entry ) {
			if ( strpos( strtolower( $original_singular ), strtolower( $gp_glossary_entry->term ) ) !== false ) {
				// Use the translation as key, because we could have multiple translations with the same term.
				$glossary_entries[ $gp_glossary_entry->translation ] = $gp_glossary_entry->term;
			}
		}
		if ( ! empty( $glossary_entries ) ) {
			$glossary_query = ' The following terms are translated as follows: ';
			foreach ( $glossary_entries as $translation => $term ) {
				$glossary_query .= '"' . $term . '" is translated as "' . $translation . '"';
				if ( array_key_last( $glossary_entries ) != $translation ) {
					$glossary_query .= ', ';
				}
			}
			$glossary_query .= '.';
		}

		$gp_locale     = GP_Locales::by_field( 'slug', $locale );
		$openai_query .= ' Translate the following text to ' . $gp_locale->english_name . ": \n";
		$openai_query .= '"' . $original_singular . '"';

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $openai_prompt . $glossary_query,
			),
			array(
				'role'    => 'user',
				'content' => $openai_query,
			),
		);

		$openai_response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $openai_key,
				),
				'body'    => wp_json_encode(
					array(
						'model'       => 'gpt-3.5-turbo',
						'max_tokens'  => 1000,
						'n'           => 1,
						'messages'    => $messages,
						'temperature' => $openai_temperature,
					)
				),
			)
		);
		if ( is_wp_error( $openai_response ) ) {
			return array();
		}
		$response_status = wp_remote_retrieve_response_code( $openai_response );
		if ( 200 !== $response_status ) {
			return array();
		}
		$output                            = json_decode( wp_remote_retrieve_body( $openai_response ), true );
		$message                           = $output['choices'][0]['message'];
		$response['openai']['translation'] = trim( trim( $message['content'] ), '"' );
		$response['openai']['diff']        = '';

		return $response;
	}

	/**
	 * Gets a translation suggestion from DeepL.
	 *
	 * @param string $original_singular The singular from the original string.
	 * @param string $locale            The locale.
	 *
	 * @return array
	 */
	private function get_deepl_suggestion( string $original_singular, string $locale ): array {
		$free_url        = 'https://api-free.deepl.com/v2/translate';
		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$deepl_api_key   = gp_array_get( $gp_default_sort, 'deepl_api_key' );
		if ( empty( trim( $deepl_api_key ) ) ) {
			return array();
		}
		$deepl_formality = gp_array_get( $gp_default_sort, 'deepl_formality', 'default' );
		$source_lang     = 'EN';
		$target_lang     = $this->get_deepl_locale( $locale );
		if ( empty( $target_lang ) ) {
			return array();
		}
		$deepl_response = wp_remote_post(
			$free_url,
			array(
				'timeout' => 20,
				'body'    => array(
					'auth_key'    => $deepl_api_key,
					'text'        => $original_singular,
					'source_lang' => $source_lang,
					'target_lang' => $target_lang,
					'formality'   => $deepl_formality,
				),
			),
		);
		if ( is_wp_error( $deepl_response ) ) {
			return array();
		} else {
			$body                             = wp_remote_retrieve_body( $deepl_response );
			$response['deepl']['translation'] = json_decode( $body )->translations[0]->text;
			$response['deepl']['diff']        = '';
			return $response;
		}
	}

	/**
	 * Gets the Deepl locale.
	 *
	 * @param string $locale The WordPress locale.
	 *
	 * @return string
	 */
	private function get_deepl_locale( string $locale ): string {
		$available_locales = array(
			'bg'    => 'BG',
			'cs'    => 'CS',
			'da'    => 'DA',
			'de'    => 'DE',
			'el'    => 'EL',
			'en-gb' => 'EN-GB',
			'es'    => 'ES',
			'et'    => 'ET',
			'fi'    => 'FI',
			'fr'    => 'FR',
			'hu'    => 'HU',
			'id'    => 'ID',
			'it'    => 'IT',
			'ja'    => 'JA',
			'ko'    => 'KO',
			'lt'    => 'LT',
			'lv'    => 'LV',
			'nb'    => 'NB',
			'nl'    => 'NL',
			'pl'    => 'PL',
			'pt'    => 'PT-PT',
			'pt-br' => 'PT-BR',
			'ro'    => 'RO',
			'ru'    => 'RU',
			'sk'    => 'SK',
			'sl'    => 'SL',
			'sv'    => 'SV',
			'tr'    => 'TR',
			'uk'    => 'UK',
			'zh-cn' => 'ZH',
		);
		if ( array_key_exists( $locale, $available_locales ) ) {
			return $available_locales[ $locale ];
		}
		return '';
	}
}


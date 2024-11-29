<?php
/**
 * This file contains the OpenAI pre-translation class.
 *
 * @package    WordPressdotorg\GlotPress\Bulk_Pretranslations
 * @author     WordPress.org
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 * @link       https://wordpress.org/
 */

namespace WordPressdotorg\GlotPress\Bulk_Pretranslations;

use GP;
use GP_Locale;
use GP_Translation_Set;

/**
 * OpenAI pre-translation class.
 */
class OpenAI extends Pretranslation {

	/**
	 * Tokens used by OpenAI.
	 *
	 * @var ?int
	 */
	protected ?int $tokens_used = null;

	/**
	 * Gets the suggestion for the translation from OpenAI.
	 *
	 * Only works for strings with no plural forms.
	 *
	 * @param int                $original_id     The original ID.
	 * @param GP_Locale          $locale          The locale.
	 * @param GP_Translation_Set $translation_set The translation set.
	 *
	 * @return false|string
	 */
	public function get_suggestion_0( int $original_id, GP_Locale $locale, GP_Translation_Set $translation_set ) {
		if ( ! $this->should_pretranslate( $original_id, $translation_set ) ) {
			return false;
		}
		$original = GP::$original->get( $original_id );

		$current_set_slug = 'default';

		$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $current_set_slug, $locale->slug );
		$locale_glossary                 = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );

		$openai_query    = '';
		$glossary_query  = '';
		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$openai_key      = gp_array_get( $gp_default_sort, 'openai_api_key' );
		if ( empty( trim( $openai_key ) ) ) {
			return false;
		}
		$openai_prompt      = gp_array_get( $gp_default_sort, 'openai_custom_prompt' );
		$openai_temperature = gp_array_get( $gp_default_sort, 'openai_temperature', 0 );
		if ( ! is_float( $openai_temperature ) || $openai_temperature < 0 || $openai_temperature > 2 ) {
			$openai_temperature = 0;
		}

		$glossary_entries = array();
		foreach ( $locale_glossary->get_entries() as $gp_glossary_entry ) {
			if ( strpos( strtolower( $original->singular ), strtolower( $gp_glossary_entry->term ) ) !== false ) {
				// Use the translation as key, because we could have multiple translations with the same term.
				$glossary_entries[ $gp_glossary_entry->translation ] = $gp_glossary_entry->term;
			}
		}
		if ( ! empty( $glossary_entries ) ) {
			$glossary_query = ' The following terms are translated as follows: ';
			foreach ( $glossary_entries as $translation => $term ) {
				$glossary_query .= '"' . $term . '" is translated as "' . $translation . '"';
				if ( array_key_last( $glossary_entries ) !== $translation ) {
					$glossary_query .= ', ';
				}
			}
			$glossary_query .= '.';
		}

		$openai_query .= ' Translate the following text to ' . $locale->english_name . ": \n";
		$openai_query .= '"' . $original->singular . '"';
		$openai_model  = gp_array_get( $gp_default_sort, 'openai_model', 'gpt-3.5-turbo' );

		$messages        = array(
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
						'model'       => $openai_model,
						'max_tokens'  => 1000,
						'n'           => 1,
						'messages'    => $messages,
						'temperature' => $openai_temperature,
					)
				),
			)
		);
		if ( is_wp_error( $openai_response ) ) {
			return false;
		}
		$response_status = wp_remote_retrieve_response_code( $openai_response );
		if ( 200 !== $response_status ) {
			return false;
		}
		$output             = json_decode( wp_remote_retrieve_body( $openai_response ), true );
		$message            = $output['choices'][0]['message'];
		$this->tokens_used  = $output['usage']['total_tokens'];
		$this->suggestion_0 = trim( trim( $message['content'] ), '"' );

		return $this->suggestion_0;
	}

	/**
	 * Updates the number of tokens used by OpenAI.
	 *
	 * @return bool
	 */
	public function update_openai_tokens_used(): bool {
		if ( is_null( $this->tokens_used ) ) {
			return false;
		}
		$gp_external_translations = get_user_option( 'gp_external_translations' );
		$openai_tokens_used       = gp_array_get( $gp_external_translations, 'openai_tokens_used' );
		if ( ! is_int( $openai_tokens_used ) || $openai_tokens_used < 0 ) {
			$openai_tokens_used = 0;
		}
		$openai_tokens_used                            += $this->tokens_used;
		$gp_external_translations['openai_tokens_used'] = $openai_tokens_used;
		update_user_option( get_current_user_id(), 'gp_external_translations', $gp_external_translations );

		return true;
	}
}

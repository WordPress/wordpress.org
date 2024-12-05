<?php
/**
 * This file contains the Deepl pre-translation class.
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
 * Deepl pre-translation class.
 */
class Deepl extends Pretranslation {

	/**
	 * Gets the suggestion for the translation from Deepl.
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

		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$deepl_api_key   = gp_array_get( $gp_default_sort, 'deepl_api_key' );
		$deepl_url_free  = 'https://api-free.deepl.com/v2/translate';
		$deepl_url_pro   = 'https://api.deepl.com/v2/translate';
		$deepl_url       = gp_array_get( $gp_default_sort, 'deepl_use_api_pro', false ) ? $deepl_url_pro : $deepl_url_free;
		if ( empty( trim( $deepl_api_key ) ) ) {
			return false;
		}
		$target_lang = $this->get_deepl_locale( $locale->slug );
		if ( empty( $target_lang ) ) {
			return false;
		}

		$deepl_response = wp_remote_post(
			$deepl_url,
			array(
				'timeout' => 20,
				'body'    => array(
					'auth_key'    => $deepl_api_key,
					'text'        => $original->singular,
					'source_lang' => 'EN',
					'target_lang' => $target_lang,
					'formality'   => $this->get_language_formality( $target_lang, $locale->slug ),
				),
			),
		);
		if ( is_wp_error( $deepl_response ) ) {
			return false;
		}
		$response_status = wp_remote_retrieve_response_code( $deepl_response );
		if ( 200 !== $response_status ) {
			return false;
		}
		$body = wp_remote_retrieve_body( $deepl_response );

		return json_decode( $body )->translations[0]->text;
	}

	/**
	 * Gets the Deepl locale.
	 *
	 * List of available languages https://developers.deepl.com/docs/resources/supported-languages#target-languages
	 *
	 * @param string $locale The WordPress locale.
	 *
	 * @return string
	 */
	public function get_deepl_locale( string $locale ): string {
		$available_locales = array(
			'ar'    => 'AR',
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

	/**
	 * Gets the formality of the language.
	 *
	 * @param string $locale   The locale.
	 * @param string $set_slug The set slug.
	 *
	 * @return string
	 */
	private function get_language_formality( string $locale, string $set_slug ): string {
		$lang_informality = array(
			'BG'    => 'prefer_more',
			'CS'    => 'prefer_less',
			'DA'    => 'prefer_less',
			'DE'    => 'prefer_less',
			'EL'    => 'prefer_more',
			'EN-GB' => 'prefer_less',
			'ES'    => 'prefer_less',
			'ET'    => 'prefer_less',
			'FI'    => 'prefer_less',
			'FR'    => 'prefer_more',
			'HU'    => 'prefer_more',
			'ID'    => 'prefer_more',
			'IT'    => 'prefer_less',
			'JA'    => 'prefer_more',
			'KO'    => 'prefer_less',
			'LT'    => 'prefer_more',
			'LV'    => 'prefer_less',
			'NB'    => 'prefer_less',
			'NL'    => 'prefer_less',
			'PL'    => 'prefer_less',
			'PT-BR' => 'prefer_less',
			'PT-PT' => 'prefer_more',
			'RO'    => 'prefer_less',
			'RU'    => 'prefer_more',
			'SK'    => 'prefer_less',
			'SL'    => 'prefer_less',
			'SV'    => 'prefer_less',
			'TR'    => 'prefer_less',
			'UK'    => 'prefer_more',
			'ZH'    => 'prefer_more',
		);

		if ( ( 'DE' === $locale || 'NL' === $locale ) && 'formal' === $set_slug ) {
			return 'prefer_more';
		}
		if ( array_key_exists( $locale, $lang_informality ) ) {
			return $lang_informality[ $locale ];
		}

		return 'default';
	}

	/**
	 * Updates the number of characters used by DeepL.
	 */
	public function update_deepl_chars_used() {
		$gp_external_translations = get_user_option( 'gp_external_translations' );
		$deepl_chars_used         = gp_array_get( $gp_external_translations, 'deepl_chars_used', 0 );
		if ( ! is_int( $deepl_chars_used ) || $deepl_chars_used < 0 ) {
			$deepl_chars_used = 0;
		}
		$deepl_chars_used                            += mb_strlen( $this->original->singular );
		$gp_external_translations['deepl_chars_used'] = $deepl_chars_used;
		update_user_option( get_current_user_id(), 'gp_external_translations', $gp_external_translations );
	}

}

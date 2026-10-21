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
use WordPressdotorg\GlotPress\Customizations\AI\OpenAI_Client;
use WordPressdotorg\GlotPress\Customizations\AI\OpenAI_Messages;

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

		$client = new OpenAI_Client();
		if ( ! $client->is_ready() ) {
			return false;
		}

		$original = GP::$original->get( $original_id );

		$current_set_slug = 'default';

		$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $current_set_slug, $locale->slug );
		$locale_glossary                 = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );

		$messages_builder = new OpenAI_Messages(
			$original->singular,
			$locale,
			$locale_glossary->get_entries()
		);
		$messages = $messages_builder->build_translation_messages();

		$result = $client->chat_completion( $messages );
		if ( false === $result ) {
			return false;
		}

		$this->tokens_used  = $result['usage']['total_tokens'] ?? 0;
		$this->suggestion_0 = $result['content'];

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

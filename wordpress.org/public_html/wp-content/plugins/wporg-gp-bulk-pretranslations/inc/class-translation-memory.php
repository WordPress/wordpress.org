<?php
/**
 * This file contains the translation memory pre-translation class.
 *
 * @package    WordPressdotorg\GlotPress\Bulk_Pretranslations
 * @author     WordPress.org
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 * @link       https://wordpress.org/
 */

namespace WordPressdotorg\GlotPress\Bulk_Pretranslations;

use GP_Locale;
use GP_Translation_Set;
use WordPressdotorg\GlotPress\TranslationSuggestions\Translation_Memory_Client;

/**
 * Translation memory pre-translation class.
 */
class Translation_Memory extends Pretranslation {

	/**
	 * Similarity threshold for the translation memory.
	 * If the similarity score is below this threshold, the suggestion is not used.
	 * Value between 0 and 1.
	 *
	 * @var float
	 */
	private $threshold = 1;

	/**
	 * Gets the suggestion for the translation from the translation memory.
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
		$suggestions = Translation_Memory_Client::query( $this->original->singular, $locale->slug );
		if ( empty( $suggestions ) ) {
			return false;
		}
		if ( is_wp_error( $suggestions ) ) {
			return false;
		}
		if ( $suggestions[0]['similarity_score'] < $this->threshold ) {
			return false;
		}
		return $suggestions[0]['translation'];
	}
}

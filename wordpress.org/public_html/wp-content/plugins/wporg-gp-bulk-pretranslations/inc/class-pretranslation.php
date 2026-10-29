<?php
/**
 * This file contains the pre-translation class, which is the base class for all pre-translation classes.
 *
 * @package    WordPressdotorg\GlotPress\Bulk_Pretranslations
 * @author     WordPress.org
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 * @link       https://wordpress.org/
 */

namespace WordPressdotorg\GlotPress\Bulk_Pretranslations;

use GP;
use GP_Locale;
use GP_Original;
use GP_Translation_Set;

/**
 * Pre-translation class.
 */
abstract class Pretranslation {

	/**
	 * The original object.
	 *
	 * @var GP_Original
	 */
	protected ?GP_Original $original = null;

	/**
	 * The suggestion for the translation (singular).
	 *
	 * @var string
	 */
	protected string $suggestion_0 = '';

	/**
	 * Gets the suggestion for the translation.
	 *
	 * Only works for strings with no plural forms.
	 *
	 * @param int                $original_id     The original ID.
	 * @param GP_Locale          $locale          The locale.
	 * @param GP_Translation_Set $translation_set The translation set.
	 *
	 * @return false|string
	 */
	abstract public function get_suggestion_0( int $original_id, GP_Locale $locale, GP_Translation_Set $translation_set );

	/**
	 * Check if a string should be pre-translated.
	 *
	 * @param int                $original_id     The original ID.
	 * @param GP_Translation_Set $translation_set The translation set.
	 *
	 * @return bool
	 */
	protected function should_pretranslate( int $original_id, GP_Translation_Set $translation_set ): bool {
		$this->original = GP::$original->get( $original_id );
		if ( ! $this->original ) {
			return false;
		}

		// We don't pre translate strings with plural forms.
		if ( ! is_null( $this->original->plural ) ) {
			return false;
		}

		// We don't pre translate a string with a translation in current, waiting, fuzzy or changesrequested status.
		$translations = GP::$translation->find(
			array(
				'original_id'        => $original_id,
				'translation_set_id' => $translation_set->id,
				'status'             => array( 'current', 'waiting', 'fuzzy', 'changesrequested' ),
			)
		);
		if ( ! empty( $translations ) ) {
			return false;
		}

		return true;
	}
}

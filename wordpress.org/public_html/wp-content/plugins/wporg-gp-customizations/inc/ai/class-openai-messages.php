<?php
/**
 * Centralized OpenAI Messages Builder for WordPress.org GlotPress
 *
 * This class provides a unified interface for building OpenAI API messages
 * across WordPress.org GlotPress plugins, handling glossary integration,
 * locale-specific formatting, and custom prompt management.
 *
 * @package    WordPressdotorg\GlotPress\Customizations\AI
 * @author     WordPress.org
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 * @link       https://wordpress.org/
 */

namespace WordPressdotorg\GlotPress\Customizations\AI;

use GP_Locale;

/**
 * OpenAI Messages Builder class.
 */
class OpenAI_Messages {

	/**
	 * Original text to translate.
	 *
	 * @var string
	 */
	private string $original_text;

	/**
	 * Target locale object.
	 *
	 * @var GP_Locale
	 */
	private GP_Locale $locale;

	/**
	 * Array of glossary entry objects.
	 *
	 * @var array
	 */
	private array $glossary_entries;

	/**
	 * Constructor.
	 *
	 * @param string    $original_text     The original text to translate.
	 * @param GP_Locale $locale            The target locale object.
	 * @param array     $glossary_entries  Array of glossary entry objects with 'term' and 'translation' properties.
	 */
	public function __construct( string $original_text, GP_Locale $locale, array $glossary_entries ) {
		$this->original_text    = $original_text;
		$this->locale           = $locale;
		$this->glossary_entries = $glossary_entries;
	}

	/**
	 * Build messages array for OpenAI chat completion.
	 *
	 * Creates a structured messages array suitable for OpenAI API calls,
	 * incorporating glossary terms, custom prompts, and locale-specific
	 * translation instructions.
	 *
	 * @return array Array of messages formatted for OpenAI API:
	 *               [
	 *                 ['role' => 'system', 'content' => string],
	 *                 ['role' => 'user', 'content' => string]
	 *               ]
	 */
	public function build_translation_messages(): array {
		$custom_prompt  = sanitize_text_field( $this->get_custom_prompt() );
		$glossary_query = sanitize_text_field( $this->build_glossary_query() );
		$user_query     = $this->build_user_query();

		$messages = array(
			array(
				'role'    => 'system',
				'content' => $custom_prompt . $glossary_query,
			),
			array(
				'role'    => 'user',
				'content' => $user_query,
			),
		);

		return $messages;
	}

	/**
	 * Get the custom prompt from user options.
	 *
	 * Retrieves the OpenAI custom prompt stored in the current user's
	 * GlotPress default sort settings.
	 *
	 * @return string The custom prompt, or empty string if not set.
	 */
	private function get_custom_prompt(): string {
		$user_options = get_user_option( 'gp_default_sort' );
		if ( ! is_array( $user_options ) ) {
			return '';
		}

		return gp_array_get( $user_options, 'openai_custom_prompt', '' );
	}

	/**
	 * Build glossary query string from glossary entries.
	 *
	 * Filters glossary entries that appear in the original text and formats
	 * them into a natural language instruction for the AI.
	 *
	 * @return string Formatted glossary instruction string, or empty string if no matches.
	 */
	private function build_glossary_query(): string {
		if ( empty( $this->glossary_entries ) ) {
			return '';
		}

		$relevant_entries = array();
		$original_lower   = strtolower( $this->original_text );

		foreach ( $this->glossary_entries as $entry ) {
			$term        = is_object( $entry ) ? $entry->term : $entry['term'];
			$translation = is_object( $entry ) ? $entry->translation : $entry['translation'];

			if ( strpos( $original_lower, strtolower( $term ) ) !== false ) {
				$relevant_entries[ $translation ] = $term;
			}
		}

		if ( empty( $relevant_entries ) ) {
			return '';
		}

		$glossary_query = ' The following terms are translated as follows: ';
		foreach ( $relevant_entries as $translation => $term ) {
			$glossary_query .= '"' . $term . '" is translated as "' . $translation . '"';
			if ( array_key_last( $relevant_entries ) !== $translation ) {
				$glossary_query .= ', ';
			}
		}
		$glossary_query .= '.';

		return $glossary_query;
	}

	/**
	 * Build user query string for translation request.
	 *
	 * Creates the user message that instructs the AI to translate
	 * the original text to the target locale.
	 *
	 * @return string Formatted translation request.
	 */
	private function build_user_query(): string {
		$query  = ' Translate the following text to ' . $this->locale->english_name . ": \n";
		$query .= '"' . $this->original_text . '"';

		return $query;
	}
}

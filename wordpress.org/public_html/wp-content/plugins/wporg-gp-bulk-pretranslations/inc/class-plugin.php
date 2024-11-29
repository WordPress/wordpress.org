<?php
/**
 * This file contains the main plugin file.
 *
 * @package    WordPressdotorg\GlotPress\Bulk_Pretranslations
 * @author     WordPress.org
 * @license    http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 * @link       https://wordpress.org/
 */

namespace WordPressdotorg\GlotPress\Bulk_Pretranslations;

use GP;
use GP_Locale;
use GP_Project;
use GP_Route;
use GP_Translation;
use GP_Translation_Set;

/**
 * Main plugin class.
 */
class Plugin extends GP_Route {

	/**
	 * The instance of the class.
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Get the instance of the class.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin constructor.
	 */
	public function __construct() {
		parent::__construct();
		add_action( 'gp_translation_set_bulk_action', array( $this, 'add_pretranslation_options' ) );
		add_action( 'gp_translation_set_bulk_action_post', array( $this, 'store_pretanslations' ), 10, 4 );
	}

	/**
	 * Add pre-translation options to the bulk actions dropdown.
	 *
	 * It adds it only for the GTE and translation memory, OpenAI and DeepL.
	 *
	 * @param GP_Translation_Set $translation_set The translation set.
	 */
	public function add_pretranslation_options( GP_Translation_Set $translation_set ):void {
		$can_approve = $this->can( 'approve', 'translation-set', $translation_set->id );
		if ( ! $can_approve ) {
			return;
		}

		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$openai_key      = gp_array_get( $gp_default_sort, 'openai_api_key', false );
		$deepl_api_key   = gp_array_get( $gp_default_sort, 'deepl_api_key', false );
		$locale          = $translation_set->locale;

		echo '<optgroup label="Pre translate selected rows with">';
		echo '<option value="bulk-pretranslation-tm">' . esc_html__( 'Translation Memory', 'glotpress' ) . '</option>';
		if ( $openai_key ) {
			echo '<option value="bulk-pretranslation-openai">' . esc_html__( 'OpenAI', 'glotpress' ) . '</option>';
		}
		$deepl = new DeepL();
		if ( $deepl_api_key && $deepl->get_deepl_locale( $locale ) ) {
			echo '<option value="bulk-pretranslation-deepl">' . esc_html__( 'DeepL', 'glotpress' ) . '</option>';
		}
		echo '</optgroup>';
	}

	/**
	 * Get the suggestions and store the pre-translations for the selected rows.
	 *
	 * @param GP_Project         $project          The project.
	 * @param GP_Locale          $locale           The locale.
	 * @param GP_Translation_Set $translation_set  The translation set.
	 * @param array              $bulk             The bulk action data.
	 */
	public function store_pretanslations( $project, $locale, $translation_set, $bulk ) {
		$affected_actions = array( 'bulk-pretranslation-tm', 'bulk-pretranslation-openai', 'bulk-pretranslation-deepl' );
		if ( ! in_array( $bulk['action'], $affected_actions, true ) ) {
			return;
		}

		$can_approve = $this->can( 'approve', 'translation-set', $translation_set->id );
		if ( ! $can_approve ) {
			return;
		}

		$current_user_id       = get_current_user_id();
		$pretranslations_added = 0;
		foreach ( $bulk['row-ids'] as $original_id ) {
			$translation_0 = null;
			if ( 'bulk-pretranslation-tm' === $bulk['action'] ) {
				$tm            = new Translation_Memory();
				$translation_0 = $tm->get_suggestion_0( $original_id, $locale, $translation_set );
			}
			if ( 'bulk-pretranslation-openai' === $bulk['action'] ) {
				$openai        = new OpenAI();
				$translation_0 = $openai->get_suggestion_0( $original_id, $locale, $translation_set );
				$openai->update_openai_tokens_used();
			}
			if ( 'bulk-pretranslation-deepl' === $bulk['action'] ) {
				$deepl         = new DeepL();
				$translation_0 = $deepl->get_suggestion_0( $original_id, $locale, $translation_set );
				$deepl->update_deepl_chars_used();
			}
			if ( $translation_0 ) {
				$translation_created = $this->store_pretranslation( $original_id, $translation_set->id, $translation_0, $current_user_id );
				if ( $translation_created ) {
					$pretranslations_added ++;
				}
			}
		}

		$this->set_notice( $pretranslations_added );
	}

	/**
	 * Store the pre-translation.
	 *
	 * @param int    $original_id        The original ID.
	 * @param int    $translation_set_id The translation set ID.
	 * @param string $translation_0      The translation.
	 * @param int    $current_user_id    The current user ID.
	 *
	 * @return false|GP_Translation
	 */
	private function store_pretranslation( int $original_id, int $translation_set_id, string $translation_0, int $current_user_id ) {
		return GP::$translation->create(
			array(
				'original_id'        => $original_id,
				'translation_set_id' => $translation_set_id,
				'translation_0'      => $translation_0,
				'status'             => 'waiting',
				'user_id'            => $current_user_id,
			)
		);
	}

	/**
	 * Set the notice with the number of pre-translations added.
	 *
	 * @param int $pretranslations_added The number of pre-translations added.
	 *
	 * @return void
	 */
	private function set_notice( int $pretranslations_added ):void {
		$notice = sprintf(
		/* translators: %s: Pretranslations count. */
			_n( '%s pretranslation was added.', '%s pretranslations were added.', $pretranslations_added, 'wporg-gp-bulk-pretranslations' ),
			$pretranslations_added
		);
		if ( $pretranslations_added > 0 ) {
			$current_url = str_replace( '-bulk/', '', gp_url_current() );
			$waiting_url = add_query_arg( 'filters[status]', 'waiting', $current_url );

			$notice .= '<br>';
			$notice .= wp_kses(
				sprintf(
				_n( "You can see it in <a href=\"%s\">waiting</a> status.",
					"You can see them in <a href=\"%s\">waiting</a> status.",
					$pretranslations_added,
					'wporg-gp-bulk-pretranslations' ),
				$waiting_url,
			),
				array( 'a' => array( 'href' => array() ) )
			);
		}
		gp_notice_set( $notice );
	}
}

<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use GP;
use GP_Locales;
use WordPressdotorg\GlotPress\TranslationSuggestions\Routes\Translation_Memory;

class Plugin {

	const TM_UPDATE_EVENT = 'wporg_translate_tm_update';

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * @var array
	 */
	private $queue = array();

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		add_action( 'template_redirect', array( $this, 'register_routes' ), 5 );
		add_action( 'gp_pre_tmpl_load', array( $this, 'pre_tmpl_load' ), 10, 2 );
		add_action( 'wporg_translate_suggestions', array( $this, 'extend_translation_suggestions' ) );

		if ( 'cli' !== PHP_SAPI ) {
			add_action( 'gp_translation_created', array( $this, 'translation_updated' ), 3 );
			add_action( 'gp_translation_saved', array( $this, 'translation_updated' ), 3 );

			// DB Writes are delayed until shutdown to bulk-update the stats during imports.
			add_action( 'shutdown', array( $this, 'schedule_tm_update' ), 3 );
		}

		add_action( self::TM_UPDATE_EVENT, array( Translation_Memory_Client::class, 'update' ) );
		add_action( 'gp_translation_created', array( Translation_Memory::class, 'update_external_translations' ) );
	}

	/**
	 * Adds a translation in queue when a translation was created
	 * or updated.
	 *
	 * @param \GP_Translation $translation Created/updated translation.
	 */
	public function translation_updated( $translation ) {
		if ( ! $translation->user_id || 'current' !== $translation->status ) {
			return;
		}

		$this->queue[ $translation->original_id ] = $translation->id;
	}

	/**
	 * Schedules a single event to update translation memory for new translations.
	 */
	public function schedule_tm_update() {
		remove_action( 'gp_translation_created', array( $this, 'translation_updated' ), 3 );
		remove_action( 'gp_translation_saved', array( $this, 'translation_updated' ), 3 );

		if ( ! $this->queue ) {
			return;
		}

		wp_schedule_single_event( time() + 60, self::TM_UPDATE_EVENT, array( 'translations' => $this->queue ) );
	}

	/**
	 * Registers custom routes.
	 */
	public function register_routes() {
		$dir      = '([^_/][^/]*)';
		$path     = '(.+?)';
		$projects = 'projects';
		$project  = $projects . '/' . $path;
		$locale   = '(' . implode( '|', wp_list_pluck( GP_Locales::locales(), 'slug' ) ) . ')';
		$set      = "$project/$locale/$dir";

		GP::$router->prepend( "/$set/-get-tm-suggestions", array( __NAMESPACE__ . '\Routes\Translation_Memory', 'get_suggestions' ) );
		GP::$router->prepend( "/$set/-get-other-language-suggestions", array( __NAMESPACE__ . '\Routes\Other_Languages', 'get_suggestions' ) );
		GP::$router->prepend( "/$set/-get-tm-openai-suggestions", array( __NAMESPACE__ . '\Routes\Translation_Memory', 'get_openai_suggestions' ) );
		GP::$router->prepend( "/$set/-get-tm-deepl-suggestions", array( __NAMESPACE__ . '\Routes\Translation_Memory', 'get_deepl_suggestions' ) );
	}

	/**
	 * Enqueue custom styles and scripts.
	 */
	public function pre_tmpl_load( $template, $args ) {
		if ( 'translations' !== $template || ! isset( $args['translation_set']->id ) || ! GP::$permission->current_user_can( 'edit', 'translation-set', $args['translation_set']->id ) ) {
			return;
		}

		wp_register_style(
			'gp-translation-suggestions',
			plugins_url( 'css/translation-suggestions.css', PLUGIN_FILE ),
			array(),
			'20220401'
		);
		gp_enqueue_style( 'gp-translation-suggestions' );

		wp_register_script(
			'gp-translation-suggestions',
			plugins_url( './js/translation-suggestions.js', PLUGIN_FILE ),
			array( 'gp-editor' ),
			filemtime( plugin_dir_path( __FILE__ ) . '/../js/translation-suggestions.js' )
		);

		$gp_default_sort         = get_user_option( 'gp_default_sort' );
		$get_openai_translations = ! empty( trim( gp_array_get( $gp_default_sort, 'openai_api_key' ) ) );
		$get_deepl_translations  = ! empty( trim( gp_array_get( $gp_default_sort, 'deepl_api_key' ) ) );

		wp_localize_script(
			'gp-translation-suggestions',
			'gpTranslationSuggestions',
			array(
				'nonce'                     => wp_create_nonce( 'gp-translation-suggestions' ),
				'get_external_translations' => array(
					'get_openai_translations' => $get_openai_translations,
					'get_deepl_translations'  => $get_deepl_translations,
				),
			)
		);

		gp_enqueue_script( 'gp-translation-suggestions' );

		wp_add_inline_script(
			'gp-translation-suggestions',
			sprintf(
				"window.WPORG_TRANSLATION_MEMORY_API_URL = %s;\nwindow.WPORG_TRANSLATION_MEMORY_OPENAI_API_URL = %s;\nwindow.WPORG_TRANSLATION_MEMORY_DEEPL_API_URL = %s;\nwindow.WPORG_OTHER_LANGUAGES_API_URL = %s;",
				wp_json_encode( gp_url_project( $args['project'], gp_url_join( $args['locale_slug'], $args['translation_set_slug'], '-get-tm-suggestions' ) ) ),
				wp_json_encode( gp_url_project( $args['project'], gp_url_join( $args['locale_slug'], $args['translation_set_slug'], '-get-tm-openai-suggestions' ) ) ),
				wp_json_encode( gp_url_project( $args['project'], gp_url_join( $args['locale_slug'], $args['translation_set_slug'], '-get-tm-deepl-suggestions' ) ) ),
				wp_json_encode( gp_url_project( $args['project'], gp_url_join( $args['locale_slug'], $args['translation_set_slug'], '-get-other-language-suggestions' ) ) )
			)
		);
	}

	/**
	 * Extends the suggestions container for Translation Memory and
	 * Other Languages.
	 *
	 * @param object $entry Current translation row entry.
	 */
	public function extend_translation_suggestions( $entry ) {
		if ( ! isset( $entry->translation_set_id ) || ! GP::$permission->current_user_can( 'edit', 'translation-set', $entry->translation_set_id ) ) {
			return;
		}

		// Prevent querying the TM for long strings which usually time out
		// and have no results due to being too unique.
		$query_tm = mb_strlen( $entry->singular ) <= 420;

		// Used to add a link to add the OpenAI and the DeepL keys.
		// We only show this link if the user has not added any of these keys yet.
		$gp_default_sort = get_user_option( 'gp_default_sort' );
		$openai_key      = trim( gp_array_get( $gp_default_sort, 'openai_api_key' ) );
		$deepl_key       = trim( gp_array_get( $gp_default_sort, 'deepl_api_key' ) );
		?>
		<details open class="suggestions__translation-memory<?php echo $query_tm ? '' : ' initialized'; ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'translation-memory-suggestions-' . $entry->original_id ) ); ?>">
			<summary>Suggestions from Translation Memory</summary>
			<?php if ( $query_tm ) : ?>
				<p class="suggestions__loading-indicator">Loading <span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span></p>
			<?php else : ?>
				<p class="no-suggestions">No suggestions.</p>
			<?php endif; ?>

			<?php if ( empty( $openai_key ) && empty( $deepl_key ) ) : ?>
				<p class="translation-suggestion__footer_message__for_suggestions">Get translation suggestions from <a href="https://translate.wordpress.org/settings/" target="_blank">OpenAI</a>  and from <a href="https://translate.wordpress.org/settings/" target="_blank">DeepL</a>. <a href="https://make.wordpress.org/polyglots/2023/03/29/adding-chatgpt-and-deepl-in-the-translation-memory/" target="_blank">More info</a>.</p>
			<?php endif; ?>
		</details>

		<details class="suggestions__other-languages" data-nonce="<?php echo esc_attr( wp_create_nonce( 'other-languages-suggestions-' . $entry->original_id ) ); ?>">
			<summary>Other Languages</summary>
			<p class="suggestions__loading-indicator">Loading <span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span></p>
		</details>
		<?php
	}
}

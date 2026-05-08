<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use GP;
use GP_Locales;
use WordPressdotorg\GlotPress\TranslationSuggestions\Routes\Translation_Memory;
use WordPressdotorg\GlotPress\Bulk_Pretranslations\Deepl;

class Plugin {

	const TM_UPDATE_EVENT = 'wporg_translate_tm_update';
	const TM_QUEUE_OPTION = 'wporg_translate_tm_queue';
	const TM_UPDATE_DELAY = 60;

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Translation IDs queued during the current request.
	 *
	 * Keyed by translation_id for dedup; values are ignored.
	 *
	 * @var array<int, true>
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

		// Key by translation_id so distinct translations of the same original
		// (e.g. different locales, plural forms) are all queued.
		$this->queue[ (int) $translation->id ] = true;
	}

	/**
	 * Merges the request's queued translations into the persistent queue and
	 * ensures a single cron event is scheduled to process it.
	 */
	public function schedule_tm_update() {
		remove_action( 'gp_translation_created', array( $this, 'translation_updated' ), 3 );
		remove_action( 'gp_translation_saved', array( $this, 'translation_updated' ), 3 );

		if ( ! $this->queue ) {
			return;
		}

		$new_ids = $this->queue;
		$this->queue = array();

		$merged = self::with_lock( 'queue_lock', function () use ( $new_ids ) {
			self::write_queue( $new_ids + self::read_queue() );
			return true;
		} );

		// If the lock couldn't be acquired, fall back to a per-request cron
		// so the translations aren't dropped.
		if ( ! $merged ) {
			wp_schedule_single_event(
				time() + self::TM_UPDATE_DELAY,
				self::TM_UPDATE_EVENT,
				array( 'translations' => array_keys( $new_ids ) )
			);
			return;
		}

		if ( ! wp_next_scheduled( self::TM_UPDATE_EVENT ) ) {
			wp_schedule_single_event( time() + self::TM_UPDATE_DELAY, self::TM_UPDATE_EVENT );
		}
	}

	/**
	 * Reads the persistent TM update queue.
	 *
	 * @return array<int, true> Set of translation_ids (value always true).
	 */
	public static function read_queue() {
		$queue = get_option( self::TM_QUEUE_OPTION, array() );
		return is_array( $queue ) ? $queue : array();
	}

	/**
	 * Writes (or deletes, when empty) the persistent TM update queue.
	 *
	 * @param array<int, true> $queue Set of translation_ids.
	 */
	public static function write_queue( array $queue ) {
		if ( ! $queue ) {
			delete_option( self::TM_QUEUE_OPTION );
			return;
		}
		update_option( self::TM_QUEUE_OPTION, $queue, false );
	}

	/**
	 * Runs $callback while holding a memcached-backed lock.
	 *
	 * wp_cache_add maps to memcached ADD — atomic. Single attempt; returns
	 * false without running the callback if the lock is held.
	 *
	 * @param string   $name     Lock name.
	 * @param callable $callback
	 * @return mixed The callback's return value, or false if the lock was not acquired.
	 */
	public static function with_lock( $name, callable $callback ) {
		if ( ! wp_cache_add( $name, 1, 'locks', 30 ) ) {
			return false;
		}

		try {
			return $callback();
		} finally {
			wp_cache_delete( $name, 'locks' );
		}
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
		$deepl 				     = new DeepL();
		$deepl_locale 		     = $deepl->get_deepl_locale( $args['locale_slug'] );
		
		wp_localize_script(
			'gp-translation-suggestions',
			'gpTranslationSuggestions',
			array(
				'nonce'                     => wp_create_nonce( 'gp-translation-suggestions' ),
				'get_external_translations' => array(
					'get_openai_translations' => $get_openai_translations,
					'get_deepl_translations'  => $get_deepl_translations,
					'get_deepl_locale'        => $deepl_locale,
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

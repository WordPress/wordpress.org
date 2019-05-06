<?php

namespace WordPressdotorg\GlotPress\TranslationSuggestions;

use GP;
use GP_Locales;
use Text_Diff;
use WP_Error;
use WP_Http;
use WP_Text_Diff_Renderer_inline;

require_once ABSPATH . '/wp-includes/wp-diff.php' ;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

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
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		add_action( 'template_redirect', [ $this, 'register_routes' ], 5 );
		add_action( 'gp_pre_tmpl_load', [ $this, 'pre_tmpl_load' ], 10, 2 );
		add_action( 'wporg_translate_suggestions', [ $this, 'extend_translation_suggestions' ] );
	}

	/**
	 * Registers custom routes.
	 */
	public function register_routes() {
		$dir = '([^_/][^/]*)';
		$path = '(.+?)';
		$projects = 'projects';
		$project = $projects . '/' . $path;
		$locale = '(' . implode( '|', wp_list_pluck( GP_Locales::locales(), 'slug' ) ) . ')';
		$set = "$project/$locale/$dir";

		GP::$router->prepend( "/$set/-get-tm-suggestions", [ __NAMESPACE__ . '\Routes\Translation_Memory', 'get_suggestions' ] );
		GP::$router->prepend( "/$set/-get-other-language-suggestions", [ __NAMESPACE__ . '\Routes\Other_Languages', 'get_suggestions' ] );
	}

	/**
	 * Enqueue custom styles and scripts.
	 */
	public function pre_tmpl_load( $template, $args ) {
		if ( 'translations' !== $template || ! is_user_logged_in() ) {
			return;
		}

		wp_register_style(
			'gp-translation-suggestions',
			plugins_url( 'css/translation-suggestions.css', PLUGIN_FILE ),
			[],
			'20190506'
		);
		gp_enqueue_style( 'gp-translation-suggestions' );

		wp_register_script(
			'gp-translation-suggestions',
			plugins_url( './js/translation-suggestions.js', PLUGIN_FILE ),
			[ 'gp-editor' ],
			'20190506'
		);

		gp_enqueue_script( 'gp-translation-suggestions' );

		wp_add_inline_script(
			'gp-translation-suggestions',
			sprintf(
				"window.WPORG_TRANSLATION_MEMORY_API_URL = %s;\nwindow.WPORG_OTHER_LANGUAGES_API_URL = %s;",
				wp_json_encode( gp_url_project( $args['project'], gp_url_join( $args['locale_slug'], $args['translation_set_slug'], '-get-tm-suggestions' ) ) ),
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
		if ( ! is_user_logged_in() ) {
			return;
		}

		?>
		<details open class="suggestions__translation-memory" data-nonce="<?php echo esc_attr( wp_create_nonce( 'translation-memory-suggestions-' . $entry->original_id ) ); ?>">
			<summary>Suggestions from Translation Memory</summary>
			<p class="suggestions__loading-indicator">Loading <span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span></p>
		</details>

		<details class="suggestions__other-languages" data-nonce="<?php echo esc_attr( wp_create_nonce( 'other-languages-suggestions-' . $entry->original_id ) ); ?>">
			<summary>Other Languages</summary>
			<p class="suggestions__loading-indicator">Loading <span aria-hidden="true" class="suggestions__loading-indicator__icon"><span></span><span></span><span></span></span></p>
		</details>
		<?php
	}
}

<?php

namespace WordPressdotorg\GlotPress\Discussion;

use GP;
use GP_Locales;

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
		// Temporarily hide for public while in development.
		if ( ! is_caped() ) {
			return;
		}

		add_action( 'wporg_translate_meta', [ $this, 'show_discussion_meta' ] );
	}

	/**
	 * Adds discussion items.
	 *
	 * @param object $entry Current translation row entry.
	 */
	public function show_discussion_meta( $entry ) {
		$set     = GP::$translation_set->get( $entry->translation_set_id );
		$blog_id = $this->get_blog_id($set->locale  );
		if ( ! $blog_id ) {
			return;
		}

		$rest_url = get_rest_url( $blog_id );


		echo '📝';
	}

	/**
	 * Returns the blog ID of a locale.
	 *
	 * @param string $locale_slug Slug of GlotPress locale.
	 * @return int Blog ID on success, 0 on failure.
	 */
	public function get_blog_id( $locale_slug ) {
		$gp_locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $gp_locale || ! isset( $gp_locale->wp_locale ) ) {
			return false;
		}

		$wp_locale = $gp_locale->wp_locale;

		$result = get_sites( [
			'network_id' => get_current_network_id(),
			'path'       => '/support/',
			'number'     => 1,
			'locale'    => $wp_locale,
		] );
		$site = array_shift( $result );

		return $site ? (int) $site->blog_id : 0;
	}
}


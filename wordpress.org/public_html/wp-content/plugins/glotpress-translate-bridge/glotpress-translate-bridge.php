<?php
/*
 * Plugin Name: GlotPress Translate Bridge
 * Description: This plugin allows for a code to translate arbitrary strings from a GlotPress instance for the current locale.
 * Version: 0.1
 * Plugin URI: https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/glotpress-translate-bridge/
 * Author: wordpressdotorg
 * Author URI: http://wordpress.org/
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 */

/**
 * The GlotPress Translate Bridge is designed to allow the translation of single strings from a GlotPress instance.
 * This has been written for WordPress.org, so assumes a few things about the environment.
 * - It assumes that GlotPress is using the 'gp_' prefix, unless GLOTPRESS_TABLE_PREFIX is defined.
 * - It assumes that $wpdb has access to the GlotPress tables.
 * - It assumes that you have an object cache enabled (but works without it too).
 * - It assumes that you want to use the current locale.
 * - It assumes you're using the WordPress Locale format.
 *
 * An example of how to use this plugin is:
 *  $translated = GlotPress_Translate_Bridge::translate( "Hello World", "wp/dev" );
 *
 */
class GlotPress_Translate_Bridge {
	static $instance = null;

	private $gp_prefix = 'gp_';
	private $cache_group = 'glotpress-translate-bridge'; // __construct() marks this as global.
	private $cache_duration = 21600; // 6 * HOUR_IN_SECONDS;

	/**
	 * Translate a single string.
	 *
	 * @param $singular     The string to translate.
	 * @param $project_path The GlotPress project path.
	 * @param $context      The strings context. Default: null.
	 *
	 * @return string The translated string if it exists, else, the existing string.
	 */
	static function translate( $singular, $project_path, $context = null ) {
		$t = self::instance();

		$translation = $t->find_translation( compact( 'singular', 'context' ), $project_path );

		return $translation ? $translation[0] : $singular;
	}

	/**
	 * Translate a pluralised string. This does not support the `$count` parameter of `_n()`.
	 *
	 * @param $singular     The singular form of the string.
	 * @param $plural       The plural form of the string.
	 * @param $project_path The GlotPress project path.
	 * @param $context      The strings context. Default: null
	 *
	 * @return array The translated plural forms of the string.
	 */
	static function translate_plural( $singular, $plural, $project_path, $context = null ) {
		$t = self::instance();

		$translation = $t->find_translation( compact( 'singular', 'plural', 'context' ), $project_path );

		return $translation ?: array( $singular, $plural );
	}

	/**
	 * Retrieves the instance of the GlotPress Translate Bridge.
	 *
	 * @return object
	 */
	public function instance() {
		if ( is_null( self::$instance ) ) {
			$class = __CLASS__;
			self::$instance = new $class;
		}
		return self::$instance;
	}

	/**
	 * Basic configurations for the plugin, sets the cache group to a global.
	 *
	 * @access private
	 */
	private function __construct() {
		wp_cache_add_global_groups( array( $this->cache_group ) );
		if ( defined( 'GLOTPRESS_TABLE_PREFIX' ) ) {
			$this->gp_prefix = GLOTPRESS_TABLE_PREFIX;
		}
	}

	/**
	 * Retrieves the translations for a string from the GlotPress database.
	 * Checks the Object cache first.
	 *
	 * @param array $strings {
	 *	@type string $singular The singular form of the string.
	 *	@type string $plural   The plural form of the string.
	 *	@type string $context  The context of the string
	 * }
	 * @param string $project_path The path of the GlotPress project.
	 *
	 * @access private
	 *
	 * @return array|false An array of translated string, false if none found.
	 */
	private function find_translation( array $strings, $project_path ) {
		global $wpdb;

		$locale = $this->glotpress_locale();
		if ( ! $locale ) {
			return false;
		}

		$cache_key = $this->cache_key( $strings, $project_path );
		$cache_not_found = '__NONE__';

		if ( false !== ( $cache = wp_cache_get( $cache_key, $this->cache_group ) ) ) {
			if ( $cache === $cache_not_found ) {
				return false;
			}
			return $cache;
		}

		$sql_project  = $wpdb->prepare( "p.path = %s", $project_path );
		$sql_singular = isset( $strings['singular'] ) ? $wpdb->prepare( "o.singular = %s", $strings['singular'] ) : 'o.singular IS NULL';
		$sql_plural   = isset( $strings['plural'] )   ? $wpdb->prepare( "o.plural = %s",   $strings['plural']   ) : 'o.plural IS NULL';
		$sql_context  = isset( $strings['context'] )  ? $wpdb->prepare( "o.contxt = %s",   $strings['context']  ) : 'o.context IS NULL';

		$sql_locale = $wpdb->prepare( "s.locale = %s AND s.slug = %s", $locale['locale'], $locale['slug'] );

		$translation = $wpdb->get_row(
			"SELECT t.translation_0, t.translation_1, t.translation_2, t.translation_3, t.translation_4, t.translation_5
				FROM {$this->gp_prefix}projects p
				LEFT JOIN {$this->gp_prefix}originals o ON p.id = o.project_id
				LEFT JOIN {$this->gp_prefix}translation_sets s ON p.id = s.project_id
				LEFT JOIN {$this->gp_prefix}translations t ON t.original_id = o.id AND t.translation_set_id = s.id

			WHERE
				$sql_project AND $sql_singular AND $sql_plural AND $sql_context AND $sql_locale
				AND o.status = '+active'
				AND t.status = 'current'

			ORDER BY t.date_modified DESC
			LIMIT 1",
			ARRAY_N
		);

		if ( $translation ) {
			$translation = array_filter( $translation );
			wp_cache_set( $cache_key, $translation, $this->cache_group, $this->cache_duration );
		} else {
			wp_cache_set( $cache_key, $cache_not_found, $this->cache_group, $this->cache_duration );
		}

		return $translation;
	}

	/**
	 * Determines the GlotPress Locale for a given WordPress Locale
	 *
	 * Uses `GP_Locales` if 'GLOTPRESS_LOCALES_PATH' is defined. Otherwise it makes assumptions about the format of a GlotPress slug,
	 * which may not reflect real-world use-cases.
	 *
	 * @access private
	 *
	 * @return array|false False if the current loale is English (US), an array of the Locale and slug of the GlotPress translation set otherwise.
	 */
	private function glotpress_locale() {
		$wp_locale = get_locale();

		if ( 'en_US' === $wp_locale ) {
			return false;
		}

		preg_match( '!^([a-z]{2,3})(_([A-Z]{2}))?(_([a-z0-9]+))?$!', $wp_locale, $matches );

		$wp_locale = $matches[1] . $matches[2];
		$locale = $matches[1];
		$country = isset( $matches[3] ) ? $matches[3] : $matches[1];
		$variant = isset( $matches[5] ) ? $matches[5] : false;

		if ( defined( 'GLOTPRESS_LOCALES_PATH' ) ) {
			require_once( GLOTPRESS_LOCALES_PATH );

			$gp_locale = GP_Locales::by_field( 'wp_locale', $wp_locale );
			if ( $gp_locale ) {
				$locale = $gp_locale->slug;
			}
		} else {
			$country = strtolower( $country );
			if ( $locale !== $country ) {
				$locale .= '-' . $country;
			}
		}

		$slug = $variant ?: 'default';

		return compact( 'locale', 'slug' );
	}

	/**
	 * Generates a unique cache key for the given strings & project.
	 * The cache is based on the current locale, the project path, and the non-blank strings.
	 *
	 * @access private
	 *
	 * @return string The cache key.
	 */
	private function cache_key( $strings, $project_path ) {
		return strtolower( get_locale() ) . ':' . $project_path . ':' . serialize( array_filter( $strings ) );
	}
}

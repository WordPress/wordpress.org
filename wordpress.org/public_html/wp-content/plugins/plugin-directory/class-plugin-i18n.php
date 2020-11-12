<?php
namespace WordPressdotorg\Plugin_Directory;

use GP_Locales;
use WP_Http;

/**
 * Translation for plugin content.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_I18n {

	/**
	 * @var string Global cache group for translations.
	 */
	const CACHE_GROUP = 'plugins-i18n';

	/**
	 * @var int When to expire the cache contents.
	 */
	const CACHE_EXPIRE = 3 * DAY_IN_SECONDS;

	/**
	 * Project slug of the parent project.
	 *
	 * @link https://translate.wordpress.org/projects/wp-plugins
	 *
	 * @var string
	 */
	public $master_project = 'wp-plugins';

	/**
	 * @static
	 *
	 * @var bool
	 */
	public static $use_cache = true;

	/**
	 * @static
	 *
	 * @var bool
	 */
	public static $set_cache = true;

	/**
	 * Fetch the instance of the Plugin_I18n class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Plugin_I18n constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		wp_cache_add_global_groups( self::CACHE_GROUP );
	}

	/**
	 * Generates and returns a standard cache key format, for consistency.
	 *
	 * @param string $slug   Plugin slug
	 * @param string $branch dev|stable
	 * @param string $suffix Optional. Arbitrary cache key suffix, if needed for uniqueness.
	 * @return string Cache key
	 */
	public function cache_key( $slug, $branch, $suffix = '' ) {

		/*
		 * EG keys
		 * plugin:press-this:stable-readme:originals
		 * plugin:press-this:stable-readme:original:title
		 * plugin:press-this:stable-readme:fr:title
		 */
		$key = "{$this->master_project}:{$slug}:{$branch}";
		if ( ! empty( $suffix ) ) {
			$key .= ":{$suffix}";
		}

		return $key;
	}

	/**
	 * Cache getting, with proper global cache group.
	 *
	 * @param string $slug   Plugin slug
	 * @param string $branch dev|stable
	 * @param string $suffix Optional. Arbitrary cache key suffix, if needed for uniqueness.
	 * @return bool|mixed As returned by wp_cache_set()
	 */
	public function cache_get( $slug, $branch, $suffix = '' ) {
		if ( ! self::$use_cache ) {
			return false;
		}

		$key = $this->cache_key( $slug, $branch, $suffix );

		return wp_cache_get( $key, self::CACHE_GROUP );
	}

	/**
	 * Cache setting, with proper global cache group.
	 *
	 * @param string $slug    Plugin slug
	 * @param string $branch  dev|stable
	 * @param mixed  $content Content to be cached.
	 * @param string $suffix  Optional. Arbitrary cache key suffix, if needed for uniqueness.
	 * @return bool As returned by wp_cache_set()
	 */
	public function cache_set( $slug, $branch, $content, $suffix = '' ) {
		if ( ! self::$set_cache ) {
			return false;
		}

		$key = $this->cache_key( $slug, $branch, $suffix );

		return wp_cache_set( $key, $content, self::CACHE_GROUP, self::CACHE_EXPIRE );
	}

	/**
	 * Gets a GlotPress branch ID.
	 *
	 * @param string $slug   Plugin slug
	 * @param string $branch dev|stable
	 *
	 * @return bool|int|mixed
	 */
	public function get_gp_branch_id( $slug, $branch ) {
		global $wpdb;

		$cache_suffix = 'branch_id';

		if ( false !== ( $branch_id = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			return $branch_id;
		}

		$branch_id = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . GLOTPRESS_TABLE_PREFIX . 'projects WHERE path = %s',
			"wp-plugins/{$slug}/{$branch}"
		) );

		if ( empty( $branch_id ) ) {
			$branch_id = 0;
		}

		$this->cache_set( $slug, $branch, $branch_id, $cache_suffix );

		return $branch_id;
	}

	/**
	 * Gets GlotPress "originals" based on passed parameters.
	 *
	 * @param string $slug   Plugin slug
	 * @param string $branch dev|stable
	 * @param string $key    Unique key
	 * @param string $str    String to match in GP
	 * @return array|bool|mixed|null
	 */
	public function get_gp_originals( $slug, $branch, $key, $str ) {
		global $wpdb;

		$cache_suffix = 'originals';

		if ( false !== ( $originals = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			if ( $originals && is_object( $originals[ array_keys($originals)[0] ] ) ) {
				$originals = array_column( $originals, 'singular', 'id' );
			}

			return $originals;
		}

		$branch_id = $this->get_gp_branch_id( $slug, $branch );

		if ( empty( $branch_id ) ) {
			return array();
		}

		$originals = $wpdb->get_results( $wpdb->prepare(
			'SELECT id, singular FROM ' . GLOTPRESS_TABLE_PREFIX . 'originals WHERE project_id = %d AND status = %s ORDER BY CHAR_LENGTH(singular) DESC',
			$branch_id, '+active'
		), ARRAY_A );

		$originals = array_column( $originals, 'singular', 'id' );

		$this->cache_set( $slug, $branch, $originals, $cache_suffix );

		return $originals;
	}

	/**
	 * Get GlotPress translation set ID based on passed params.
	 *
	 * @param string $slug   Plugin slug
	 * @param string $branch dev|stable
	 * @param string $locale EG: fr
	 * @return bool|int|mixed
	 */
	public function get_gp_translation_set_id( $slug, $branch, $locale ) {
		global $wpdb;

		$cache_suffix = "{$locale}:translation_set_id";

		if ( false !== ( $translation_set_id = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			return $translation_set_id;
		}

		$branch_id = $this->get_gp_branch_id( $slug, $branch );

		if ( empty( $branch_id ) ) {
			return 0;
		}

		// The slug is optional, use 'default' as .. default.
		list( $locale, $locale_slug ) = array_merge( explode( '/', $locale ), [ 'default' ] );

		$translation_set_id = $wpdb->get_var( $wpdb->prepare(
			'SELECT id FROM ' . GLOTPRESS_TABLE_PREFIX . 'translation_sets WHERE project_id = %d AND locale = %s AND slug = %s',
			$branch_id,
			$locale,
			$locale_slug
		) );

		if ( empty( $translation_set_id ) ) {

			// Don't give up yet. Might be given fr_FR, which actually exists as locale=fr in GP.
			$translation_set_id = $wpdb->get_var( $wpdb->prepare(
				'SELECT id FROM ' . GLOTPRESS_TABLE_PREFIX . 'translation_sets WHERE project_id = %d AND locale = %s AND slug = "default"',
				$branch_id, preg_replace( '/^([^-]+)(-.+)?$/', '\1', $locale )
			) );
		}

		if ( empty( $translation_set_id ) ) {
			$translation_set_id = 0;
		}

		$this->cache_set( $slug, $branch, $translation_set_id, $cache_suffix );

		return $translation_set_id;
	}

	/**
	 * Returns GlotPress translations for the passed original string IDs.
	 *
	 * @param string $slug               Plugin slug
	 * @param string $branch             dev|stable
	 * @param array  $originals          List of IDs of the original strings.
	 * @param int    $translation_set_id Unique ID for translation set.
	 * @return array Plugin translations
	 */
	public function get_gp_translations( $slug, $branch, $originals, $translation_set_id ) {
		global $wpdb;

		$cache_suffix = "translations:{$translation_set_id}";
		$translations = $this->cache_get( $slug, $branch, $cache_suffix );

		if ( false === $translations ) {
			$translations = [];

			$raw_translations = $wpdb->get_results( $wpdb->prepare(
				'SELECT original_id, translation_0 FROM ' . GLOTPRESS_TABLE_PREFIX . 'translations WHERE original_id IN (' . implode( ', ', array_keys( $originals ) ) . ') AND translation_set_id = %d AND status = %s',
				$translation_set_id, 'current'
			) );

			foreach ( $raw_translations as $translation ) {
				$translations[ $translation->original_id ] = $translation->translation_0;
			}

			$this->cache_set( $slug, $branch, $translations, $cache_suffix );
		}

		return $translations;
	}

	/**
	 * Somewhat emulated equivalent of __() for content translation drawn directly from the GlotPress DB.
	 *
	 * @param string $key     Unique key, used for caching
	 * @param string $content Content to be translated
	 * @param array  $args    Optional. Misc arguments, such as BBPress topic id
	 *                        (otherwise acquired from global $topic_id).
	 * @return string
	 */
	public function translate( $key, $content, $args = array() ) {
		global $wpdb;

		if ( empty( $key ) || empty( $content ) ) {
			return $content;
		}

		$args = wp_parse_args( $args, [
			'post_id' => null,
			'locale'  => '',
		] );

		$post = get_post( $args['post_id'] );

		if ( ! $post ) {
			return $content;
		}

		if ( ! empty( $args['locale'] ) ) {
			$wp_locale = $args['locale'];
		} else {
			$wp_locale = get_locale();
		}

		if ( ! $wp_locale || 'en_US' == $wp_locale ) {
			return $content;
		}

		require_once GLOTPRESS_LOCALES_PATH;
		$gp_locale = GP_Locales::by_field( 'wp_locale', $wp_locale );

		if ( ! $gp_locale || 'en' === $gp_locale->slug ) {
			return $content;
		}

		// The slug is the locale of a translation set.
		$locale = $gp_locale->slug;
		$slug   = $post->post_name;

		$post->stable_tag = get_post_meta( $post->ID, 'stable_tag', true ) ?: 'trunk';

		if ( empty( $slug ) ) {
			return $content;
		}

		$branch = ( empty( $post->stable_tag ) || 'trunk' === $post->stable_tag ) ? 'dev' : 'stable';

		if ( empty( $args['code_i18n'] ) || true !== $args['code_i18n'] ) {
			$branch .= '-readme';
		}

		$originals = $this->get_gp_originals( $slug, $branch, $key, $content );

		if ( empty( $originals ) ) {
			return $content;
		}

		$translation_set_id = $this->get_gp_translation_set_id( $slug, $branch, $locale );

		if ( empty( $translation_set_id ) ) {
			return $content;
		}

		$translations = $this->get_gp_translations( $slug, $branch, $originals, $translation_set_id );

		// Sort the originals so as to process long strings first.
		uasort( $originals, function( $a, $b ) {
			$a_len = strlen( $a );
			$b_len = strlen( $b );

			return $a_len == $b_len ? 0 : ($a_len > $b_len ? -1 : 1);
		} );

		// Mark each original for translation
		foreach ( $originals as $original_id => $original ) {
			if ( isset( $translations[ $original_id ] ) ) {
				$content = $this->mark_gp_original( $original_id, $original, $content );
			}
		}

		// Translate the marked originals.
		$content = $this->translate_marked_gp_originals( $content, $translations, $originals );

		return $content;
	}

	/**
	 * Takes content, searches for $original, and replaces it with a marker for later translation.
	 *
	 * @param string $original_id Original English Translation ID.
	 * @param string $original    Original English String.
	 * @param string $content     Content to be searched.
	 * @return mixed
	 */
	public function mark_gp_original( $original_id, $original, $content ) {
		$marker = "___TRANSLATION_{$original_id}___";

		if ( $original === $content ) {
			$content = $marker;
		} else {
			$original = preg_quote( $original, '/' );

			if ( false === strpos( $content, '<' ) ) {
				$content = preg_replace( "/\b{$original}\b/", $marker, $content );
			} else {
				$content = preg_replace( "/(<([a-z0-9]*)\b[^>]*>){$original}(<\/\\2>)/m", "\${1}{$marker}\${3}", $content );
			}
		}

		return $content;
	}

	/**
	 * Translates marked translations $content from ::mark_gp_original().
	 *
	 * @param string $content      The Content to be searched.
	 * @param array  $translations The Translations.
	 * @param array  $originals    The Originals.
	 * @return string
	 */
	public function translate_marked_gp_originals( $content, $translations, $originals ) {
		return preg_replace_callback(
			'/___TRANSLATION_(\d+)___/',
			function( $m ) use( $translations, $originals ) {
				$marker = $m[0];
				$id     = $m[1];

				// The translation by ID, Original by ID, or the marker if it was never actually marking a original translation.
				$translation = $translations[ $id ] ?? ( $originals[ $id ] ?? $marker );

				// Run the gettext filter for simpler compat with translation plugins.
				return apply_filters( 'gettext', $translation, $originals[ $id ], 'dynamic-plugin-i18n' /* fake textdomain*/ );
			},
			$content
		);
	}

	/**
	 * Returns a list of translation locales for a given plugin slug and branch.
	 *
	 * @param string $slug        Plugin slug.
	 * @param string $branch      Branch - 'stable-readme' for example.
	 * @param int    $min_percent Optional. Only return locales where percent_translated is >= this value.
	 * @return array
	 */
	public function find_all_translations_for_plugin( $slug, $branch, $min_percent = 0 ) {
		$post = Plugin_Directory::get_plugin_post( $slug );

		return wp_filter_object_list( $this->get_locales( $post, $branch, $min_percent ), null, null, 'wp_locale' );
	}

	/**
	 * Returns a list of locale objects for a given plugin slug and branch.
	 *
	 * @param int|\WP_Post|null $post        Optional. Post ID or post object. Defaults to global $post.
	 * @param string            $branch      Optional. Branch - 'stable-readme' for example. Default: 'stable'.
	 * @param int               $min_percent Optional. Only return locales where percent_translated is >= this value.
	 *                                       Default: 95.
	 * @return array
	 */
	public function get_locales( $post = null, $branch = 'stable', $min_percent = 95 ) {
		$post = get_post( $post );

		$cache_suffix = 'translation_sets';

		$translation_sets = $this->cache_get( $post->post_name, $branch, $cache_suffix );
		if ( false === $translation_sets ) {
			$api_url  = esc_url_raw( 'https://translate.wordpress.org/api/projects/wp-plugins/' . $post->post_name . '/' . $branch, [ 'https' ] );
			$response = wp_remote_get( $api_url );

			if ( is_wp_error( $response ) || WP_Http::OK !== wp_remote_retrieve_response_code( $response ) ) {
				$translation_sets = [];
			} else {
				$result           = json_decode( wp_remote_retrieve_body( $response ) );
				$translation_sets = isset( $result->translation_sets ) ? $result->translation_sets : [];
			}

			$this->cache_set( $post->post_name, $branch, $translation_sets, $cache_suffix );
		}

		$locales = array_filter( $translation_sets, function( $locale ) use ( $min_percent ) {
			return $locale->percent_translated >= $min_percent;
		} );

		return $locales;
	}

	/**
	 * Returns a list of locale objects for available language packs.
	 *
	 * @param string $plugin_slug Slug of a plugin.
	 * @return array List of locale objects.
	 */
	public function get_translations( $plugin_slug ) {
		global $wpdb;

		require_once GLOTPRESS_LOCALES_PATH;

		// Get the active language packs of the plugin.
		$locales = wp_cache_get( $plugin_slug, 'language-pack-locales' );
		if ( $locales === false ) {
			$locales = $wpdb->get_col( $wpdb->prepare( "
				SELECT `language`
				FROM language_packs
				WHERE
					type = 'plugin' AND
					domain = %s AND
					active = 1
				GROUP BY `language`",
				$plugin_slug
			) );

			wp_cache_set( $plugin_slug, $locales, 'language-pack-locales', HOUR_IN_SECONDS );
		}

		$translations = [];

		foreach ( $locales as $locale ) {
			$gp_locale = GP_Locales::by_field( 'wp_locale', $locale );
			if ( ! $gp_locale ) {
				continue;
			}

			$translations[] = (object) [
				'name'      => $gp_locale->english_name,
				'wp_locale' => $locale,
			];
		}

		return $translations;
	}
}

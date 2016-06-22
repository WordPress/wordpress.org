<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Translation for plugin content.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_I18n {

	/**
	 * @var string Global cache group for related caching
	 */
	public $i18n_cache_group = 'plugins-i18n';

	/**
	 * @var string
	 */
	public $master_project;

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
	 * @access protected
	 *
	 * @var \wpdb WordPress database abstraction object.
	 */
	protected $db;

	/**
	 * Fetch the instance of the Plugin_I18n class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		global $wpdb;

		return ! is_null( $instance ) ? $instance : $instance = new Plugin_I18n( $wpdb );
	}

	/**
	 * Plugin_I18n constructor.
	 *
	 * @access private
	 *
	 * @param \wpdb $db WordPress database abstraction object.
	 * @param null $tracker
	 */
	private function __construct( $db, $tracker = null ) {
		if ( ! empty( $db ) && is_object( $db ) ) {
			$this->db = $db;
		}

		wp_cache_add_global_groups( $this->i18n_cache_group );
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

		return wp_cache_get( $key, $this->i18n_cache_group );
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

		return wp_cache_set( $key, $content, $this->i18n_cache_group );
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
		$cache_suffix = "branch_id";

		if ( false !== ( $branch_id = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			return $branch_id;
		}

		$branch_id = $this->db->get_var( $this->db->prepare(
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

		// Try to get a single original with the whole content first (title, etc), if passed, or get them all otherwise.
		if ( ! empty( $key ) && ! empty( $str ) ) {
			$originals = $this->search_gp_original( $slug, $branch, $key, $str );

			// Do not cache this as originals, search_gp_original() does its own caching.
			if ( ! empty( $originals ) ) {
				return array( $originals );
			}
		}

		$cache_suffix = 'originals';

		if ( false !== ( $originals = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			return $originals;
		}

		$branch_id = $this->get_gp_branch_id( $slug, $branch );

		if ( empty( $branch_id ) ) {
			return array();
		}

		$originals = $this->db->get_results( $this->db->prepare(
			'SELECT id, singular, comment FROM ' . GLOTPRESS_TABLE_PREFIX . 'originals WHERE project_id = %d AND status = %s ORDER BY CHAR_LENGTH(singular) DESC',
			$branch_id, '+active'
		) );

		if ( empty( $originals ) ) {

			// Still cache if empty, but as array, never false.
			$originals = array();
		}

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
		$cache_suffix = "{$locale}:translation_set_id";

		if ( false !== ( $translation_set_id = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			return $translation_set_id;
		}

		$branch_id = $this->get_gp_branch_id( $slug, $branch );

		if ( empty( $branch_id ) ) {
			return 0;
		}

		$translation_set_id = $this->db->get_var( $this->db->prepare(
			'SELECT id FROM ' . GLOTPRESS_TABLE_PREFIX . 'translation_sets WHERE project_id = %d AND locale = %s',
			$branch_id, $locale ) );

		if ( empty( $translation_set_id ) ) {

			// Don't give up yet. Might be given fr_FR, which actually exists as locale=fr in GP.
			$translation_set_id = $this->db->get_var( $this->db->prepare(
				'SELECT id FROM ' . GLOTPRESS_TABLE_PREFIX . 'translation_sets WHERE project_id = %d AND locale = %s',
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
	 * Searches GlotPress "originals" for the passed string.
	 *
	 * @param string $slug   Plugin slug
	 * @param string $branch dev|stable
	 * @param string $key    Unique key
	 * @param string $str    String to be searched for
	 * @return bool|mixed|null
	 */
	public function search_gp_original( $slug, $branch, $key, $str ) {
		$cache_suffix = "original:{$key}";

		if ( false !== ( $original = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			return $original;
		}

		$branch_id = $this->get_gp_branch_id( $slug, $branch );

		if ( empty( $branch_id ) ) {
			return false;
		}

		$original = $this->db->get_row( $this->db->prepare(
			'SELECT id, singular, comment FROM ' . GLOTPRESS_TABLE_PREFIX . 'originals WHERE project_id = %d AND status = %s AND singular = %s',
			$branch_id, '+active', $str
		) );

		if ( empty( $original ) ) {
			$original = null;
		}

		$this->cache_set( $slug, $branch, $original, $cache_suffix );

		return $original;
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

		$server_name = strtolower( $_SERVER['SERVER_NAME'] );
		if ( 'api.wordpress.org' == $server_name ) {

			// Support formats like fr, haz, and en_GB.
			if ( ! empty( $_REQUEST['locale'] ) ) {
				$wp_locale = preg_replace( '/[^a-zA-Z_]/', '', $_REQUEST['locale'] );
			} else if ( ! empty( $_REQUEST['request'] ) ) {
				$request = maybe_unserialize( $_REQUEST['request'] );
				if ( ! empty( $request ) && ! empty( $request->locale ) ) {
					$wp_locale = preg_replace( '/[^a-zA-Z_]/', '', $request->locale );
				}
			}
		}

		if ( ! $wp_locale ) {
			return $content;
		}

		require_once GLOTPRESS_LOCALES_PATH;
		$gp_locale = \GP_Locales::by_field( 'wp_locale', $wp_locale );

		if ( ! $gp_locale || 'en' === $gp_locale->slug ) {
			return $content;
		}

		// The slug is the locale of a translation set.
		$locale = $gp_locale->slug;
		$slug   = $post->post_name;

		$post->stable_tag = get_post_meta( $post->ID, 'stable_tag', true );

		if ( empty( $slug ) ) {
			return $content;
		}

		$branch = ( empty( $post->stable_tag ) || 'trunk' === $post->stable_tag ) ? 'dev' : 'stable';

		if ( empty( $args['code_i18n'] ) || true !== $args['code_i18n'] ) {
			$branch .= '-readme';
		}

		$cache_suffix = "{$locale}:{$key}";

		// Try the cache.
		if ( false !== ( $cache = $this->cache_get( $slug, $branch, $cache_suffix ) ) ) {
			// DEBUG
			// var_dump( array( $slug, $branch, $cache_suffix, $cache ) );
			return $cache;
		}

		$originals = $this->get_gp_originals( $slug, $branch, $key, $content );

		if ( empty( $originals ) ) {
			return $content;
		}

		$translation_set_id = $this->get_gp_translation_set_id( $slug, $branch, $locale );

		if ( empty( $translation_set_id ) ) {
			return $content;
		}

		foreach ( $originals as $original ) {
			if ( empty( $original->id ) ) {
				continue;
			}

			$translation = $this->db->get_var( $this->db->prepare(
				'SELECT translation_0 FROM ' . GLOTPRESS_TABLE_PREFIX . 'translations WHERE original_id = %d AND translation_set_id = %d AND status = %s',
				$original->id, $translation_set_id, 'current'
			) );

			if ( empty( $translation ) ) {
				continue;
			}

			$content = $this->translate_gp_original( $original->singular, $translation, $content );
		}

		$this->cache_set( $slug, $branch, $content, $cache_suffix );

		return $content;
	}

	/**
	 * Takes content, searches for $original, and replaces it by $translation.
	 *
	 * @param string $original    English string.
	 * @param string $translation Translation.
	 * @param string $content     Content to be searched.
	 * @return mixed
	 */
	public function translate_gp_original( $original, $translation, $content ) {
		if ( false === strpos( $content, '<' ) ) {
			$content = str_replace( $original, $translation, $content );
		} else {
			$original = preg_quote( $original, '/' );
			$content  = preg_replace( "/(<([a-z0-9]*)\b[^>]*>){$original}(<\/\\2>)/m", "\\1{$translation}\\3", $content );
		}

		return $content;
	}

	/**
	 * Returns a list of translation locales for a given plugin slug and branch.
	 *
	 * @param string $slug    Plugin slug.
	 * @param string $branch  Branch - 'stable-readme' for example.
	 * @param string $min_percent     Only return locales where percent_translated is >= this value.
	 * @return array
	 */
	public function find_all_translations_for_plugin( $slug, $branch, $min_percent = 0 ) {

		// This naively hits the API. It could probably be re-written to query the DB instead.
		$api_url = esc_url_raw( 'https://translate.wordpress.org/api/projects/wp-plugins/' . $slug . '/' . $branch, array( 'https' ) );

		$http = new \WP_Http();
		$result = $http->request( $api_url );

		$out = array();
		if ( !is_wp_error( $result ) ) {
			$json = $result['body'];
			if ( $data = json_decode( $json ) ) {
				if ( isset( $data->translation_sets ) ) {
					foreach ( $data->translation_sets as $translation ) {
						if ( $translation->percent_translated >= $min_percent )
							$out[] = $translation->wp_locale;
					}
				}
			}
		}

		return $out;
	}

}

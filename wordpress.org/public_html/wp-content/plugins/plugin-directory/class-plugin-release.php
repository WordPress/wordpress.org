<?php
/**
 * Plugin release CPT storage.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory;

/**
 * Storage and compatibility layer for plugin release records.
 *
 * Releases used to be stored as one `releases` postmeta array on the plugin
 * post. This keeps the public Plugin_Directory::get_release(s)/add_release()
 * array contract intact while moving the backing storage to child CPT records.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Release {

	const POST_TYPE       = 'plugin_release';
	const DATA_META_KEY   = 'release_data';
	const BACKFILLED_META = '_releases_cpt_backfilled';

	/**
	 * Fetch the instance of the Plugin_Release class.
	 *
	 * @return Plugin_Release
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Plugin_Release();
	}

	/**
	 * Plugin_Release constructor.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the private release CPT.
	 */
	public function register_post_type() {
		if ( post_type_exists( self::POST_TYPE ) ) {
			return;
		}

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'          => __( 'Releases', 'wporg-plugins' ),
					'singular_name' => __( 'Release', 'wporg-plugins' ),
				),
				'public'              => false,
				'show_ui'             => false,
				'exclude_from_search' => true,
				'publicly_queryable'  => false,
				'show_in_rest'        => false,
				'supports'            => array( 'title', 'custom-fields' ),
				'rewrite'             => false,
				'query_var'           => false,
				'hierarchical'        => false,
				'delete_with_user'    => false,
			)
		);
	}

	/**
	 * Ensure release CPT queries and writes can run before `init` in CLI contexts.
	 */
	private function ensure_post_type() {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			$this->register_post_type();
		}
	}

	/**
	 * Get all releases for a plugin as legacy release arrays.
	 *
	 * @param string|\WP_Post $plugin Plugin slug or post object.
	 * @return array
	 */
	public function get_releases( $plugin ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return array();
		}

		$release_posts = $this->get_release_posts( $plugin );

		$releases = array_map(
			function ( $release_post ) use ( $plugin ) {
				return $this->post_to_release_data( $release_post, $plugin );
			},
			$release_posts
		);

		uasort(
			$releases,
			function ( $a, $b ) {
				return $b['date'] <=> $a['date'];
			}
		);

		return array_values( $releases );
	}

	/**
	 * Check if a plugin has any CPT release records.
	 *
	 * @param string|\WP_Post $plugin Plugin slug or post object.
	 * @return bool
	 */
	public function has_releases( $plugin ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin );
		return $plugin && (bool) $this->get_release_posts( $plugin, 1 );
	}

	/**
	 * Backfill release CPTs from legacy release metadata, tags metadata, or SVN.
	 *
	 * This is intended to be driven by the one-off migration script
	 * (bin/backfill-release-cpts.php) rather than run lazily on reads or writes.
	 *
	 * @param string|\WP_Post $plugin Plugin slug or post object.
	 * @param bool            $force  Whether to run even if CPT releases exist.
	 * @return array|false|\WP_Error Backfilled release arrays, false when skipped.
	 */
	public function maybe_backfill_releases( $plugin, $force = false ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return new \WP_Error( 'invalid_plugin', 'Invalid plugin' );
		}

		if ( ! $force ) {
			if ( $this->has_releases( $plugin ) ) {
				return false;
			}

			if ( get_post_meta( $plugin->ID, self::BACKFILLED_META, true ) ) {
				return false;
			}
		}

		$legacy_releases = get_post_meta( $plugin->ID, 'releases', true );
		if ( is_array( $legacy_releases ) ) {
			$releases = $legacy_releases;
		} else {
			$releases = $this->get_prefill_releases( $plugin );
		}

		foreach ( $releases as $release ) {
			$this->add_release( $plugin, $release );
		}

		update_post_meta( $plugin->ID, self::BACKFILLED_META, time() );

		return $releases;
	}

	/**
	 * Get prefill release data from old tags metadata or SVN tags.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return array
	 */
	private function get_prefill_releases( $plugin ) {
		$releases = array();
		$tags     = get_post_meta( $plugin->ID, 'tags', true );

		if ( $tags ) {
			foreach ( $tags as $tag_version => $tag ) {
				$releases[] = array(
					'date'                   => strtotime( $tag['date'] ),
					'tag'                    => $tag['tag'],
					'version'                => $tag_version,
					'committer'              => array( $tag['author'] ),
					'zips_built'             => true,
					'confirmations_required' => 0,
				);
			}

			return $releases;
		}

		$svn_tags = Tools\SVN::ls( "https://plugins.svn.wordpress.org/{$plugin->post_name}/tags/", true );
		$svn_tags = $svn_tags ? $svn_tags : array();
		foreach ( $svn_tags as $entry ) {
			if ( 'dir' !== $entry['kind'] ) {
				continue;
			}

			$tag = $entry['filename'];
			if ( '.' === substr( $tag, 0, 1 ) ) {
				$tag = "0{$tag}";
			}

			$releases[] = array(
				'date'                   => strtotime( $entry['date'] ),
				'tag'                    => $entry['filename'],
				'version'                => $tag,
				'committer'              => array( $entry['author'] ),
				'zips_built'             => true,
				'confirmations_required' => 0,
			);
		}

		return $releases;
	}

	/**
	 * Fetch a specific release of the plugin, by tag.
	 *
	 * @param string|\WP_Post $plugin Plugin slug or post object.
	 * @param string          $tag    Plugin version / release tag.
	 * @return array|bool
	 */
	public function get_release( $plugin, $tag ) {
		$releases = $this->get_releases( $plugin );

		$filtered = wp_list_filter( $releases, compact( 'tag' ) );
		if ( $filtered ) {
			return array_shift( $filtered );
		}

		$filtered = wp_list_filter(
			$releases,
			array(
				'tag'     => "trunk@{$tag}",
				'version' => $tag,
			)
		);
		if ( $filtered ) {
			return array_shift( $filtered );
		}

		return false;
	}

	/**
	 * Add or update a Plugin Release.
	 *
	 * @param string|\WP_Post $plugin Plugin slug or post object.
	 * @param array           $data   Release data.
	 * @return bool
	 */
	public function add_release( $plugin, $data ) {
		if ( ! isset( $data['tag'] ) ) {
			return false;
		}

		$plugin = Plugin_Directory::get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return false;
		}

		$existing_post = $this->get_release_post_by_tag( $plugin, $data['tag'] );
		$release       = $existing_post ? $this->post_to_release_data( $existing_post, $plugin ) : $this->get_default_release_data( $plugin );

		foreach ( $data as $key => $value ) {
			if ( isset( $release[ $key ] ) && is_array( $release[ $key ] ) ) {
				$release[ $key ] = array_unique( array_merge( $release[ $key ], (array) $value ) );
			} else {
				$release[ $key ] = $value;
			}
		}

		if ( isset( $data['undo-discard'] ) && ! empty( $release['discarded'] ) && empty( $data['discarded'] ) ) {
			unset( $release['discarded'] );
		}
		unset( $release['undo-discard'] );

		$release = $this->normalize_release_data( $release, $plugin );

		$release_id = $this->save_release_post( $plugin, $release, $existing_post );
		if ( ! $release_id || is_wp_error( $release_id ) ) {
			return false;
		}

		$this->delete_duplicate_release_posts( $plugin, $release['tag'], $release_id );

		return true;
	}

	/**
	 * Remove an unconfirmed Plugin Release.
	 *
	 * @param string|\WP_Post $plugin Plugin slug or post object.
	 * @param string          $tag    Release tag.
	 * @return bool
	 */
	public function remove_release( $plugin, $tag ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return false;
		}

		$release_post = $this->get_release_post_by_tag( $plugin, $tag );
		if ( ! $release_post ) {
			return false;
		}

		$release = $this->post_to_release_data( $release_post, $plugin );
		if ( ! empty( $release['confirmed'] ) ) {
			return false;
		}

		return (bool) wp_delete_post( $release_post->ID, true );
	}

	/**
	 * Query release CPT posts for a plugin.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @param int      $limit  Maximum number of posts.
	 * @return \WP_Post[]
	 */
	private function get_release_posts( $plugin, $limit = -1 ) {
		$this->ensure_post_type();

		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'posts_per_page'   => $limit,
				'post_parent'      => $plugin->ID,
				'post_status'      => 'any',
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);
	}

	/**
	 * Query one release CPT post for an exact release tag.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @param string   $tag    Release tag.
	 * @return \WP_Post|null
	 */
	private function get_release_post_by_tag( $plugin, $tag ) {
		$this->ensure_post_type();

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'posts_per_page'   => 1,
				'post_parent'      => $plugin->ID,
				'post_status'      => 'any',
				'meta_key'         => 'release_tag', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => $tag, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);

		return $posts ? $posts[0] : null;
	}

	/**
	 * Save a release array as a CPT post.
	 *
	 * @param \WP_Post      $plugin        Plugin post object.
	 * @param array         $release       Release data.
	 * @param \WP_Post|null $existing_post Existing release post, if any.
	 * @return int|\WP_Error
	 */
	private function save_release_post( $plugin, $release, $existing_post = null ) {
		$this->ensure_post_type();

		$title = $release['version'] ? $release['version'] : $release['tag'];
		if ( 'trunk' === $release['tag'] ) {
			$title = 'trunk';
		}

		$date = (int) $release['date'];
		$date = $date ? $date : time();
		$post = array(
			'post_type'      => self::POST_TYPE,
			'post_title'     => $title,
			'post_name'      => sanitize_title( $plugin->post_name . '-' . $release['tag'] ),
			'post_parent'    => $plugin->ID,
			'post_status'    => ( 'trunk' === $release['tag'] ) ? 'draft' : 'publish',
			'post_date'      => gmdate( 'Y-m-d H:i:s', $date ),
			'post_date_gmt'  => gmdate( 'Y-m-d H:i:s', $date ),
			'post_content'   => '',
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
		);

		if ( $existing_post ) {
			$post['ID'] = $existing_post->ID;
			$release_id = wp_update_post( $post, true );
		} else {
			$release_id = wp_insert_post( $post, true );
		}

		if ( ! $release_id || is_wp_error( $release_id ) ) {
			return $release_id;
		}

		$this->update_release_meta( $release_id, $release );

		return $release_id;
	}

	/**
	 * Update full and mirrored release postmeta.
	 *
	 * @param int   $release_id Release post ID.
	 * @param array $release    Release data.
	 */
	private function update_release_meta( $release_id, $release ) {
		update_post_meta( $release_id, self::DATA_META_KEY, $release );

		$mirrored_fields = array(
			'date'                     => 'release_date',
			'tag'                      => 'release_tag',
			'version'                  => 'release_version',
			'committer'                => 'release_committer',
			'zips_built'               => 'release_zips_built',
			'zips_built_from_revision' => 'release_zips_built_from_revision',
			'confirmations'            => 'release_confirmations',
			'confirmed'                => 'release_confirmed',
			'confirmations_required'   => 'release_confirmations_required',
			'revision'                 => 'release_revision',
			'revision_final'           => 'release_revision_final',
			'revision_prior'           => 'release_revision_prior',
			'commit_log'               => 'release_commit_log',
			'tested'                   => 'release_tested',
			'requires_php'             => 'release_requires_php',
			'requires_wp'              => 'release_requires_wp',
			'requires_plugins'         => 'release_requires_plugins',
			'discarded'                => 'release_discarded',
			'rollout_strategy'         => 'release_rollout_strategy',
			'release_delay'            => 'release_delay',
		);

		foreach ( $mirrored_fields as $field => $meta_key ) {
			if ( array_key_exists( $field, $release ) ) {
				update_post_meta( $release_id, $meta_key, $release[ $field ] );
			} else {
				delete_post_meta( $release_id, $meta_key );
			}
		}
	}

	/**
	 * Delete duplicate release posts for a tag after an upsert.
	 *
	 * @param \WP_Post $plugin      Plugin post object.
	 * @param string   $tag         Release tag.
	 * @param int      $release_id  Release post that should remain.
	 */
	private function delete_duplicate_release_posts( $plugin, $tag, $release_id ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'posts_per_page'   => -1,
				'post_parent'      => $plugin->ID,
				'post_status'      => 'any',
				'meta_key'         => 'release_tag', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => $tag, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'           => 'ids',
				'suppress_filters' => true,
			)
		);

		foreach ( $posts as $post_id ) {
			if ( (int) $post_id !== (int) $release_id ) {
				wp_delete_post( $post_id, true );
			}
		}
	}

	/**
	 * Convert a release CPT post to the legacy release array shape.
	 *
	 * @param \WP_Post $release_post Release post object.
	 * @param \WP_Post $plugin       Plugin post object.
	 * @return array
	 */
	private function post_to_release_data( $release_post, $plugin ) {
		$data = get_post_meta( $release_post->ID, self::DATA_META_KEY, true );
		$data = is_array( $data ) ? $data : array();

		$legacy_meta_fields = array(
			'date'                     => 'release_date',
			'tag'                      => 'release_tag',
			'version'                  => 'release_version',
			'committer'                => 'release_committer',
			'zips_built'               => 'release_zips_built',
			'zips_built_from_revision' => 'release_zips_built_from_revision',
			'confirmations'            => 'release_confirmations',
			'confirmed'                => 'release_confirmed',
			'confirmations_required'   => 'release_confirmations_required',
			'revision'                 => 'release_revision',
			'revision_final'           => 'release_revision_final',
			'revision_prior'           => 'release_revision_prior',
			'commit_log'               => 'release_commit_log',
			'tested'                   => 'release_tested',
			'requires_php'             => 'release_requires_php',
			'requires_wp'              => 'release_requires_wp',
			'requires_plugins'         => 'release_requires_plugins',
			'discarded'                => 'release_discarded',
			'rollout_strategy'         => 'release_rollout_strategy',
			'release_delay'            => 'release_delay',
		);

		foreach ( $legacy_meta_fields as $field => $meta_key ) {
			if ( array_key_exists( $field, $data ) ) {
				continue;
			}

			if ( metadata_exists( 'post', $release_post->ID, $meta_key ) ) {
				$data[ $field ] = get_post_meta( $release_post->ID, $meta_key, true );
			}
		}

		if ( empty( $data['date'] ) ) {
			$data['date'] = strtotime( $release_post->post_date_gmt ? $release_post->post_date_gmt : $release_post->post_date );
		}
		if ( empty( $data['tag'] ) ) {
			$tag         = get_post_meta( $release_post->ID, 'release_tag', true );
			$data['tag'] = $tag ? $tag : $release_post->post_title;
		}
		if ( empty( $data['version'] ) ) {
			$version         = get_post_meta( $release_post->ID, 'release_version', true );
			$data['version'] = $version ? $version : $release_post->post_title;
		}

		return $this->normalize_release_data( $data, $plugin );
	}

	/**
	 * Get the default legacy release array for a plugin.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 * @return array
	 */
	private function get_default_release_data( $plugin ) {
		return array(
			'date'                     => time(),
			'tag'                      => '',
			'version'                  => '',
			'zips_built'               => ! $plugin->release_confirmation,
			'zips_built_from_revision' => 0,
			'confirmations'            => array(),
			'confirmed'                => ! $plugin->release_confirmation,
			'confirmations_required'   => (int) $plugin->release_confirmation,
			'committer'                => array(),
			'revision'                 => array(),
			'release_delay'            => get_release_cooldown_delay( $plugin->post_name ),
		);
	}

	/**
	 * Normalize a release array to match the legacy storage contract.
	 *
	 * @param array    $release Release data.
	 * @param \WP_Post $plugin  Plugin post object.
	 * @return array
	 */
	private function normalize_release_data( $release, $plugin ) {
		$release = wp_parse_args( $release, $this->get_default_release_data( $plugin ) );

		$release['date']                     = (int) $release['date'];
		$release['tag']                      = (string) $release['tag'];
		$release['version']                  = (string) $release['version'];
		$release['committer']                = array_values( array_unique( array_filter( (array) $release['committer'] ) ) );
		$release['revision']                 = array_values( array_unique( array_filter( (array) $release['revision'] ) ) );
		$release['confirmations']            = is_array( $release['confirmations'] ) ? $release['confirmations'] : array();
		$release['confirmations_required']   = (int) $release['confirmations_required'];
		$release['zips_built']               = (bool) $release['zips_built'];
		$release['zips_built_from_revision'] = (int) $release['zips_built_from_revision'];
		$release['confirmed']                = (bool) $release['confirmed'];
		$release['release_delay']            = (int) $release['release_delay'];

		if ( ! $release['confirmations_required'] && ! $release['zips_built'] ) {
			$release['zips_built'] = true;
		}

		return $release;
	}
}

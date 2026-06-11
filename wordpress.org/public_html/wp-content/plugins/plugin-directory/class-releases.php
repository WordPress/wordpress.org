<?php
/**
 * Plugin release CPT storage.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

namespace WordPressdotorg\Plugin_Directory;

/**
 * Storage and compatibility layer for the release records of a plugin.
 *
 * Releases used to be stored as one `releases` postmeta array on the plugin
 * post. This keeps the public Plugin_Directory::get_release(s)/add_release()
 * array contract intact while moving the backing storage to child CPT records.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Releases {

	const POST_TYPE       = 'plugin_release';
	const BACKFILLED_META = '_releases_cpt_backfilled';
	const CACHE_GROUP     = 'plugin_release';

	/**
	 * Release array fields stored as postmeta on the release post.
	 */
	const META_FIELDS = array(
		'date',
		'tag',
		'version',
		'committer',
		'zips_built',
		'zips_built_from_revision',
		'confirmations',
		'confirmed',
		'confirmations_required',
		'revision',
		'revision_final',
		'revision_prior',
		'commit_log',
		'tested',
		'requires_php',
		'requires_wp',
		'requires_plugins',
		'discarded',
		'rollout_strategy',
		'release_delay',
	);

	/**
	 * The plugin whose releases this instance manages.
	 *
	 * @var \WP_Post
	 */
	private $plugin;

	/**
	 * Releases constructor.
	 *
	 * @param \WP_Post $plugin Plugin post object.
	 */
	private function __construct( $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Get a Releases accessor for a plugin.
	 *
	 * @param string|\WP_Post $plugin Plugin slug or post object.
	 * @return Releases|null Null for unknown plugins; chain with the nullsafe operator.
	 */
	public static function for_plugin( $plugin ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return null;
		}

		return new Releases( $plugin );
	}

	/**
	 * Register the private release CPT.
	 */
	public static function register_post_type() {
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
	private static function ensure_post_type() {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			self::register_post_type();
		}
	}

	/**
	 * Get all releases for the plugin as legacy release arrays.
	 *
	 * @return array
	 */
	public function get_all() {
		$releases = array_map(
			function ( $release_post ) {
				return $this->post_to_data( $release_post );
			},
			$this->get_posts()
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
	 * Check if the plugin has any CPT release records.
	 *
	 * @return bool
	 */
	public function has() {
		return (bool) $this->get_posts( 1 );
	}

	/**
	 * Backfill release CPTs from legacy release metadata, tags metadata, or SVN.
	 *
	 * This is driven by the one-off migration script (bin/backfill-release-cpts.php)
	 * and runs lazily before writes (add() / remove()), but not on reads.
	 *
	 * @param bool $force Whether to run even if CPT releases exist.
	 * @return array|false Backfilled release arrays, false when skipped.
	 */
	public function maybe_backfill( $force = false ) {
		if ( ! $force ) {
			if ( $this->has() ) {
				return false;
			}

			if ( get_post_meta( $this->plugin->ID, self::BACKFILLED_META, true ) ) {
				return false;
			}
		}

		$legacy_releases = get_post_meta( $this->plugin->ID, 'releases', true );
		if ( is_array( $legacy_releases ) ) {
			$releases = $legacy_releases;
		} else {
			$releases = $this->get_prefill_data();
		}

		// Mark as migrated up-front, so a concurrent (or recursive, via add()) backfill bails early.
		update_post_meta( $this->plugin->ID, self::BACKFILLED_META, time() );

		foreach ( $releases as $release ) {
			$this->add( $release );
		}

		return $releases;
	}

	/**
	 * Get prefill release data from old tags metadata or SVN tags.
	 *
	 * @return array
	 */
	private function get_prefill_data() {
		$releases = array();
		$tags     = get_post_meta( $this->plugin->ID, 'tags', true );

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

		$svn_tags = Tools\SVN::ls( "https://plugins.svn.wordpress.org/{$this->plugin->post_name}/tags/", true );
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
	 * @param string $tag Plugin version / release tag.
	 * @return array|bool
	 */
	public function get( $tag ) {
		$release_post = $this->get_post_by_tag( $tag );

		// Fall back to a trunk release of that version, recorded as trunk@{version}.
		if ( ! $release_post ) {
			$release_post = $this->get_post_by_tag( "trunk@{$tag}" );
			if ( $release_post && $release_post->version !== $tag ) {
				$release_post = null;
			}
		}

		return $release_post ? $this->post_to_data( $release_post ) : false;
	}

	/**
	 * Add or update a Plugin Release.
	 *
	 * @param array $data Release data.
	 * @return bool
	 */
	public function add( $data ) {
		if ( ! isset( $data['tag'] ) ) {
			return false;
		}

		$this->maybe_backfill();

		$existing_post = $this->get_post_by_tag( $data['tag'] );
		$release       = $existing_post ? $this->post_to_data( $existing_post ) : $this->get_default_data();

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

		$release = $this->normalize_data( $release );

		$release_id = $this->save_post( $release, $existing_post );
		if ( ! $release_id || is_wp_error( $release_id ) ) {
			return false;
		}

		$this->delete_duplicate_posts( $release['tag'], $release_id );

		return true;
	}

	/**
	 * Remove an unconfirmed Plugin Release.
	 *
	 * @param string $tag Release tag.
	 * @return bool
	 */
	public function remove( $tag ) {
		$this->maybe_backfill();

		$release_post = $this->get_post_by_tag( $tag );
		if ( ! $release_post ) {
			return false;
		}

		$release = $this->post_to_data( $release_post );
		if ( ! empty( $release['confirmed'] ) ) {
			return false;
		}

		return (bool) wp_delete_post( $release_post->ID, true );
	}

	/**
	 * Query release CPT posts for the plugin.
	 *
	 * @param int $limit Maximum number of posts.
	 * @return \WP_Post[]
	 */
	private function get_posts( $limit = -1 ) {
		self::ensure_post_type();

		return get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'posts_per_page'   => $limit,
				'post_parent'      => $this->plugin->ID,
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
	 * @param string $tag Release tag.
	 * @return \WP_Post|null
	 */
	private function get_post_by_tag( $tag ) {
		self::ensure_post_type();

		// The cached ID is validated below, so deletions and re-tags self-correct.
		$cache_key = $this->plugin->post_name . ':' . $tag;
		$post_id   = wp_cache_get( $cache_key, self::CACHE_GROUP );
		if ( $post_id ) {
			$post = get_post( $post_id );
			if (
				$post &&
				self::POST_TYPE === $post->post_type &&
				$this->plugin->ID === $post->post_parent &&
				$post->tag === $tag
			) {
				return $post;
			}

			wp_cache_delete( $cache_key, self::CACHE_GROUP );
		}

		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'posts_per_page'   => 1,
				'post_parent'      => $this->plugin->ID,
				'post_status'      => 'any',
				'meta_key'         => 'tag', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'   => $tag, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'orderby'          => 'date',
				'order'            => 'DESC',
				'suppress_filters' => true,
			)
		);

		if ( ! $posts ) {
			return null;
		}

		wp_cache_set( $cache_key, $posts[0]->ID, self::CACHE_GROUP );

		return $posts[0];
	}

	/**
	 * Save a release array as a CPT post.
	 *
	 * @param array         $release       Release data.
	 * @param \WP_Post|null $existing_post Existing release post, if any.
	 * @return int|\WP_Error
	 */
	private function save_post( $release, $existing_post = null ) {
		self::ensure_post_type();

		$title = $release['version'] ? $release['version'] : $release['tag'];
		if ( 'trunk' === $release['tag'] ) {
			$title = 'trunk';
		}

		$date = (int) $release['date'];
		$date = $date ? $date : time();
		$post = array(
			'post_type'      => self::POST_TYPE,
			'post_title'     => $title,
			'post_name'      => sanitize_title( $this->plugin->post_name . '-' . $release['tag'] ),
			'post_parent'    => $this->plugin->ID,
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

		$this->update_meta( $release_id, $release );

		return $release_id;
	}

	/**
	 * Update the release postmeta fields.
	 *
	 * @param int   $release_id Release post ID.
	 * @param array $release    Release data.
	 */
	private function update_meta( $release_id, $release ) {
		foreach ( self::META_FIELDS as $field ) {
			if ( array_key_exists( $field, $release ) ) {
				update_post_meta( $release_id, $field, $release[ $field ] );
			} else {
				delete_post_meta( $release_id, $field );
			}
		}
	}

	/**
	 * Delete duplicate release posts for a tag after an upsert.
	 *
	 * @param string $tag         Release tag.
	 * @param int    $release_id  Release post that should remain.
	 */
	private function delete_duplicate_posts( $tag, $release_id ) {
		$posts = get_posts(
			array(
				'post_type'        => self::POST_TYPE,
				'posts_per_page'   => -1,
				'post_parent'      => $this->plugin->ID,
				'post_status'      => 'any',
				'meta_key'         => 'tag', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
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
	 * @return array
	 */
	private function post_to_data( $release_post ) {
		$data = array();

		foreach ( self::META_FIELDS as $field ) {
			if ( metadata_exists( 'post', $release_post->ID, $field ) ) {
				$data[ $field ] = get_post_meta( $release_post->ID, $field, true );
			}
		}

		if ( empty( $data['date'] ) ) {
			$data['date'] = strtotime( $release_post->post_date_gmt ? $release_post->post_date_gmt : $release_post->post_date );
		}
		if ( empty( $data['tag'] ) ) {
			$data['tag'] = $release_post->post_title;
		}
		if ( empty( $data['version'] ) ) {
			$data['version'] = $release_post->post_title;
		}

		return $this->normalize_data( $data );
	}

	/**
	 * Get the default legacy release array for the plugin.
	 *
	 * @return array
	 */
	private function get_default_data() {
		return array(
			'date'                     => time(),
			'tag'                      => '',
			'version'                  => '',
			'zips_built'               => ! $this->plugin->release_confirmation,
			'zips_built_from_revision' => 0,
			'confirmations'            => array(),
			'confirmed'                => ! $this->plugin->release_confirmation,
			'confirmations_required'   => (int) $this->plugin->release_confirmation,
			'committer'                => array(),
			'revision'                 => array(),
			'release_delay'            => get_release_cooldown_delay( $this->plugin->post_name ),
		);
	}

	/**
	 * Normalize a release array to match the legacy storage contract.
	 *
	 * @param array $release Release data.
	 * @return array
	 */
	private function normalize_data( $release ) {
		$release = wp_parse_args( $release, $this->get_default_data() );

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

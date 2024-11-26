<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * The Plugin Release class encapsulates the plugin release CPT and related code.
 * Used for storing and interacting with plugin releases; ie versions of a plugin that are made available for download.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Plugin_Release {
	/**
	 * Fetch the instance of the Plugin_Release class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Plugin_Release();
	}

	/**
	 * Plugin_Release constructor.
	 *
	 * @access private
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize the Plugin_Release class.
	 */
	public function init() {
		register_post_type( 'plugin_release', array(
			'labels'              => array(
				'name'          => __( 'Releases', 'wporg-plugins' ),
				'singular_name' => __( 'Release', 'wporg-plugins' ),
			),
			'public'              => false,
			'show_ui'             => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => true,
			'show_in_rest'        => true, // FIXME: maybe?
			'supports'            => array( 'title', 'editor' ), // TBD
			'rewrite'             => false,
			'query_var'           => false,
			'hierarchical'        => false, // Disappointingly, this doesn't help us make a Post -> Release hierarchy.
		) );
	}

	// Starting point for an internal API, mostly copilot-generated.

	/**
	 * Get all releases for a plugin.
	 */
	public function get_releases( $plugin ) {
		$plugin_id = ( get_post( $plugin ) )->ID;

		$releases = get_posts( array(
			'post_type'      => 'plugin_release',
			'posts_per_page' => -1,
			'post_parent'    => $plugin_id,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		return $releases;
	}

	/**
	 * Add release info for a plugin.
	 */
	public function add_release( $plugin, $release ) {
		$plugin = get_post( $plugin );
		$plugin_id = $plugin->ID;

		if ( !$plugin || 'plugin' !== $plugin->post_type ) {
			return new \WP_Error( 'invalid_plugin', 'Invalid plugin' );
		}

		$release_date = date( 'Y-m-d H:i:s', $release['date'] );
		$committer_user_id = get_user_by( 'login', reset( $release['committer'] ) )->ID;
		if ( ! $committer_user_id ) {
			return new \WP_Error( 'invalid_committer', 'Invalid committer' );
		}

		$post_status = ( 'trunk' === $release['tag'] ) ? 'draft' : 'publish';

		$release_id = wp_insert_post( array(
			'post_type'   => 'plugin_release',
			'post_title'  => $release['version'],
			'post_name'   => $plugin->post_name . '-' . $release['version'],
			'post_parent' => $plugin_id,
			'post_status' => $post_status,
			'post_date'   => $release_date, // And/or post_date_gmt?
			// Mirrors the metadata.
			'meta_input'  => array(
				'release_date'      => $release['date'],
				'release_tag'       => $release['tag'],
				'release_version'   => $release['version'],
				'release_committer' => $release['committer'],
				'release_zips_built' => $release['zips_built'],
				'release_confirmations_required' => $release['confirmations_required'],
				'release_revision' => $release['revision'],
			),
			// TODO: what else? Could store the changelog or other content at the point of release for comparison purposes.
		) );

		return $release_id;
	}

	/**
	 * Update existing release info.
	 */
	public function update_release( $release_id, $release ) {

		$release_date = date( 'Y-m-d H:i:s', $release['date'] );
		$committer_user_id = get_user_by( 'login', reset( $release['committer'] ) )->ID;
		if ( ! $committer_user_id ) {
			return new \WP_Error( 'invalid_committer', 'Invalid committer' );
		}

		$release_post = get_post( $release_id );
		if ( ! $release_post || 'plugin_release' !== $release_post->post_type ) {
			return new \WP_Error( 'invalid_release', 'Invalid release' );
		}

		$parent_plugin = get_post( $release_post->post_parent );
		if ( ! $parent_plugin || 'plugin' !== $parent_plugin->post_type ) {
			return new \WP_Error( 'invalid_plugin', 'Invalid plugin' );
		}

		$post_status = ( 'trunk' === $release['tag'] ) ? 'draft' : 'publish';

		$release_id = wp_update_post( array(
			'ID'           => $release_id,
			'post_type'   => 'plugin_release',
			'post_title'  => $release['version'],
			'post_name'   => $parent_plugin->post_name . '-' . $release['version'],
			'post_parent' => $parent_plugin->ID,
			'post_status' => $post_status,
			'post_date'   => $release_date, // And/or post_date_gmt?
			// Mirrors the metadata.
			'meta_input'  => array(
				'release_date'      => $release['date'],
				'release_tag'       => $release['tag'],
				'release_version'   => $release['version'],
				'release_committer' => $release['committer'],
				'release_zips_built' => $release['zips_built'],
				'release_confirmations_required' => $release['confirmations_required'],
				'release_revision' => $release['revision'],
			),
			// TODO: what else? Could store the changelog or other content at the point of release for comparison purposes.
		) );

		return $release_id;
	}

	/**
	 * Save draft (trunk) release for a plugin.
	 */
	public function add_or_update_draft_release( $plugin, $release ) {
		$plugin = get_post( $plugin );
		$plugin_id = $plugin->ID;

		// Tag must be 'trunk' for this to be a draft release.
		if ( 'trunk' !== $release['tag'] ) {
			return new \WP_Error( 'invalid_tag', 'Invalid tag' );
		}

		// Version must be set; we'll only add/update if it doesn't match an existing non-draft release.
		if ( empty( $release['version'] ) ) {
			return new \WP_Error( 'invalid_version', 'Invalid version' );
		}

		if ( !$plugin || 'plugin' !== $plugin->post_type ) {
			return new \WP_Error( 'invalid_plugin', 'Invalid plugin' );
		}

		// If there's already a published release for this plugin, we only create a draft if there are unreleased trunk commits.
		$last_release = $this->get_release( $plugin, null );
		if ( $last_release ) {
			if ( !empty( $release[ 'revision' ] ) ) {
				// Don't create a draft unless the revision number is higher than the last release.
				if ( max( $release['revision'] ) <= max( $last_release->release_revision ) ) {
					return false; // Not an error, just skip.
				}
			} else {
				// If we don't have revision numbers, use dates. Maybe this should be removed.
				if ( strtotime( $release['date'] ) <= strtotime( $last_release->release_date ) ) {
					return false; // Not an error, just skip.
				}
			}
		}

		$draft_id = $this->get_release( $plugin, 'trunk' );
		if ( $draft_id ) {
			$release_id = $this->update_release( $draft_id, $release );
		} else {
			$release_id = $this->add_release( $plugin, $release );
		}

		return $release_id;
	}

	function delete_release( $release_id ) {
		$release_post = get_post( $release_id );
		if ( ! $release_post || 'plugin_release' !== $release_post->post_type ) {
			return new \WP_Error( 'invalid_release', 'Invalid release' );
		}

		return wp_delete_post( $release_id, false ); // FIXME: change to true for force delete when this is ready and WELL TESTED.
	}

	/**
	 * Update all release info for a plugin. This will insert or update each release, and remove any unknown releases.
	 *
	 * @param int|WP_Post $plugin The plugin post.
	 * @param array $releases An array of release data. Should be a complete array of all releases.
	 * @return int|WP_Error The number of changes made.
	 */
	public function update_releases( $plugin, $releases ) {
		$plugin_id = ( get_post( $plugin ) )->ID;

		if ( 'plugin' !== get_post_type( $plugin ) ) {
			return new \WP_Error( 'invalid_plugin', 'Invalid plugin' );
		}

		$changed = false;

		// The current releases, if any, that need to be updated.
		$current_releases = $this->get_releases( $plugin );
		$current_versions = wp_list_pluck( $current_releases, 'post_title', 'ID' );

		// Add or update each release.
		foreach ( $releases as $release ) {
			if ( ! in_array( $release['version'], $current_versions ) ) {
				// Add a CPT for the release if one does not yet exist.
				$r = $this->add_release( $plugin, $release );
				#fputs( STDERR, 'add: ' . var_export( $r, true ) . "\n" );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				++ $changed;
			} else {
				// Update an existing CPT for the release.
				// Note that this will update the CPT even if no data has changed.
				$release_id = array_search( $release['version'], $current_versions );
				$r = $this->update_release( $release_id, $release );
				#fputs( STDERR, 'update: ' . var_export( $r, true ) . "\n" );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				++ $changed;
			}
		}

		// Remove any releases that are no longer present.
		foreach ( $current_versions as $release_id => $release_version ) {
			// A CPT that doesn't exist in the $releases array should be removed.
			if ( ! in_array( $release_version, wp_list_pluck( $releases, 'version' ) ) ) {
				$r = $this->delete_release( $release_id );
				#fputs( STDERR, 'delete: ' . var_export( $r, true ) . "\n" );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				++ $changed;
			}
			// If there are multiple releases with the same version (title), remove all but the first.
			// TODO: Not sure this code should stay.
			if ( $release_id !==  array_search( $release_version, $current_versions ) ) {
				$r = $this->delete_release( $release_id );
				#fputs( STDERR, 'delete dupe: ' . var_export( $r, true ) . "\n" );
				if ( is_wp_error( $r ) ) {
					return $r;
				}
				++ $changed;
			}
		}

		return $changed;
	}

	/**
	 * Get a specific plugin release.
	 */
	public function get_release( $plugin, $version ) {
		$plugin_id = ( get_post( $plugin ) )->ID;

		// Note that the post_status is 'draft' for trunk releases.
		$post_status = ( 'trunk' === $version ) ? 'draft' : 'publish';

		$release = get_posts( array(
			'post_type'      => 'plugin_release',
			'posts_per_page' => 1,
			'post_parent'    => $plugin_id,
			'post_title'     => $version,
			'post_status'    => $post_status,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		return $release ? $release[0] : null;
	}

}
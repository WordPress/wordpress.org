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
			'publicly_queryable'  => false,
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
		$plugin_id = ( get_post( $plugin ) )->ID;

		// Make sure we don't accidentally add junk from a sandbox while tinkering.
		die( "Not yet ready for use" );

		$release_date = date( 'Y-m-d H:i:s', strtotime( $tag['date'] ) );
		$committer_user_id = get_user_by( 'login', $tag['author'] )->ID;
		if ( ! $committer_user_id ) {
			return new WP_Error( 'invalid_committer', 'Invalid committer' );
		}

		$release_id = wp_insert_post( array(
			'post_type'   => 'plugin_release',
			'post_title'  => $release['version'],
			'post_parent' => $plugin_id,
			'post_status' => 'publish',
			'post_date'   => $release_date, // And/or post_date_gmt?
			// Mirrors the metadata.
			'meta_input'  => array(
				'release_date'      => $release['date'],
				'release_tag'       => $release['tag'],
				'release_version'   => $release['version'],
				'release_committer' => $release['committer'],
				'release_zips_built' => $release['zips_built'],
				'release_confirmations_required' => $release['confirmations_required'],
			),
			// TODO: what else? Could store the changelog or other content at the point of release for comparison purposes.
		) );

		return $release_id;
	}

	/**
	 * Update existing release info.
	 */
	public function update_release( $release_id, $release ) {
		// Make sure we don't accidentally add junk from a sandbox while tinkering.
		die( "Not yet ready for use" );

		$release_date = date( 'Y-m-d H:i:s', strtotime( $tag['date'] ) );
		$committer_user_id = get_user_by( 'login', $tag['author'] )->ID;
		if ( ! $committer_user_id ) {
			return new WP_Error( 'invalid_committer', 'Invalid committer' );
		}

		$release_id = wp_update_post( array(
			'ID'           => $release_id,
			'post_type'   => 'plugin_release',
			'post_title'  => $release['version'],
			'post_parent' => $plugin_id,
			'post_status' => 'publish',
			'post_date'   => $release_date, // And/or post_date_gmt?
			// Mirrors the metadata.
			'meta_input'  => array(
				'release_date'      => $release['date'],
				'release_tag'       => $release['tag'],
				'release_version'   => $release['version'],
				'release_committer' => $release['committer'],
				'release_zips_built' => $release['zips_built'],
				'release_confirmations_required' => $release['confirmations_required'],
			),
			// TODO: what else? Could store the changelog or other content at the point of release for comparison purposes.
		) );

		return $release_id;
	}

	/**
	 * Update all release info for a plugin. This will insert or update each release, and remove any unknown releases.
	 */
	public function update_releases( $plugin, $releases ) {
		$plugin_id = ( get_post( $plugin ) )->ID;

		// Make sure we don't accidentally add junk from a sandbox while tinkering.
		die( "Not yet ready for use" );

		$changed = false;

		// The current releases, if any, that need to be updated.
		$current_releases = $this->get_releases( $plugin );
		$current_versions = wp_list_pluck( $current_releases, 'post_title', 'ID' );

		// Add or update each release.
		foreach ( $releases as $release ) {
			if ( ! isset( $current_versions[ $release['version'] ] ) ) {
				$changed = $changed | (bool)$this->add_release( $plugin, $release );
			} else {
				$release_id = $current_versions[ $release['version'] ];
				$changed = $changed | (bool)$this->update_release( $release_id, $release );
			}
		}

		// Remove any releases that are no longer present.
		foreach ( $current_releases as $release_id => $release ) {
			if ( ! in_array( $release->post_title, wp_list_pluck( $releases, 'version' ) ) ) {
				$changed = $changed | (bool)wp_delete_post( $release_id, true ); // Force delete.
			}
		}

		return $changed;
	}

	/**
	 * Get a specific plugin release.
	 */
	public function get_release( $plugin, $version ) {
		$plugin_id = ( get_post( $plugin ) )->ID;

		$release = get_posts( array(
			'post_type'      => 'plugin_release',
			'posts_per_page' => 1,
			'post_parent'    => $plugin_id,
			'post_title'     => $version,
		) );

		return $release ? $release[0] : null;
	}

}
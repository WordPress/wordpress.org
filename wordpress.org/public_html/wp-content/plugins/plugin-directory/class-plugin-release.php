<?php
namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Cli\Import;

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
	 * Check if a plugin has any release CPTs stored.
	 * Note that this intentionally does not count draft releases. If needed, we can add a parameter to support that.
	 */
	public function has_releases( $plugin ) {
		$release = $this->get_release( $plugin, null );
		return ! empty( $release );
	}

	/**
	 * Backfill releases for a plugin, if none exist. This uses the releases postmeta to populate the CPTs.
	 */
	public function maybe_backfill_releases( $plugin ) {
		$plugin = get_post( $plugin );

		if ( !$plugin || 'plugin' !== $plugin->post_type ) {
			return new \WP_Error( 'invalid_plugin', 'Invalid plugin' );
		}

		// This will backfill the releases postmeta if needed.
		$releases_postmeta = Plugin_Directory::get_releases( $plugin );

		// Add or update the release CPTs using postmeta.
		if ( $releases_postmeta && ! $this->has_releases( $plugin ) ) {
			return $this->update_releases( $plugin, $releases_postmeta );
		}

		return false;
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
		$post_title = ( 'trunk' === $release['tag'] ) ? 'trunk' : $release['version'];

		$release_id = wp_insert_post( array(
			'post_type'   => 'plugin_release',
			'post_title'  => $post_title,
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
				'release_commit_log' => $release['commit_log'] ?? null,
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
		$post_title = ( 'trunk' === $release['tag'] ) ? 'trunk' : $release['version'];

		$release_id = wp_update_post( array(
			'ID'           => $release_post->ID,
			'post_type'   => 'plugin_release',
			'post_title'  => $post_title,
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
				'release_commit_log' => $release['commit_log'] ?? null,
				'release_tested' => $release['tested'] ?? null,
				'release_requires_php' => $release['requires_php'] ?? null,
				'release_requires_wp' => $release['requires_wp'] ?? null,
				'release_requires_plugins' => $release['requires_plugins'] ?? null,
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

		// Tag must be 'trunk' for this to be a draft release.
		if ( 'trunk' !== $release['tag'] ) {
			return new \WP_Error( 'invalid_tag', 'Invalid tag' );
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

		// Store the commit log in postmeta. We'll only do this for drafts.
		$last_release_revision = max( $last_release->release_revision );
		if ( $last_release_revision && $release['revision'] ) {
			$trunk_url = Import::PLUGIN_SVN_BASE . '/' . $plugin->post_name . '/trunk';
			$commit_log = SVN::log( $trunk_url, [ $last_release_revision, max( $release['revision'] ) ] );
			$release['commit_log'] = $commit_log['log'] ?? null;
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
			'title'          => $version,
			'post_status'    => $post_status,
			'orderby'        => 'date',
			'order'          => 'DESC',
		) );

		return $release ? $release[0] : null;
	}

	/**
	 * Publish a draft release (ie trunk).
	 * This will use svn to tag the release, and then publish the release post.
	 *
	 * Note: As yet untested.
	 */
	public function publish_release( $plugin ) {
		$plugin = get_post( $plugin );

		// TODO: current_user_can()? Or other checks?

		$draft = $this->get_release( $plugin, 'trunk' );
		if ( ! $draft ) {
			return new \WP_Error( 'no_draft', 'No draft release found' );
		}

		$new_tag = $draft->release_version;
		if ( $this->get_release( $plugin, $new_tag ) ) {
			return new \WP_Error( 'tag_exists', 'Tag already exists', $new_tag );
		}

		if ( !$draft->plugin_check_result || ! $draft->plugin_check_result['verdict'] ) {
			return new \WP_Error( 'plugin_check_failed', 'Plugin check failed' );
		}

		// TODO: Should import warnings exist on the release CPT?
		if ( $plugin->_import_warnings ) {
			// These warnings are likely (always?) present because the tag hasn't been created yet.
			$ignored_warnings = [
				'stable_tag_invalid_trunk_fallback' => 1,
				'stable_tag_invalid' => 1,
			];
			// Stop here if other warnings are present.
			if ( array_diff_key( $plugin->_import_warnings, $ignored_warnings ) ) {
				return new \WP_Error( 'import_warnings', 'Import warnings', $plugin->_import_warnings );
			}
		}

		// TODO: What sanitizing or cross-checking do we need here?
		$trunk_url = 'https://plugins.svn.wordpress.org/' . $plugin->post_name . '/trunk';
		$tag_url = 'https://plugins.svn.wordpress.org/' . $plugin->post_name . '/tags/' . $new_tag;

		// TODO: Decide if we're committing this as a specific user. Also any other options needed.
		// Note that since this is a url-to-url copy, the commit happens immediately.
		$svn_options = [
			'message' => 'Tagging ' . $new_tag . ' from trunk@' . reset( $draft->release_revision ), // Commit message. i18n?
		];
		$tag_result = SVN::copy( $trunk_url, $tag_url, $svn_options );

		if ( !$tag_result || ! $tag_result['result'] ) {
			return new \WP_Error( 'svn_error', 'SVN error', $tag_result['errors'] );
		}

		// Include the tag revision in the release post list of revisions.
		// This is so that we can easily tell if there are trunk commits after the release.
		$release_revisions = array_merge( $draft->release_revision, [ $tag_result['revision'] ] );

		$release_id = wp_update_post( array(
			'ID'          => $draft->ID,
			'post_status' => 'publish',
			'post_title'  => $new_tag,
			'meta_input'  => array(
				'release_revision'     => $release_revisions,
				#'release_tag_revision' => $tag_result['revision'], // Do we need this? Probably not.
				'release_tag'          => $new_tag, // Was 'trunk'
			),
		) );

		return $release_id;
	}

}
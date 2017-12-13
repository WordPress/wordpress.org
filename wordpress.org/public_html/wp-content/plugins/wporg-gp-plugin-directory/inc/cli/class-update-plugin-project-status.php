<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\CLI;

use GP;
use WordPressdotorg\GlotPress\Plugin_Directory\Plugin;
use WP_CLI;
use WP_CLI_Command;

class Update_Plugin_Project_Status extends WP_CLI_Command {

	/**
	 * Updates the active status for all plugin projects.
	 */
	public function __invoke() {
		global $wpdb;

		if ( ! defined( 'WPORG_PLUGIN_DIRECTORY_BLOGID' ) ) {
			WP_CLI::error( 'WPORG_PLUGIN_DIRECTORY_BLOGID is not defined.' );
		}

		$parent_project = GP::$project->by_path( Plugin::GP_MASTER_PROJECT );
		if ( ! $parent_project ) {
			WP_CLI::error( 'The master project doesn\'t exist.' );
		}

		$plugins = GP::$project->many( "SELECT * FROM {$wpdb->gp_projects} WHERE parent_project_id = %d", $parent_project->id );
		if ( ! $plugins ) {
			WP_CLI::error( 'No plugins found.' );
		}

		foreach ( $plugins as $plugin ) {
			$plugin_status = $this->get_plugin_status( $plugin->slug );

			if ( 'publish' === $plugin_status && ! $plugin->active ) {
				$plugin->save( [
					'active' => 1,
				] );
				WP_CLI::log( sprintf(
					'Plugin `%s` has been activated.',
					$plugin->slug
				) );
			} elseif ( 'publish' !== $plugin_status && $plugin->active ) {
				$plugin->save( [
					'active' => 0,
				] );
				WP_CLI::log( sprintf(
					'Plugin `%s` has been deactivated.',
					$plugin->slug
				) );
			}
		}
	}

	/**
	 * Retrieves the current plugin status.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return false|string Status on success, false on failure.
	 */
	protected function get_plugin_status( $plugin_slug ) {
		switch_to_blog( WPORG_PLUGIN_DIRECTORY_BLOGID );
		$posts = get_posts( [
			'post_type'   => 'plugin',
			'name'        => $plugin_slug,
			'post_status' => [ 'publish', 'pending', 'disabled', 'closed', 'new', 'draft', 'approved' ],
		] );
		restore_current_blog();

		if ( ! $posts ) {
			return false;
		}

		$plugin = array_shift( $posts );

		return $plugin->post_status;
	}
}

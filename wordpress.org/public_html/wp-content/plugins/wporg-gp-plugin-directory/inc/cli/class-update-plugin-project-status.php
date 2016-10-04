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

		if ( ! defined( 'PLUGINS_TABLE_PREFIX' ) ) {
			WP_CLI::error( 'PLUGINS_TABLE_PREFIX is not defined.' );
		}

		$parent_project = GP::$project->by_path( Plugin::GP_MASTER_PROJECT );
		if ( ! $parent_project ) {
			WP_CLI::error( 'The master project doesn\'t exist.' );
		}

		$plugins = GP::$project->many( "SELECT * FROM {$wpdb->gp_projects} WHERE parent_project_id = %d", $parent_project->id );
		if ( ! $plugins ) {
			WP_CLI::error( 'No plugins founds.' );
		}

		foreach ( $plugins as $plugin ) {
			$plugin_status = $this->get_plugin_status( $plugin->slug );

			if ( 'published' === $plugin_status && ! $plugin->active ) {
				$plugin->save( array(
					'active' => 1,
				) );
				WP_CLI::log( sprintf(
					'Plugin `%s` has been activated.',
					$plugin->slug
				) );
			} elseif ( 'published' !== $plugin_status && $plugin->active ) {
				$plugin->save( array(
					'active' => 0,
				) );
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
	 * @param $plugin_slug The plugin slug.
	 *
	 * @return false|string Status on success, false on failure.
	 */
	protected function get_plugin_status( $plugin_slug ) {
		global $wpdb;

		$topic = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . PLUGINS_TABLE_PREFIX . 'topics WHERE topic_slug = %s', $plugin_slug ) );
		if ( ! $topic ) {
			return false;
		}

		$status = 'published';
		if ( 2 == $topic->topic_open ) {
			$status = 'approved';
		} elseif ( 2 == $topic->forum_id ) {
			$status = 'pending';
		} elseif ( 4 == $topic->forum_id || 'rejected-' == substr( $topic->topic_slug, 0, 9 ) ) {
			$status = 'rejected';
		} elseif ( 1 == $topic->forum_id && 0 == $topic->topic_open ) {
			$status = 'closed';
		} elseif ( 3 == $topic->topic_open ) {
			$status = 'disabled';
		}

		return $status;
	}
}

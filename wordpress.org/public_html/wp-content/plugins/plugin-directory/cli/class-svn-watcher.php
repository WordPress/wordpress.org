<?php
namespace WordPressdotorg\Plugin_Directory\CLI;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use Exception;

/**
 * A class that watches SVN and triggers plugin imports into WordPress and GlotPress
 *
 * @package WordPressdotorg\Plugin_Directory\CLI
 */
class SVN_Watcher {

	const SVN_URL      = 'https://plugins.svn.wordpress.org/';
	const PHP          = '/usr/local/bin/php';

	/**
	 * This method is responsible for running a loop over all SVN revisions to fetch updated details.
	 */
	public function watch() {
		$svn_rev_option_name = 'svn_rev_last_processed';

		$last_rev_processed = $this->get_option( $svn_rev_option_name );
		if ( ! $last_rev_processed ) {
			throw new Exception( "Unknown Revision to parse from, please check the value of {$svn_rev_option_name} in the options table." );
		}

		$head_rev = $this->get_head_rev();
		// Don't attempt to process more than 100 revs at a time, both SimpleXML and our SVN server doesn't like it.
		if ( $head_rev > $last_rev_processed + 100 ) {
			$head_rev = $last_rev_processed + 100;
		}

		if ( $last_rev_processed >= $head_rev ) {
			// Nothing to do!
			return;
		}

		// We don't want to re-process the last rev processed, so bump past it
		$last_rev_processed++;

		echo "Processing changes from $last_rev_processed to $head_rev..\n";

		$plugins_to_process = $this->get_plugin_changes_between( $last_rev_processed, $head_rev );

		foreach ( $plugins_to_process as $plugin_slug => $plugin_data ) {
			if ( $plugin_data['assets_touched'] && ! in_array( 'trunk', $plugin_data['tags_touched'] ) ) {
				$plugin_data['tags_touched'][] = 'trunk';
			}

			Jobs\Plugin_Import::queue( $plugin_slug, $plugin_data );

			$this->update_option( $svn_rev_option_name, min( $plugin_data['revisions'] ) );
		}

		// Update it to HEAD again. We do this as $plugin_data['revision'] may be set to PREVHEAD in the event the latest 2 (or more) commits are to a single plugin.
		$this->update_option( $svn_rev_option_name, $head_rev );
	}

	/**
	 * Determines what plugin changes have happened between two revisions.
	 *
	 * @param string $rev      The revision to search from.
	 * @param string $head_rev The revision to search to. Default HEAD.
	 * @return array A list of plugin changes to process.
	 */
	protected function get_plugin_changes_between( $rev, $head_rev = 'HEAD' ) {

		$logs = SVN::log( self::SVN_URL, array( $rev, $head_rev ) );
		if ( $logs['errors'] ) {
			throw new Exception( "Could not fetch plugins.svn logs: " . implode( ', ', $logs['errors'] ) );
		}

		// nothing new to report
		if ( ! $logs['log'] ) {
			return array();
		}

		// Summarize the plugin changes down into something more useful
		$plugins = array();

		foreach ( $logs['log'] as $log ) {
			$plugin_slug = explode( '/', $log['paths'][0] )[1];

			if ( ! isset( $plugins[ $plugin_slug ] ) ) {
				$plugins[ $plugin_slug ] = array(
					'tags_touched' => array(), // trunk is a tag too!
					'readme_touched' => false, // minor optimization, only parse readme i18n on readme-related commits
					'code_touched' => false,
					'assets_touched' => false,
					'revisions' => array(),
				);
			}
			$plugin =& $plugins[ $plugin_slug ];

			// Keep track of the lowest revision number we've seen for this plugin
			$plugin['revisions'][] = $log['revision'];
			foreach ( $log['paths'] as $path ) {
				$path_parts = explode('/', trim( $path, '/' ) );

				if ( ! isset( $path_parts[1] ) ) {
					continue;
				}

				if ( 'trunk' == $path_parts[1] ) {
					$plugin['tags_touched'][] = 'trunk';

				} elseif ( 'tags' == $path_parts[1] && isset( $path_parts[2] ) ) {
					$plugin['tags_touched'][] = $path_parts[2];

				} elseif ( 'assets' == $path_parts[1] ) {
					$plugin['assets_touched'] = true;

				}

				// This will have false-positives for when a readme in a subdirectory is hit, but this is only for optimizations.
				if ( in_array( strtolower( basename( $path ) ), array( 'readme.txt', 'readme.md' ) ) ) {
					$plugin['readme_touched'] = true;
				}
				if ( ! $plugin['code_touched'] && ( '/' == substr( $path, -1 ) || '.php' == substr( $path, -4 ) ) ) {
					$plugin['code_touched'] = true;
				}

			}
			$plugin['tags_touched'] = array_unique( $plugin['tags_touched'] );
		}

		// Sort plugins by minimum revision, it should already be in this order, but double check.
		uasort( $plugins, function( $a, $b ) {
			if ( min( $a['revisions'] ) == min( $b['revisions'] ) ) {
				return 0;
			}

			return ( min( $a['revisions'] ) < min( $b['revisions'] ) ) ? -1 : 1;
		} );

		return $plugins;
	}

	/**
	 * Determines the HEAD revision of a repository.
	 *
	 * @return int The revision ID of HEAD.
	 */
	protected function get_head_rev() {
		$log = SVN::log( self::SVN_URL, 'HEAD' );
		if ( $log['errors'] || ! $log['log'] ) {
			throw new Exception( "Unable to determine HEAD revision" );
		}
		return array_keys( $log['log'] )[0];
	}

	/**
	 * An implementation of `get_option()` which doesn't utilise the cache.
	 * As this is a long-running script, we don't want to hit an alloptions race condition bug.
	 *
	 * @param string $option_name The Option to retrieve.
	 * @return mixed The option value.
	 */
	protected function get_option( $option_name ) {
		global $wpdb;
		return maybe_unserialize( $wpdb->get_var( $wpdb->prepare(
			"SELECT option_value FROM $wpdb->options WHERE option_name = %s",
			$option_name
		) ) );
	}

	/**
	 * An implementation of `update_option()` which doesn't utilise the cache.
	 * As this is a long-running script, we don't want to hit an alloptions race condition bug.
	 *
	 * @param string $option_name  The Option to update.
	 * @param mixed  $option_value The option value to update to.
	 * @return bool Whether the operation succeeeded.
	 */
	protected function update_option( $option_name, $option_value ) {
		global $wpdb;
		return (bool) $wpdb->query( $wpdb->prepare(
			"UPDATE $wpdb->options SET option_value = %s WHERE option_name = %s LIMIT 1",
			maybe_serialize( $option_value ),
			$option_name
		) );
	}

}

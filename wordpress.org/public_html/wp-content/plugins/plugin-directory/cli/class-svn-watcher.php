<?php
namespace WordPressdotorg\Plugin_Directory\CLI;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
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
	const PROCESS_I18N = false;

	public function __construct() {
		$this->watch();
	}

	/**
	 * This method is responsible for running a loop over all SVN revisions to fetch updated details.
	 */
	public function watch() {
		$svn_rev_option_name = 'svn_rev_' . php_uname( 'n' );

		$last_rev_processed = $this->get_option( $svn_rev_option_name );
		if ( ! $last_rev_processed ) {
			throw new Exception( "Unknown Revision to parse from, please check the value of {$svn_rev_option_name} in the options table." );
		}

		$head_rev = $this->get_head_rev();
		// Don't attempt to process more than 100 revs at a time, both SimpleXML and our SVN server doesn't like it.
		if ( $head_rev > $last_rev_processed + 100 ) {
			$head_rev = $last_rev_processed + 100;
		}

		if ( $last_rev_processed == $head_rev ) {
			// Nothing to do!
			return;
		}

		echo "Processing changes from $last_rev_processed to $head_rev..\n";

		$plugins_to_process = $this->get_plugin_changes_between( $last_rev_processed, $head_rev );

		foreach ( $plugins_to_process as $plugin_slug => $plugin_data ) {
			if ( $plugin_data['assets_touched'] && ! in_array( 'trunk', $plugin_data['tags_touched'] ) ) {
				$plugin_data['tags_touched'][] = 'trunk';
			}

			$esc_plugin_slug  = escapeshellarg( $plugin_slug );
			$esc_changed_tags = escapeshellarg( implode( ',', $plugin_data['tags_touched'] ) );
			$esc_revision     = escapeshellarg( $plugin_data['revision'] );

			$cmd = self::PHP . ' ' . dirname( __DIR__ ) . "/bin/import-plugin.php --plugin {$esc_plugin_slug} --changed-tags {$esc_changed_tags} --revision {$esc_revision}";

			echo "\$$cmd\n";
			echo shell_exec( $cmd ) . "\n";

			if ( self::PROCESS_I18N ) {
				$plugin     = Plugin_Directory::get_plugin_post( $plugin_slug );
				$stable_tag = $plugin->stable_tag;

				$i18n_processes = [];
				if ( in_array( 'trunk', $plugin_data['tags_touched'] ) ) {
					if ( $plugin_data['code_touched'] ) {
						$i18n_processes[] = 'trunk|code';
					}
					if ( $plugin_data['readme_touched'] ) {
						$i18n_processes[] = 'trunk|readme';
					}
				}
				if ( in_array( $stable_tag, $plugin_data['tags_touched'] ) ) {
					if ( $plugin_data['code_touched'] ) {
						$i18n_processes[] = "{$stable_tag}|code";
					}
					if ( $plugin_data['readme_touched'] ) {
						$i18n_processes[] = "{$stable_tag}|readme";
					}
				}

				$this->process_i18n_for_plugin( $plugin_slug, $i18n_processes );
			}

			$this->update_option( $svn_rev_option_name, $plugin_data['revision'] );
		}

		// Update it to HEAD again. We do this as $plugin_data['revision'] may be set to PREVHEAD in the event the latest 2 (or more) commits are to a single plugin.
		$this->update_option( $svn_rev_option_name, $head_rev );
	}

	/**
	 * Processes i18n import tasks.
	 *
	 * @param string $plugin_slug
	 * @param array $i18n_processes
	 */
	protected function process_i18n_for_plugin( $plugin_slug, $i18n_processes ) {
		foreach ( $i18n_processes as $process ) {
			list( $tag, $type ) = explode( '|', $process );

			$esc_plugin_slug = escapeshellarg( $plugin_slug );
			$esc_tag         = escapeshellarg( $tag );
			$esc_type        = escapeshellarg( $type );

			$cmd = self::PHP . ' ' . dirname( __DIR__ ) . "/bin/import-plugin-to-glotpress.php --plugin {$esc_plugin_slug} --tag {$esc_tag} --type {$esc_type}";

			echo "\n\$$cmd\n";
			echo shell_exec( $cmd ) . "\n";
		}
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
		if ( $logs['error'] ) {
			throw new Exception( "Could not fetch plugins.svn logs: " . implode( ', ', $logs['error'] ) );
		}

		// If no changes (either no log entries, or HEAD was the same as $rev)
		if ( ! count( $logs['log'] ) || ( 1 == count( $logs['log'] ) && $rev == array_keys( $logs['log'] )[0] ) ) {
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
					'revision' => PHP_INT_MAX,
				);
			}
			$plugin =& $plugins[ $plugin_slug ];

			// Keep track of the lowest revision number we've seen for this plugin
			$plugin['revision'] = min( $plugin['revision'], $log['revision'] );
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
			if ( $a['revision'] == $b['revision'] ) {
				return 0;
			}

			return ( $a['revision'] < $b['revision'] ) ? -1 : 1;
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
		if ( $log['error'] || ! $log['log'] ) {
			throw new Exception( "Unable to determine HEAD revision" );
		}
		return array_keys( $log['log'] )[0];
	}

	/**
	 * An implementation of `get_option()~ which doesn't utilise the cache.
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

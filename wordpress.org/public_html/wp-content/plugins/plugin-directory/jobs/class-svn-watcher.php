<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\CLI;
use Exception;

/**
 * Watch SVN changesets and queue up jobs to import the changed plugins.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class SVN_Watcher {

	/**
	 * The cron trigger for the svn import job.
	 */
	public static function cron_trigger() {
		$watcher = new CLI\SVN_Watcher();
		$watcher->watch();
	}

}

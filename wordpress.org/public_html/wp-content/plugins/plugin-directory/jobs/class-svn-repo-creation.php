<?php
namespace WordPressdotorg\Plugin_Directory\Jobs;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Admin\Status_Transitions;


/**
 * Create a SVN repo after a plugin is approved. This is only used when the initial creation fails.
 *
 * @package WordPressdotorg\Plugin_Directory\Jobs
 */
class SVN_Repo_Creation {

	public static function cron_trigger( $post_id, $author_id = null ) {
		$post = get_post( $post_id );

		// Check to see if the repo exists..
		$exists = SVN::ls( 'http://plugins.svn.wordpress.org/' . $post->post_name . '/' );
		if ( $exists ) {
			return;
		}

		$created = Status_Transitions::instance()->approved_create_svn_repo( $plugin_id, $author_id );
		if ( $created ) {
			Tools::audit_log( 'Created SVN Repository.', $post->ID );
		}
	}

}

<?php
/**
 * This webhook is very basic, but handles syncing a select few featured plugins over to plugins.svn.wordpress.org during development.
 *
 * To have your *feature project* handled by this sync script:
 * - Ensure you're listed on https://make.wordpress.org/core/features-as-plugins/ if you're not, ping in #meta
 * - Ping in #meta to get your project added to the whitelist within this file
 * - Add `https://api.wordpress.org/dotorg/github-sync/feature-plugins.php` as a `push` webhook on github with the secret we'll provide you.
 * - Push to github master and watch https://plugins.trac.wordpress.org/log/$plugin_slug/
 * - Profit from all the Open Source points you're getting!
 */

require dirname( dirname( __DIR__ ) ) . '/init.php';

class GH2WORG {

	private $whitelisted_repos = [
		// Github.com/{$user/repo-name} => plugins.svn.wordpress.org/{$slug}/trunk/
		'dd32/feature-plugin-testing'           => 'test-plugin-3',
		'georgestephanis/two-factor'            => 'two-factor',
		'georgestephanis/application-passwords' => 'application-passwords',
		'obenland/shiny-updates'                => 'shiny-updates',
		'pento/react'                           => 'react',
	];

	function __construct() {
		$repo_name = $this->get_notified_repo();

		$svn_directory = $this->whitelisted_repos[ $repo_name ];

		if ( ! $repo_name || ! $svn_directory ) {
			die( 'Sorry, This Github repo is not configured for WordPress.org Plugins SVN Github Sync. Please ping in #meta on Slack for assistance.' );
		}

		$this->process_github_to_svn( $repo_name, $svn_directory );
	}

	/**
	 * Determines the Github Repo (user/repo) which Github is notifying us about.
	 *
	 * - Extracts the repo from both `application/json` and `application/x-www-form-urlencoded` webhook variants.
	 * - Verifies the signature of the request
	 *
	 * @return string|bool Github repo on success, false on failure.
	 */
	function get_notified_repo() {
		$github_payload = file_get_contents( 'php://input' );
		$signature_of_payload = 'sha1=' . hash_hmac( 'sha1', $github_payload, FEATURE_PLUGIN_GH_SYNC_SECRET );

		if ( ! hash_equals( $signature_of_payload, $_SERVER['HTTP_X_HUB_SIGNATURE'] ) ) {
			return false;
		}

		/*
		 * Extract the payload from the `php://input` stream, although this is also present in
		 * $_POST, the Github signature is of this raw data, so we'll use that data.
		 */
		if ( 'application/x-www-form-urlencoded' === $_SERVER['HTTP_CONTENT_TYPE'] ) {
			parse_str( $github_payload, $github_payload );
			$github_payload = $github_payload['payload'];
		}

		return json_decode( $github_payload, true )['repository']['full_name'];
	}

	/**
	 * Triggers the shell script to migrate the Git commit to plugins.svn
	 *
	 * @param string $github_repo   The Github Repo which was modified (user/repo).
	 * @param string $svn_directory The plugins.svn directory/plugin (plugin-slug).
	 */
	function process_github_to_svn( $github_repo, $svn_directory ) {

		putenv( 'PHP_SVN_USER='     . FEATURE_PLUGIN_GH_SYNC_USER );
		putenv( 'PHP_SVN_PASSWORD=' . FEATURE_PLUGIN_GH_SYNC_PASS );

		$github_repo   = escapeshellarg( $github_repo );
		$svn_directory = escapeshellarg( $svn_directory );

		echo shell_exec( __DIR__ . "/feature-plugins.sh $github_repo $svn_directory 2>&1" );

		putenv( 'PHP_SVN_USER' );
		putenv( 'PHP_SVN_PASSWORD' );
	}

}
new GH2WORG();

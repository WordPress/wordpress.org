<?php
/**
 * A easy webhook to allow feature plugins to be sync'd to WordPress.org plugins SVN easier.
 */

require dirname( dirname( __DIR__ ) ) . '/init.php';

class GH2WORG {

	private $whitelisted_repos = array(
		// Github User/Repo => plugins.svn.wordpress.org/****/trunk/
		'dd32/feature-plugin-testing' => 'test-plugin-3',
		'MichaelArestad/Press-This' => 'press-this',
		'pento/x1f4a9' => 'x1f4a9',
	);

	function __construct() {
		$this->populate_post_vars();

		if ( ! $this->verify_github_signature() ) {
			die( 'Cheating, huh?' );
		}

		$repo_name = $_POST['repository']['full_name'];
		$repo_url  = $_POST['repository']['git_url'];

		if ( ! $this->verify_valid_plugin( $repo_name ) ) {
			die( 'Sorry, This Github repo is not configured for WordPress.org Plugins SVN Github Sync. Please contact us.' );
		}

		$svn_directory = $this->whitelisted_repos[ $repo_name ];

		$this->process_github_to_svn( $repo_url, $svn_directory );
	}

	function populate_post_vars() {
		if ( 'application/json' == $_SERVER['HTTP_CONTENT_TYPE'] ) {
			$_POST = @json_decode( file_get_contents('php://input'), true );
		} else {
			// Assuming Magic Quotes disabled like a good host.
			$_POST = @json_decode( $_POST['payload'], true );
		}
	}

	function verify_github_signature() {
		if ( empty( $_SERVER['HTTP_X_HUB_SIGNATURE'] ) )
			return false;

		list( $algo, $hash ) = explode( '=', $_SERVER['HTTP_X_HUB_SIGNATURE'], 2 );

		// Todo? Doesn't handle standard $_POST, only application/json
		$hmac = hash_hmac( $algo, file_get_contents('php://input' ), FEATURE_PLUGIN_GH_SYNC_SECRET );

		return $hash === $hmac;
	}

	function verify_valid_plugin( $repo ) {
		return isset( $this->whitelisted_repos[ $repo ] );
	}

	function process_github_to_svn( $github_url, $svn_directory ) {

		putenv( 'PHP_SVN_USER=' . FEATURE_PLUGIN_GH_SYNC_USER );
		putenv( 'PHP_SVN_PASSWORD=' . FEATURE_PLUGIN_GH_SYNC_PASS );

		echo shell_exec( __DIR__ . "/feature-plugins.sh $github_url $svn_directory 2>&1" );

		putenv( 'PHP_SVN_USER' );
		putenv( 'PHP_SVN_PASSWORD' );
	}

}
new GH2WORG();
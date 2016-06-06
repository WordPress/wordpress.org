<?php
namespace WordPressdotorg\Plugin_Directory\API\Routes;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\API\Base;

/**
 * SVN is a fascinating system, plugins.svn.wordpress.org is a fascinating implementation of it.
 *
 * We need to generate the SVN access file from the database, so that the SVN server knows who has permission.
 *
 * This API is not designed for public usage.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class SVN_Access extends Base {

	protected $svn_access_table;

	public function __construct() {
		$this->svn_access_table = PLUGINS_TABLE_PREFIX . 'svn_access';

		register_rest_route( 'plugins/v1', '/svn-access', array(
			'methods'  => \WP_REST_Server::READABLE,
			'callback' => array( $this, 'generate_svn_access' ),
			'permission_callback' => array( $this, 'permission_check_internal_api_bearer' ),
		) );
	}

	/**
	 * Generates and prints the SVN access file for plugins.svn.
	 *
	 * Rather than returning a value, the file is echo'd directly to STDOUT, so it can be piped
	 * directly into a file. It exit()'s immediately.
	 *
	 * @param \WP_REST_Request $request The Rest API Request.
	 *
	 * @return bool false This method will return false if the SVN access file couldn't be generated.
	 */
	public function generate_svn_access( $request ) {
		$svn_access = $this->load_svn_access();

		if ( empty( $svn_access ) ) {
			return false;
			}

		foreach ( $svn_access as $slug => $users ) {
			$slug = ltrim( $slug, '/' );
			echo "\n[/$slug]\n";

			foreach ( $users as $user => $access ) {
				echo "$user = $access\n";
			}
		}

		exit();
	}


	/**
	 * Loads the SVN access data from the svn access table.
	 *
	 * @access private
	 *
	 * @return array SVN access data, keyed by repo, then username.
	 */
	private function load_svn_access() {
		global $wpdb;

		$svn_access = array();

		$access_data = (array) $wpdb->get_results( "SELECT * FROM {$this->svn_access_table}" );

		foreach ( $access_data as $datum ) {
			if ( ! isset( $svn_access[ $datum->path ] ) ) {
				$svn_access[ $datum->path ] = array();
			}

			$svn_access[ $datum->path ][ $datum->user ] = $datum->access;
		}

		return $svn_access;
	}
}

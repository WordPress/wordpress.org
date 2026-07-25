<?php
/**
 * Base test case for the plugins/v1 REST API endpoint tests.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Shared helpers for dispatching requests against the plugins/v1 endpoints.
 */
abstract class Plugin_Directory_Endpoint_TestCase extends TestCase {

	/**
	 * Defines the constants the endpoints expect from wp-config.
	 */
	public static function setUpBeforeClass(): void {
		parent::setUpBeforeClass();

		/*
		 * The SVN_Access route requires this constant at registration time;
		 * it is defined in wp-config on WordPress.org.
		 */
		if ( ! defined( 'PLUGINS_TABLE_PREFIX' ) ) {
			define( 'PLUGINS_TABLE_PREFIX', $GLOBALS['wpdb']->prefix );
		}
	}

	/**
	 * Returns the REST server with all routes registered.
	 *
	 * @return \WP_REST_Server
	 */
	protected static function server() {
		return rest_get_server();
	}

	/**
	 * Creates a plugin post.
	 *
	 * The post_modified fields must be passed explicitly, as the plugin
	 * copies them from the raw postarr via filter_wp_insert_post_data(),
	 * and directory queries INNER JOIN on the _active_installs meta.
	 *
	 * @param string $slug  The plugin slug.
	 * @param string $title The plugin title.
	 * @param array  $args  Optional. Overrides for the post array.
	 * @return int The post ID.
	 */
	protected static function create_plugin( $slug, $title, $args = array() ) {
		$defaults = array(
			'post_type'         => 'plugin',
			'post_status'       => 'publish',
			'post_name'         => $slug,
			'post_title'        => $title,
			'post_modified'     => current_time( 'mysql' ),
			'post_modified_gmt' => current_time( 'mysql', true ),
			'meta_input'        => array(
				'_active_installs' => 0,
			),
		);

		return wp_insert_post( wp_parse_args( $args, $defaults ) );
	}
}

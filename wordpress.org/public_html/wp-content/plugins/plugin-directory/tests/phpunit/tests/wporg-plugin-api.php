<?php

require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

/**
 *
 * @group plugins-api
 */
class Tests_Plugins_API extends WP_UnitTestCase {

	public $api_endpoint_1_0 = 'http://api.wordpress.org/plugins/info/1.0/';
	public $api_endpoint_1_1 = 'http://api.wordpress.org/plugins/info/1.1/';
	public $api_endpoint_plugin_php = 'http://api.wordpress.org/plugins/info/1.0/jetpack.php';
	public $api_endpoint_plugin_xml = 'http://api.wordpress.org/plugins/info/1.0/jetpack.xml';
	public $api_endpoint_plugin_json = 'http://api.wordpress.org/plugins/info/1.0/jetpack.json';

	public $user_agent = 'WordPress/4.8'; // Tell the API to use the v3 back-end
	public $require_tested_value = true;

	public $fields = array(
		'short_description' => true,
		'description'       => true,
		'sections'          => true,
		'tested'            => true,
		'requires'          => true,
		'rating'            => true,
		'ratings'           => true,
		'downloaded'        => true,
		'downloadlink'      => true,
		'last_updated'      => true,
		'added'             => true,
		'tags'              => true,
		'compatibility'     => true,
		'homepage'          => true,
		'versions'          => true,
		'stable_tag'        => true,
		'donate_link'       => true,
		'reviews'           => true,
		'banners'           => true,
		'icons'             => true,
		'active_installs'   => true,
		'contributors'      => true,
		'donate_link'		=> true,
	);

	function setUp() {
		parent::setUp();
		add_filter( 'http_headers_useragent', array( $this, 'filter_http_headers_useragent' ) );
	}

	function tearDown() {
		remove_filter( 'http_headers_useragent', array( $this, 'filter_http_headers_useragent' ) );
		parent::tearDown();
	}

	// Override the user-agent header in plugins_api() requests to force the API to use the new WP codebase.
	function filter_http_headers_useragent( $user_agent ) {
		return $this->user_agent;
	}

	function api_remote_post( $url, $action, $request ) {
		return wp_remote_post( $url, array(
			'timeout' => 15,
			'body'    => array(
				'action'  => $action,
				'request' => serialize( (object) $request ),
			),
			'headers' => array( 'Host', 'api.wordpress.org' ),
		) );
	}

	function api_remote_get( $url ) {
		return wp_remote_get( $url, array(
			'timeout' => 15,
			'headers' => array( 'Host', 'api.wordpress.org' ),
		) );
	}

	function test_wporg_plugin_api_serialize_php() {
		$response = $this->api_remote_post( $this->api_endpoint_1_0, 'plugin_information', array( 'slug' => 'jetpack', 'fields' => $this->fields ) );
		$plugins = maybe_unserialize( wp_remote_retrieve_body( $response ) );

		$this->_check_response_attributes( $plugins );
	}

	function test_wporg_plugin_api_serialize_php_get() {
		$url      = add_query_arg( 'fields', implode( ',' , array_keys( $this->fields ) ), $this->api_endpoint_plugin_php );
		$response = $this->api_remote_get( $url );
		$plugins  = maybe_unserialize( wp_remote_retrieve_body( $response ) );

		$this->_check_response_attributes( $plugins );
	}

	function test_wporg_plugin_api_xml() {
		$url      = add_query_arg( 'fields', implode( ',' , array_keys( $this->fields ) ), $this->api_endpoint_plugin_xml );
		$response = $this->api_remote_get( $url );
		$plugins  = wp_remote_retrieve_body( $response );

		// TODO: validate XML response.
		$this->markTestSkipped( 'Needs XML parsing.' );
		$this->_check_response_attributes( $plugins );
	}

	function test_wporg_plugin_api_json() {
		$url      = add_query_arg( 'fields', implode( ',' , array_keys( $this->fields ) ), $this->api_endpoint_plugin_json );
		$response = $this->api_remote_get( $url );
		$plugins  = (object) json_decode( wp_remote_retrieve_body( $response ), true );

		$this->_check_response_attributes( $plugins );
	}

	function test_wporg_plugin_api_1_1_json() {
		$response = wp_remote_post( $this->api_endpoint_1_1, array(
			'timeout' => 15,
			'body'    => array(
				'action'  => 'plugin_information',
				'request' => (object) array( 'slug' => 'jetpack', 'fields' => $this->fields ),
			),
			'headers' => array( 'Host', 'api.wordpress.org' ),

		) );

		$plugins = (object) json_decode( wp_remote_retrieve_body( $response ), true );
		$this->_check_response_attributes( $plugins );
	}

	function test_plugins_api_function_action_plugin_information() {
		$plugins = plugins_api( 'plugin_information', array( 'slug' => 'jetpack', 'fields' => $this->fields ) );
		$this->_check_response_attributes( $plugins );
	}

	function test_plugins_api_function_action_query_plugins_search() {
		$slug     = 'hello-dolly';
		$per_page = 4;
		$page     = 2;
		$plugins  = plugins_api( 'query_plugins', array(
			'search'   => $slug,
			'per_page' => $per_page,
			'page'     => $page,
			'fields'   => $this->fields,
		) );

		$this->_check_response_plugin_query( $plugins, $per_page, $page );

		// If search term exactly matches a slug, it should be returned first.
		$plugins  = plugins_api( 'query_plugins', array( 'search' => $slug ) );
		$this->assertEquals( $plugins->plugins[0]->slug, $slug );
	}

	// Plugins with a specific tag.
	function test_plugins_api_function_action_query_plugins_tag() {
		$tag     = 'widget';
		$plugins = plugins_api( 'query_plugins', array( 'tag' => $tag, 'fields' => $this->fields ) );

		foreach ( $plugins->plugins as $plugin ) {
			$this->assertArrayHasKey( $tag, $plugin->tags, "Contains tag $tag" );
		}
		$this->_check_response_plugin_query( $plugins );
	}

	// Plugins written by a specific author.
	function test_plugins_api_function_action_query_plugins_author() {
		$author  = 'wordpressdotorg';
		$plugins = plugins_api( 'query_plugins', array( 'author' => $author, 'fields' => $this->fields ) );

		foreach ( $plugins->plugins as $plugin ) {
			$this->assertArrayHasKey( $author, $plugin->contributors, "Contains author $author" );
		}
		$this->_check_response_plugin_query( $plugins, 1 );
	}

	// Favorites.
	function test_plugins_api_function_action_query_plugins_user() {
		$plugins = plugins_api( 'query_plugins', array( 'user' => 'markjaquith', 'fields' => $this->fields ) );
		$this->_check_response_plugin_query( $plugins, 1 );
	}

	function test_plugins_api_function_action_query_plugins_browse() {
		$plugins = plugins_api( 'query_plugins', array( 'browse' => 'popular', 'fields' => $this->fields ) );
		$this->_check_response_plugin_query( $plugins );
	}

	function test_plugins_api_function_action_query_plugins_installed_plugins() {
		$plugins = plugins_api( 'query_plugins', array( 'browse' => 'recommended', 'installed_plugins' => array( 'jetpack' ), 'fields' => $this->fields ) );
		$this->_check_response_plugin_query( $plugins, 1 );
	}

	function test_plugins_api_function_action_query_plugins_local() {
		// Not yet implemented. Shouldn't change the structure of the response though.
		$plugins = plugins_api( 'query_plugins', array( 'local' => 'hello', 'fields' => $this->fields ) );
		$this->_check_response_plugin_query( $plugins, 1 );
	}

	function test_plugins_api_function_action_hot_tags() {
		$number  = 3;
		$plugins = plugins_api( 'hot_tags', array( 'number' => $number ) );

		$this->assertEquals( $number, count( $plugins ) );
		$this->assertInternalType( 'array', $plugins, 'Response array is array' );

		foreach ( $plugins as $hot_tag => $tag_array ) {
			$this->assertInternalType( 'array', $tag_array, 'Tag array is array' );
			$this->assertArrayHasKey( 'name', $tag_array, 'Name exists' );
			$this->assertArrayHasKey( 'slug', $tag_array, 'Slug exists' );
			$this->assertArrayHasKey( 'count', $tag_array, 'Count exists' );

			// Only check the first result.
			break;
		}
	}

	function _check_response_plugin_query( $plugin_query, $per_page = 24, $page = 1 ) {
		$this->assertObjectHasAttribute( 'info', $plugin_query, 'Info exists' );
		$info = $plugin_query->info;

		$this->assertArrayHasKey( 'page', $info, 'Page exists' );
		$this->assertEquals( $info['page'], $page, 'Page equals to ' . $page );

		$this->assertArrayHasKey( 'pages', $info, 'Pages exists' );
		$this->assertArrayHasKey( 'results', $info, 'Results exists' );

		// Plugins.
		$this->assertObjectHasAttribute( 'plugins', $plugin_query, 'Plugins exists' );
		$this->assertAttributeInternalType( 'array', 'plugins', $plugin_query, 'Plugins should be an array' );

		$this->greaterThanOrEqual( count( $plugin_query->plugins ), $per_page );

		$this->_check_response_attributes( $plugin_query->plugins[0] );
	}

	function _check_response_attributes( $plugin_info ) {
		$this->assertObjectHasAttribute( 'name', $plugin_info, 'Name exists' );
		$this->assertObjectHasAttribute( 'slug', $plugin_info, 'Slug exits' );
		$this->assertObjectHasAttribute( 'version', $plugin_info, 'Version exists' );
		$this->assertObjectHasAttribute( 'author', $plugin_info, 'Author exists' );
		$this->assertObjectHasAttribute( 'author_profile', $plugin_info, 'Author Profile exists' );
		$this->assertObjectHasAttribute( 'contributors', $plugin_info, 'Contributors exists' );
		$this->assertAttributeInternalType( 'array', 'contributors', $plugin_info, 'Contributors should be an array' );
		$this->assertObjectHasAttribute( 'requires', $plugin_info, 'Requires exists' );
		if ( $this->require_tested_value || isset( $plugin_info->tested ) ) {
			$this->assertObjectHasAttribute( 'tested', $plugin_info, 'Tested exists' );
			$this->assertAttributeInternalType( 'string', 'tested', $plugin_info, 'Tested should be a string' );
		}
		$this->assertObjectHasAttribute( 'compatibility', $plugin_info, 'Compatibility exists' );
		$this->assertAttributeInternalType( 'array', 'compatibility', $plugin_info, 'Compatibility should be an array' );

		// Ratings.
		$this->assertObjectHasAttribute( 'rating', $plugin_info, 'Rating exists' );
		$this->assertObjectHasAttribute( 'ratings', $plugin_info, 'Ratings exists' );
		$this->assertAttributeInternalType( 'array', 'ratings', $plugin_info, 'Ratings should be an array' );
		$this->assertEquals( array( 5, 4, 3, 2, 1 ), array_keys( $plugin_info->ratings ), 'Ratings should be ordered from 5 to 1' );
		$this->assertArrayHasKey( '1', $plugin_info->ratings, 'Rating should have an attribute of 1' );
		$this->assertArrayHasKey( '2', $plugin_info->ratings, 'Rating should have an attribute of 2' );
		$this->assertArrayHasKey( '3', $plugin_info->ratings, 'Rating should have an attribute of 3' );
		$this->assertArrayHasKey( '4', $plugin_info->ratings, 'Rating should have an attribute of 4' );
		$this->assertArrayHasKey( '5', $plugin_info->ratings, 'Rating should have an attribute of 5' );
		$this->assertObjectHasAttribute( 'num_ratings', $plugin_info, 'Num ratings exists' );
		$this->assertTrue( is_numeric( $plugin_info->num_ratings ), 'Num ratings are numeric' );

		$this->assertObjectHasAttribute( 'active_installs', $plugin_info, 'Active Installs exists' );
		$this->assertAttributeInternalType( 'integer', 'active_installs', $plugin_info, 'Active Installs should be an integer' );
		$this->assertObjectHasAttribute( 'downloaded', $plugin_info, 'Active Installs exists' );
		$this->assertAttributeInternalType( 'integer', 'downloaded', $plugin_info, 'Downloaded should be an integer' );

		$this->assertObjectHasAttribute( 'last_updated', $plugin_info, 'Last Updated exists' );
		$this->assertAttributeInternalType( 'string', 'last_updated', $plugin_info, 'Last Updated should be a string' );
		$this->assertObjectHasAttribute( 'added', $plugin_info, 'Added exists' );
		$this->assertAttributeInternalType( 'string', 'short_description', $plugin_info, 'Added should be a string' );
		if ( function_exists( 'date_create_from_format' ) ) {
			$last_updated_date = DateTime::createFromFormat( 'Y-m-d g:ia \G\M\T', $plugin_info->last_updated );
			$date_time_errors  = DateTime::getLastErrors();
			$this->assertTrue( $last_updated_date && 0 == $date_time_errors['warning_count'] && 0 == $date_time_errors['error_count'], 'Last updated has a valid format' );

			$added_date       = DateTime::createFromFormat( 'Y-m-d', $plugin_info->added );
			$date_time_errors = DateTime::getLastErrors();
			$this->assertTrue( $added_date && 0 == $date_time_errors['warning_count'] && 0 == $date_time_errors['error_count'], 'Added has a valid format' );
		}

		$this->assertObjectHasAttribute( 'homepage', $plugin_info, 'Homepage exists' );
		$this->assertAttributeInternalType( 'string', 'homepage', $plugin_info, 'Homepage should be a string' );
		$this->assertObjectHasAttribute( 'sections', $plugin_info, 'Sections exists' );
		$this->assertAttributeInternalType( 'array', 'sections', $plugin_info, 'Sections should be an array' );

		$this->assertObjectHasAttribute( 'description', $plugin_info, 'Description exists' );
		$this->assertAttributeInternalType( 'string', 'description', $plugin_info, 'Description should be a string' );
		$this->assertObjectHasAttribute( 'short_description', $plugin_info, 'Short Description exists' );
		$this->assertAttributeInternalType( 'string', 'short_description', $plugin_info, 'Short Description should be a string' );

		$this->assertObjectHasAttribute( 'download_link', $plugin_info, 'Download link exists' );
		$this->assertAttributeInternalType( 'string', 'download_link', $plugin_info, 'Download link should be a string' );
		$this->assertFalse( empty( $plugin_info->download_link ), 'Download link should have a value' );

		$this->assertObjectHasAttribute( 'tags', $plugin_info, 'Tags exists' );
		$this->assertAttributeInternalType( 'array', 'tags', $plugin_info, 'Tags should be an array' );

		$this->assertObjectHasAttribute( 'stable_tag', $plugin_info, 'Stable tag exists' );
		$this->assertAttributeInternalType( 'string', 'stable_tag', $plugin_info, 'Stable tag should be a string' );
		$this->assertFalse( empty( $plugin_info->stable_tag ), 'Stable tag should have a value' );

		$this->assertObjectHasAttribute( 'versions', $plugin_info, 'Versions exists' );
		$this->assertAttributeInternalType( 'array', 'versions', $plugin_info, 'Versions should be an array' );

		$this->assertObjectHasAttribute( 'donate_link', $plugin_info, 'Donate link exists' );
		$this->assertAttributeInternalType( 'string', 'donate_link', $plugin_info, 'Donate link should be a string' );

		$this->assertObjectHasAttribute( 'banners', $plugin_info, 'Banners exists' );
		$this->assertAttributeInternalType( 'array', 'banners', $plugin_info, 'Banners should be an array' );

		$this->assertObjectHasAttribute( 'icons', $plugin_info, 'Icons exists' );
		$this->assertAttributeInternalType( 'array', 'icons', $plugin_info, 'Icons should be an array' );
	}
}

/**
 *
 * @group plugins-api
 */

class Tests_Plugins_API_Old extends Tests_Plugins_API {
	public $user_agent = 'WordPress/4.7'; // Tell the API to use the old back-end
	public $require_tested_value = false; // Old API omits 'tested' if its value is empty


}
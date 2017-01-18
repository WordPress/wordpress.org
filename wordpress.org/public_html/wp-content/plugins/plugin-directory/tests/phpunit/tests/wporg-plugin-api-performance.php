<?php

require_once ABSPATH . 'wp-admin/includes/plugin-install.php';


/**
 * @test
 * @group plugins-api
 * @group performance
 */

class Tests_Plugins_API_Performance extends WP_UnitTestCase {

	public $api_endpoint_1_0 = 'http://api.wordpress.org/plugins/info/1.0/';
	public $api_endpoint_1_1 = 'http://api.wordpress.org/plugins/info/1.1/';
	public $api_endpoint_plugin_php = 'http://api.wordpress.org/plugins/info/1.0/jetpack.php';
	public $api_endpoint_plugin_xml = 'http://api.wordpress.org/plugins/info/1.0/jetpack.xml';
	public $api_endpoint_plugin_json = 'http://api.wordpress.org/plugins/info/1.0/jetpack.json';

	public $user_agent = 'WordPress/4.8'; // Tell the API to use the v3 back-end
	public $timeout_seconds = 5.0;

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
	);

	function setUp() {
		parent::setUp();
		add_filter( 'http_headers_useragent', array( $this, 'filter_http_headers_useragent' ) );
	}

	function tearDown() {
		remove_filter( 'http_headers_useragent', array( $this, 'filter_http_headers_useragent' ) );
		parent::tearDown();
	}

	static function averages( $values, $decimals = 4 ) {
		sort( $values, SORT_NUMERIC );
		$mean = array_sum( $values ) / count( $values );

		$median = $values[ floor( count( $values ) / 2 ) ];

		return "mean ". number_format( $mean, $decimals ) . ", median " . number_format( $median, $decimals );

	}

	static function tearDownAfterClass() {
		global $wporg_plugin_api_performance;

		echo "Performance summary for ". get_called_class() . ":\n";
		foreach ( $wporg_plugin_api_performance[ get_called_class() ] as $type => $deltas ) {
			echo "$type: " . self::averages( $deltas ) . "\n";
		}
	}

	// Override the user-agent header in plugins_api() requests to force the API to use the new WP codebase.
	function filter_http_headers_useragent( $user_agent ) {
		return $this->user_agent;
	}

	function api_remote_post( $url, $action, $request ) {
		return wp_remote_post( $url, array(
			'timeout' => $this->timeout_seconds,
			'body'    => array(
				'action'  => $action,
				'request' => serialize( (object) $request ),
			),
			'headers' => array( 'Host', 'api.wordpress.org' ),
		) );
	}

	function api_remote_get( $url ) {
		return wp_remote_get( $url, array(
			'timeout' => $this->timeout_seconds,
			'headers' => array( 'Host', 'api.wordpress.org' ),
		) );
	}

	function performanceTestProvider() {

		// The 50 most common plugin tags
		$common_terms = explode( ' ', 'widget post admin woocommerce posts comments shortcode twitter google images facebook sidebar image seo page gallery social email links login ecommerce widgets video rss buddypress pages jquery spam content security ajax media slider feed category search analytics menu embed javascript e-commerce link css form comment share youtube custom categories theme');

		// Each item represents the function arguments to a plugins_api() call.
		$r = array(
			array( 'plugin_information', array( 'slug' => 'jetpack', 'fields' => $this->fields ) ),
			array( 'query_plugins', array( 'browse' => 'updated', 'per_page' => 24, 'page'=> 103, 'fields' => $this->fields )),
			array( 'query_plugins', array( 'user' => 'dd32', 'fields' => $this->fields )),
			array( 'query_plugins', array( 'browse' => 'popular', 'per_page' => 20, 'fields' => $this->fields ) ),
			array( 'query_plugins', array( 'browse' => 'recommended', 'installed_plugins' => array( 'akismet', 'jetpack' ), 'fields' => $this->fields ) )
		);

		// 20 random searches
		for ( $i=0; $i < 20; $i++ ) {
			$random_search = join( ' ', array_rand( array_flip( $common_terms ), 3 ) ); // 3 random terms
			$r[] = array( 'query_plugins', array( 'search'   => $random_search, 'per_page' => 15, 'page' => 3, 'fields' => $this->fields ) );
		}

		// 100 recently updated plugins
		$recently_updated_plugins = plugins_api( 'query_plugins',
				array(
					'browse' => 'updated',
					'fields' => array( 'description' => false, 'short_description' => false ),
					'per_page' => 100,
					'page' => rand(0 , 400),
					)
				);
		$this->assertFalse( is_wp_error( $recently_updated_plugins ) );
		$this->assertEquals( 100, count( $recently_updated_plugins->plugins ));
		foreach ( $recently_updated_plugins->plugins as $plugin )
			$r[] = array( 'plugin_information', array( 'slug' => $plugin->slug, 'fields' => $this->fields ) );

		return $r;

	}

	/**
	 *
	 * @dataProvider performanceTestProvider
	 */
	 function test_api_performance( $action, $args ) {
	 	global $wporg_plugin_api_performance;

		$start = microtime( true );
		$response = plugins_api( $action, $args );
		$delta = microtime( true ) - $start;

		#error_log( get_class( $this ) . ": $action ". array_keys($args)[0]." ".array_values($args)[0]." took $delta" );
		$wporg_plugin_api_performance[ get_class( $this ) ][ $action . ' ' . array_keys($args)[0] ][] = $delta;

		$this->assertLessThan( $this->timeout_seconds, $delta, "API call $action took $delta seconds" );

		// Make sure it has returned a result of some kind and not an error or empty set
		$this->assertFalse( is_wp_error( $response ), "API call $action returned error" );
		if ( $action === 'plugin_information' ) {
			$this->assertObjectHasAttribute( 'slug', $response, 'Slug exits' );
		} else {
			$this->assertObjectHasAttribute( 'info', $response, 'Info exists' );
			$this->assertObjectHasAttribute( 'plugins', $response, 'Plugins exists' );
			$this->assertAttributeInternalType( 'array', 'plugins', $response, 'Plugins should be an array' );
			$this->greaterThanOrEqual( count( $response->plugins ), 1 );
		}
	}

}

/**
 * @test
 * @group plugins-api
 * @group performance
 */

class Tests_Plugins_API_Performance_Old extends Tests_Plugins_API_Performance {
	public $user_agent = 'WordPress/4.7'; // Tell the API to use the v3 back-end
}

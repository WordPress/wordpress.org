<?php
namespace WordPressdotorg\API\Serve_Happy;
use PHPUnit_Framework_TestCase;

/**
 * @group serve-happy
 */
class Tests_API_Responses extends PHPUnit_Framework_TestCase {

	function dataprovider_determine_request_valid() {
		return [
			[
				[ 'php_version' => '5.2.9' ],
				[ 'php_version' => '5.2.9' ]
			],
			[
				[ 'php_version' => '5.3.2-0.dotdeb.2' ],
				[ 'php_version' => '5.3.2' ]
			],
			[
				[
					'php_version' => '5.3.2-0.dotdeb.2',
					'mysql_version' => 'MySQL',
				],
				[ 'php_version' => '5.3.2' ]
			],
		];
	}

	/**
	 * @dataProvider dataprovider_determine_request_valid
	 */
	function test_determine_request_valid( $input, $expected ) {
		$output = determine_request( $input );

		$this->assertSame( $expected, $output );
	}

	/**
	 * @dataProvider dataprovider_determine_request_valid
	 * @group serve-happy-live-http
	 */
	function test_determine_request_valid_live( $input, $expected ) {
		$output = json_decode( $this->helper_api_request( $input ), true );

		// Assert that it's a successful response, and not an error.
		$this->assertArrayHasKey( 'recommended_version', $output );
		$this->assertArrayHasKey( 'is_supported', $output );
		$this->assertArrayHasKey( 'is_secure', $output );
		$this->assertArrayHasKey( 'is_acceptable', $output );
	}

	function dataprovider_determine_request_invalid() {
		return [
			[
				[],
				[
					'code'    => 'missing_param',
					'message' => 'Missing parameter: php_version',
					'status'  => 400
				]
			],
			[
				[ 'php' => '7.0' ],
				[
					'code'    => 'missing_param',
					'message' => 'Missing parameter: php_version',
					'status'  => 400
				]
			],
			[
				[ 'php_version' => '7' ],
				[
					'code'    => 'invalid_param',
					'message' => 'Invalid parameter: php_version',
					'status'  => 400
				]
			],
			[
				[ 'php_version' => 'not.a.version' ],
				[
					'code'    => 'invalid_param',
					'message' => 'Invalid parameter: php_version',
					'status'  => 400
				]
			],
		];
	}

	/**
	 * @dataProvider dataprovider_determine_request_invalid
	 */
	function test_determine_request_invalid( $input, $expected ) {
		$output = determine_request( $input );

		$this->assertSame( $expected, $output );
	}

	/**
	 * @dataProvider dataprovider_determine_request_invalid
	 * @group serve-happy-live-http
	 */
	function test_determine_request_invalid_live( $input, $expected ) {
		$output = json_decode( $this->helper_api_request( $input ), true );

		$this->assertSame( $expected, $output );
	}

	function dataprovider_parse_request_valid() {
		// Test recommended PHP version is always returned.
		$data = [
			[
				[ 'php_version' => RECOMMENDED_PHP ],
				[ 'recommended_version' => RECOMMENDED_PHP ]
			],
			[
				[ 'php_version' => '5.2' ],
				[ 'recommended_version' => RECOMMENDED_PHP ]
			],
		];

		// Test individual PHP versions.
		$flags = [
			'is_supported'  => 'SUPPORTED_PHP',
			'is_secure'     => 'SECURE_PHP',
			'is_acceptable' => 'ACCEPTABLE_PHP'
		];
		foreach ( $flags as $flag => $constant_name ) {
			$data[] = [
				[ 'php_version' => constant( $constant_name ) ],
				[ $flag => true ]
			];
			$data[] = [
				[ 'php_version' => constant( $constant_name ) + 0.1 ],
				[ $flag => true ]
			];
			$data[] = [
				[ 'php_version' => constant( $constant_name ) - 0.1 ],
				[ $flag => false ]
			];
		}
		return $data;
	}

	/**
	 * @dataProvider dataprovider_parse_request_valid
	 */
	function test_parse_request_valid( $input, $expected ) {
		$output = parse_request( $input );

		// Only check the response keys we're actually checking.
		$output = array_intersect_key( $output, $expected );

		$this->assertSame( $expected, $output );
	}

	/**
	 * @dataProvider dataprovider_parse_request_valid
	 * @group serve-happy-live-http
	 */
	function test_parse_request_valid_live( $input, $expected ) {
		$output = json_decode( $this->helper_api_request( $input ), true );

		// Only check the response keys we're actually checking.
		$output = array_intersect_key( $output, $expected );

		$this->assertSame( $expected, $output );
	}

	/**
	 * @group serve-happy-live-http
	 */
	function test_parse_request_valid_live_jsonp() {
		$output = $this->helper_api_request([ 'callback' => 'JSONP_support.works_123' ]);

		$this->assertStringStartsWith( '/**/JSONP_support.works_123(', $output );
	}

	/**
	 * Make a HTTP request to api.wordpress.org and return the result.
	 */
	function helper_api_request( $args ) {
		$sandboxed = defined( 'WPORG_SANDBOXED' ) ? WPORG_SANDBOXED : false;

		$url = 'https://api.wordpress.org/core/serve-happy/1.0/';
		if ( $sandboxed ) {
			$url = str_replace( 'api.wordpress.org', $sandboxed . '.wordpress.org', $url );
		}

		if ( $args ) {
			$url .= '?' . http_build_query( $args );
		}

		return file_get_contents(
			$url,
			false,
			stream_context_create( [
				'http' => [
					'header'        => 'Host: api.wordpress.org',
					'ignore_errors' => true, // Accept 400 statues instead of throwing a failure warning.
				],
				'ssl' => [
					'verify_peer_name' => ! $sandboxed,
				]
			] )
		);
	}
}
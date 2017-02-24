<?php

/**
 *
 * @group api
 */
class Tests_API_Locale_Banner extends WP_UnitTestCase {

	/**
	 * @dataProvider data_locale_banner
	 *
	 * @type string $header         'Accept-Language' header value.
	 * @type array  $expected_sites Rosetta sites that should be present in the banner text.
	 */
	function test_locale_banner( $header, $expected_sites ) {
		$response = wp_remote_post( 'https://wordpress.org/plugins-wp/wp-json/plugins/v1/locale-banner', [
			'headers' => [
				'Accept-Language' => $header,
			],
		] );

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->assertArrayHasKey( 'suggest_string', $data );
		$this->assertArrayHasKey( 'translated', $data );
		$this->assertInternalType( 'array', $data['translated'] );

		preg_match_all( '#[a-z-]+.wordpress.org#', $data['suggest_string'], $sites );

		$this->assertNotEmpty( $sites );
		$this->assertEquals( $expected_sites, $sites[0] );
	}

	/**
	 * Data provider for test_locale_banner().
	 *
	 * @return array {
	 *     @type array {
	 *         @type string $header         'Accept-Language' header value.
	 *         @type array  $expected_sites Rosetta sites that should be present in the banner text.
	 *     }
	 * }
	 */
	function data_locale_banner() {
		return [
			[
				'en-US,en;q=0.5',
				[],
			],
			// r3540
			[
				'en-us',
				[],
			],
			[
				'de',
				[ 'de.wordpress.org' ],
			],
			[
				'de-CH',
				[ 'de-ch.wordpress.org' ],
			],
			// #1850
			[
				'en-US,en;q=0.8,ru;q=0.6,cs;q=0.4',
				[ 'ru.wordpress.org', 'cs.wordpress.org' ],
			],
			[
				'en-US,en;q=0.8,da;q=0.6,nb;q=0.4,sv;q=0.2',
				[ 'da.wordpress.org', 'nb.wordpress.org', 'sv.wordpress.org' ],
			],
		];
	}
}

<?php
/**
 * Tests for the plugins info API fields parameter.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API_Request;

/**
 * Covers which response fields a request keeps or removes.
 *
 * PLUGINS_API_VERSION is undefined here, so these run as the 1.0/1.1 API.
 *
 * @group plugins-info-api
 */
#[Group( 'plugins-info-api' )]
class Plugins_Info_API_Fields_Test extends TestCase {

	/**
	 * Requests and the inclusion flag they should produce for a field.
	 *
	 * @return array<string, array{string, array<string, string>, string, bool}>
	 */
	public static function data_expected_fields(): array {
		return array(
			'author is included by default'         => array( 'plugin_information', array(), 'author', true ),
			'author can be removed'                 => array( 'plugin_information', array( 'author' => '0' ), 'author', false ),
			'support_threads can be removed'        => array( 'query_plugins', array( 'support_threads' => '0' ), 'support_threads', false ),
			'removing contributors drops bare list' => array( 'plugin_information', array( 'contributors' => '0' ), 'bare_contributors', false ),
		);
	}

	/**
	 * Tests that a fields[x]=0 request removes the field without changing defaults.
	 *
	 * @param string                $method    The API method.
	 * @param array<string, string> $requested The client's fields parameter.
	 * @param string                $field     The field to check.
	 * @param bool                  $expected  Whether the field should be included.
	 */
	#[DataProvider( 'data_expected_fields' )]
	public function test_expected_fields( string $method, array $requested, string $field, bool $expected ): void {
		$request = new Plugins_Info_API_Request(
			array(
				'slug'   => 'example',
				'fields' => $requested,
			)
		);

		$this->assertSame( $expected, $request->get_expected_fields( $method )[ $field ] ?? null );
	}
}

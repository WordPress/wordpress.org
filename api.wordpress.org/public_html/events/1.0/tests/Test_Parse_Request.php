<?php
/**
 * Unit tests for the request parsing and input validation in parse_request().
 *
 * The tests assemble fake requests in the superglobals, so the usual request
 * handling sniffs don't apply. The rejection paths that end in
 * send_bad_request() exit the process and are covered by the e2e suite instead.
 *
 * phpcs:disable WordPress.Security.NonceVerification, WordPress.Security.ValidatedSanitizedInput
 *
 * @package WordPressdotorg\API\Events\Tests
 */

namespace Dotorg\API\Events\Tests;

use PHPUnit\Framework\TestCase;

use function Dotorg\API\Events\parse_request;

/**
 * Tests for parse_request().
 *
 * @group events
 */
class Test_Parse_Request extends TestCase {
	/**
	 * Snapshot of $_GET, restored after each test.
	 *
	 * @var array
	 */
	protected $backup_get;

	/**
	 * Snapshot of $_POST, restored after each test.
	 *
	 * @var array
	 */
	protected $backup_post;

	/**
	 * Snapshot of $_REQUEST, restored after each test.
	 *
	 * @var array
	 */
	protected $backup_request;

	/**
	 * Snapshot of $_SERVER, restored after each test.
	 *
	 * @var array
	 */
	protected $backup_server;

	/**
	 * Snapshots the superglobals and starts each test with an empty request.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->backup_get     = $_GET;
		$this->backup_post    = $_POST;
		$this->backup_request = $_REQUEST;
		$this->backup_server  = $_SERVER;

		$_GET     = array();
		$_POST    = array();
		$_REQUEST = array();
	}

	/**
	 * Restores the superglobals.
	 */
	public function tearDown(): void {
		$_GET     = $this->backup_get;
		$_POST    = $this->backup_post;
		$_REQUEST = $this->backup_request;
		$_SERVER  = $this->backup_server;

		parent::tearDown();
	}

	/**
	 * A request without parameters should produce only the defaults.
	 *
	 * @covers ::parse_request
	 */
	public function test_no_input_yields_defaults(): void {
		$args = parse_request();

		$this->assertSame( array( 'restrict_by_country' => false ), $args );
	}

	/**
	 * Numeric coordinates should be cast to floats.
	 *
	 * @covers ::parse_request
	 */
	public function test_valid_coordinates_are_cast_to_floats(): void {
		$_GET['latitude']  = '52.52';
		$_GET['longitude'] = '13.4';

		$args = parse_request();

		$this->assertSame( 52.52, $args['latitude'] );
		$this->assertSame( 13.4, $args['longitude'] );
	}

	/**
	 * Non-numeric coordinates should be ignored entirely.
	 *
	 * @covers ::parse_request
	 *
	 * @dataProvider dataprovider_invalid_coordinates
	 *
	 * @param string $latitude  The latitude request value.
	 * @param string $longitude The longitude request value.
	 */
	public function test_invalid_coordinates_are_ignored( $latitude, $longitude ): void {
		$_GET['latitude']  = $latitude;
		$_GET['longitude'] = $longitude;

		$args = parse_request();

		$this->assertArrayNotHasKey( 'latitude', $args );
		$this->assertArrayNotHasKey( 'longitude', $args );
	}

	/**
	 * Data provider of invalid coordinate pairs.
	 *
	 * @return array
	 */
	public static function dataprovider_invalid_coordinates(): array {
		return array(
			'non-numeric latitude'  => array( 'abc', '13.4' ),
			'non-numeric longitude' => array( '52.52', 'def' ),
			'both non-numeric'      => array( 'abc', 'def' ),
		);
	}

	/**
	 * ISO 3166-1 alpha-2 and alpha-3 codes should be accepted verbatim.
	 *
	 * @covers ::parse_request
	 *
	 * @dataProvider dataprovider_valid_countries
	 *
	 * @param string $country The country request value.
	 */
	public function test_valid_country_is_accepted( $country ): void {
		$_GET['country'] = $country;

		$args = parse_request();

		$this->assertSame( $country, $args['country'] );
		$this->assertTrue( $args['restrict_by_country'] );
	}

	/**
	 * Data provider of valid country codes.
	 *
	 * @return array
	 */
	public static function dataprovider_valid_countries(): array {
		return array(
			'alpha-2 lowercase' => array( 'de' ),
			'alpha-2 uppercase' => array( 'US' ),
			'alpha-3'           => array( 'DEU' ),
		);
	}

	/**
	 * An empty country parameter should behave as if it were absent.
	 *
	 * @covers ::parse_request
	 */
	public function test_empty_country_is_treated_as_absent(): void {
		$_GET['country'] = '';

		$args = parse_request();

		$this->assertArrayNotHasKey( 'country', $args );
		$this->assertFalse( $args['restrict_by_country'] );
	}

	/**
	 * Location names should be trimmed and stripped of control characters.
	 *
	 * @covers ::parse_request
	 */
	public function test_location_name_is_trimmed_and_stripped_of_control_characters(): void {
		$_REQUEST['location'] = "  Ber\x01lin\n";

		$args = parse_request();

		$this->assertSame( 'Berlin', $args['location_name'] );
	}

	/**
	 * Timezones should be validated against the IANA identifier format.
	 *
	 * @covers ::parse_request
	 *
	 * @dataProvider dataprovider_timezones
	 *
	 * @param string $timezone The timezone request value.
	 * @param string $expected The expected parsed value.
	 */
	public function test_timezone_validation( $timezone, $expected ): void {
		$_REQUEST['timezone'] = $timezone;

		$args = parse_request();

		$this->assertSame( $expected, $args['timezone'] );
	}

	/**
	 * Data provider of timezone values.
	 *
	 * @return array
	 */
	public static function dataprovider_timezones(): array {
		return array(
			'iana identifier'   => array( 'America/New_York', 'America/New_York' ),
			'etc offset'        => array( 'Etc/GMT+5', 'Etc/GMT+5' ),
			'invalid character' => array( 'America/New York', '' ),
			'trailing newline'  => array( "America/New_York\n", '' ),
		);
	}

	/**
	 * Locales should be validated against the WordPress locale format.
	 *
	 * @covers ::parse_request
	 *
	 * @dataProvider dataprovider_locales
	 *
	 * @param string $locale   The locale request value.
	 * @param string $expected The expected parsed value.
	 */
	public function test_locale_validation( $locale, $expected ): void {
		$_REQUEST['locale'] = $locale;

		$args = parse_request();

		$this->assertSame( $expected, $args['locale'] );
	}

	/**
	 * Data provider of locale values.
	 *
	 * @return array
	 */
	public static function dataprovider_locales(): array {
		return array(
			'simple'           => array( 'en_US', 'en_US' ),
			'variant'          => array( 'pt_PT_ao90', 'pt_PT_ao90' ),
			'invalid'          => array( 'en US<script>', '' ),
			'trailing newline' => array( "en_US\n", '' ),
		);
	}

	/**
	 * A public IP should be used as-is.
	 *
	 * @covers ::parse_request
	 */
	public function test_public_ip_is_accepted(): void {
		$_REQUEST['ip'] = '8.8.8.8';

		$args = parse_request();

		$this->assertSame( '8.8.8.8', $args['ip'] );
	}

	/**
	 * A private IP should fall back to the server-observed address.
	 *
	 * @covers ::parse_request
	 */
	public function test_private_ip_falls_back_to_the_server_address(): void {
		$_REQUEST['ip']         = '192.168.0.1';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

		$args = parse_request();

		$this->assertSame( '203.0.113.9', $args['ip'] );
	}

	/**
	 * A POSTed location_data array should replace the argument array wholesale.
	 *
	 * @covers ::parse_request
	 */
	public function test_location_data_replaces_the_argument_array(): void {
		$_POST['location_data'] = array(
			'latitude'  => '52.52',
			'longitude' => '13.4',
		);

		$args = parse_request();

		$this->assertSame( $_POST['location_data'], $args );
	}
}

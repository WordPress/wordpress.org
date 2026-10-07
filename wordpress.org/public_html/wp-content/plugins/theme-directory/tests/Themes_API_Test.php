<?php
/**
 * Tests for the themes API request handling.
 *
 * `Themes_API::__construct()` is the single funnel for every themes API entry
 * point — the unauthenticated `themes/info/1.x` endpoints, the `themes/1.x`
 * REST routes, and internal `wporg_themes_query_api()` callers alike — so the
 * normalisation and validation it performs on the request is the contract the
 * whole API rests on.
 *
 * @package theme-directory
 */

use PHPUnit\Framework\TestCase;

/**
 * @group themes-api
 */
class Themes_API_Test extends TestCase {

	/**
	 * API instances created during a test, detached again on teardown.
	 *
	 * @var array
	 */
	protected $api_instances = array();

	/**
	 * Detaches the locale filters installed by the API constructor.
	 *
	 * Each instance adds its own `locale` filter that otherwise outlives the
	 * test and leaks into the rest of the suite.
	 */
	protected function tearDown(): void {
		foreach ( $this->api_instances as $api ) {
			remove_filter( 'locale', array( $api, 'filter_locale' ) );
		}

		$this->api_instances = array();

		parent::tearDown();
	}

	/**
	 * Builds an API object for the given request.
	 *
	 * Defaults to the `feature_list` action because it touches no database
	 * state, which leaves the constructor's request handling as the only
	 * behaviour under test.
	 *
	 * @param array  $request The request parameters.
	 * @param string $action  Optional. The API action. Default 'feature_list'.
	 * @return Themes_API The constructed API object.
	 */
	protected function make_api( $request, $action = 'feature_list' ) {
		$api = new Themes_API( $action, $request );

		$this->api_instances[] = $api;

		return $api;
	}

	/*
	 * Scalar-only fields.
	 */

	/**
	 * Fields documented as scalar are dropped when given a non-scalar value,
	 * and the request is flagged so the endpoints can answer with a 400.
	 */
	public function test_non_scalar_scalar_only_field_is_dropped() {
		$api = $this->make_api( array( 'search' => array( 'nope' ) ) );

		$this->assertObjectNotHasProperty( 'search', $api->request );
		$this->assertNotEmpty( $api->bad_input );
	}

	/**
	 * A scalar value for the same field is left alone.
	 */
	public function test_scalar_only_field_is_preserved() {
		$api = $this->make_api( array( 'search' => 'twenty' ) );

		$this->assertSame( 'twenty', $api->request->search );
		$this->assertEmpty( $api->bad_input );
	}

	/*
	 * Locale validation.
	 *
	 * The locale is filtered into get_locale() for the remainder of the
	 * request, where it reaches translation path construction, cache keys, and
	 * query clauses, so only well-formed locale names may be honoured.
	 */

	/**
	 * Locale values that must never reach `get_locale()`.
	 *
	 * @return array
	 */
	public static function data_invalid_locales() {
		return array(
			'traversal'         => array( '../../../../tmp/pwn' ),
			'traversal to file' => array( 'de_DE/../../../../tmp/pwn' ),
			'path separator'    => array( 'de/DE' ),
			'null byte'         => array( "de_DE\0.mo" ),
			'newline'           => array( "de_DE\ninjected" ),
			'space'             => array( 'de DE' ),
			'quote'             => array( "en_' OR 1=1" ),
			'percent'           => array( 'de_DE%' ),
			'dot'               => array( 'de_DE.UTF-8' ),
		);
	}

	/**
	 * A malformed locale is dropped and the site default is left in place.
	 *
	 * @dataProvider data_invalid_locales
	 *
	 * @param string $locale The malformed locale to send.
	 */
	public function test_invalid_locale_is_rejected( $locale ) {
		$default = get_locale();

		$api = $this->make_api( array( 'locale' => $locale ) );

		$this->assertObjectNotHasProperty( 'locale', $api->request, 'Malformed locale was kept on the request.' );
		$this->assertSame( $default, get_locale(), 'Malformed locale reached get_locale().' );
		$this->assertSame( $default, determine_locale(), 'Malformed locale reached determine_locale().' );
	}

	/**
	 * Locale values that are well-formed and must still work.
	 *
	 * @return array
	 */
	public static function data_valid_locales() {
		return array(
			'language and region' => array( 'de_DE' ),
			'language only'       => array( 'ca' ),
			'three letter'        => array( 'bal' ),
			'variant'             => array( 'de_DE_formal' ),
			'hyphenated'          => array( 'zh-Hans' ),
		);
	}

	/**
	 * A well-formed locale is still honoured, so the validation does not break
	 * the translated responses the endpoints exist to serve.
	 *
	 * @dataProvider data_valid_locales
	 *
	 * @param string $locale The well-formed locale to send.
	 */
	public function test_valid_locale_is_applied( $locale ) {
		$api = $this->make_api( array( 'locale' => $locale ) );

		$this->assertSame( $locale, $api->request->locale );
		$this->assertSame( $locale, get_locale(), 'Well-formed locale was not applied.' );
	}

	/**
	 * A non-string scalar carries no usable locale and is dropped rather than
	 * being coerced into one.
	 */
	public function test_non_string_scalar_locale_is_rejected() {
		$default = get_locale();

		$api = $this->make_api( array( 'locale' => 12345 ) );

		$this->assertObjectNotHasProperty( 'locale', $api->request );
		$this->assertSame( $default, get_locale() );
	}

	/**
	 * A malformed locale falls back to the default rather than failing the
	 * request, so clients sending an unusable locale keep working.
	 */
	public function test_invalid_locale_does_not_fail_the_request() {
		$api = $this->make_api( array( 'locale' => '../../../../tmp/pwn' ) );

		$this->assertEmpty( $api->bad_input, 'A malformed locale should not turn the response into a 400.' );
		$this->assertNotEmpty( $api->response );
	}

	/*
	 * Favorites requests.
	 */

	/**
	 * Browsing favorites without a user is malformed: there is nobody to fetch
	 * favorites for, so the request is flagged rather than silently browsing
	 * someone else's.
	 */
	public function test_favorites_without_user_is_flagged() {
		$api = $this->make_api( array( 'browse' => 'favorites' ) );

		$this->assertSame( '', $api->request->user );
		$this->assertNotEmpty( $api->bad_input );
	}

	/**
	 * Browsing favorites with a user is a well-formed request.
	 */
	public function test_favorites_with_user_is_accepted() {
		$api = $this->make_api( array(
			'browse' => 'favorites',
			'user'   => 'example',
		) );

		$this->assertSame( 'example', $api->request->user );
		$this->assertEmpty( $api->bad_input );
	}

	/*
	 * Array-of-string fields.
	 */

	/**
	 * Comma-separated lists are split, trimmed, and de-duplicated, so the
	 * flat query string form the 1.2 endpoint accepts behaves like the array
	 * form.
	 */
	public function test_comma_separated_list_is_normalised() {
		$api = $this->make_api( array( 'tag' => 'blog, photography ,blog' ) );

		$this->assertSame( array( 'blog', 'photography' ), array_values( $api->request->tag ) );
	}

	/**
	 * A list that normalises to nothing is dropped entirely and flagged,
	 * rather than being left as an empty filter.
	 */
	public function test_empty_list_is_dropped() {
		$api = $this->make_api( array( 'slugs' => array() ) );

		$this->assertObjectNotHasProperty( 'slugs', $api->request );
		$this->assertNotEmpty( $api->bad_input );
	}

	/*
	 * The `fields` parameter.
	 */

	/**
	 * An omitted `fields` parameter is normalised to an empty array, so
	 * callers can rely on it always being present.
	 */
	public function test_fields_defaults_to_empty_array() {
		$api = $this->make_api( array() );

		$this->assertSame( array(), $api->request->fields );
	}

	/**
	 * A list of field names becomes a map of those names to true.
	 */
	public function test_numeric_fields_array_becomes_keyed_map() {
		$api = $this->make_api( array( 'fields' => array( 'description', 'sections' ) ) );

		$this->assertSame(
			array(
				'description' => true,
				'sections'    => true,
			),
			$api->request->fields
		);
	}

	/**
	 * Field values are cast to real booleans, so the string 'false' that a
	 * query string produces does not read as an enabled field.
	 */
	public function test_keyed_fields_values_are_cast_to_booleans() {
		$api = $this->make_api( array(
			'fields' => array(
				'description' => 1,
				'sections'    => 'false',
			),
		) );

		$this->assertSame(
			array(
				'description' => true,
				'sections'    => false,
			),
			$api->request->fields
		);
	}
}

<?php

namespace WordPressdotorg\Plugin_Directory\Block_JSON;

use WP_Error;

defined( 'WPINC' ) || die();

/**
 * Class Validator
 *
 * @package WordPressdotorg\Plugin_Directory\Block_JSON
 */
class Validator {
	/**
	 * @var WP_Error
	 */
	protected $messages;

	/**
	 * Validator constructor.
	 */
	public function __construct() {
		$this->messages = new WP_Error();
	}

	/**
	 * The schema for the block.json file.
	 *
	 * Fetch the schema from `schemas.wp.org`, which redirects to the latest version
	 * of this file in GitHub.
	 * See https://github.com/WordPress/gutenberg/blob/trunk/schemas/json/block.json.
	 *
	 * @return array
	 */
	public static function schema() {
		$schema_url = 'https://schemas.wp.org/trunk/block.json';
		$response = wp_remote_get( $schema_url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$schema = json_decode( $body, true );
		return $schema;
	}

	/**
	 * Validate a PHP object representation of a block.json file.
	 *
	 * @param object|WP_Error $block_json
	 *
	 * @return bool|WP_Error
	 */
	public function validate( $block_json ) {
		// A WP_Error instance is technically an object, but shouldn't be validated.
		if ( is_wp_error( $block_json ) ) {
			return $block_json;
		}

		$schema = self::schema();
		if ( is_wp_error( $schema ) ) {
			return $schema;
		}

		$result = rest_validate_value_from_schema( $block_json, $schema, 'block.json' );

		// Workaround for a bug in validation, `oneOf` incorrectly flags that the value matches multiple options.
		// Getting this message means it passed the "string" condition, so this is not an error.
		// See https://core.trac.wordpress.org/ticket/54740.
		if ( is_wp_error( $result ) && 'rest_one_of_multiple_matches' !== $result->get_error_code() ) {
			$this->messages = $result;
		}

		$this->check_conditional_properties( $block_json );

		if ( $this->messages->has_errors() ) {
			return $this->messages;
		}

		return true;
	}

	/**
	 * Check for properties that are conditionally required.
	 *
	 * @param object $block_json
	 *
	 * @return void
	 */
	protected function check_conditional_properties( $block_json ) {
		if ( ! is_object( $block_json ) ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName
		if ( ! isset( $block_json->script ) && ! isset( $block_json->editorScript ) ) {
			$this->messages->add(
				'error',
				sprintf(
					__( 'block.json[script] At least one of the following properties must be present: %s', 'wporg-plugins' ),
					// translators: used between list items, there is a space after the comma.
					'<code>script</code>' . __( ', ', 'wporg-plugins' ) . '<code>editorScript</code>'
				)
			);
		}
	}

	/**
	 * Add more data to an error code.
	 *
	 * The `add_data` method in WP_Error replaces data with each subsequent call with the same error code.
	 *
	 * @param mixed  $new_data   The data to append.
	 * @param string $error_code The error code to assign the data to.
	 *
	 * @return void
	 */
	protected function append_error_data( $new_data, $error_code ) {
		$data   = $this->messages->get_error_data( $error_code ) ?: array();
		$data[] = $new_data;
		$this->messages->add_data( $data, $error_code );
	}
}

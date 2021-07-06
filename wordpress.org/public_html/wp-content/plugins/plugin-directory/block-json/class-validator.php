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
	 * This attempts to follow the schema for the schema.
	 * See https://json-schema.org/understanding-json-schema/reference/index.html
	 *
	 * @return array
	 */
	public static function schema() {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'apiVersion'   => array(
					'type' => 'string',
				),
				'attributes'   => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type'       => 'object',
						'properties' => array(
							'attribute' => array(
								'type' => 'string',
							),
							'meta'      => array(
								'type' => 'string',
							),
							'multiline' => array(
								'type' => 'string',
							),
							'query'     => array(
								'type' => 'object',
							),
							'selector'  => array(
								'type' => 'string',
							),
							'source'    => array(
								'type' => 'string',
								'enum' => array( 'attribute', 'text', 'html', 'query' ),
							),
							'type'      => array(
								'type' => 'string',
								'enum' => array( 'null', 'boolean', 'object', 'array', 'number', 'string', 'integer' ),
							),
						),
						'required'   => array( 'type' ),
					),
				),
				'category'     => array(
					'type' => 'string',
				),
				'comment'      => array(
					'type' => 'string',
				),
				'description'  => array(
					'type' => 'string',
				),
				'editorScript' => array(
					'type'    => 'string',
					'pattern' => '\.js$',
				),
				'editorStyle'  => array(
					'type'    => 'string',
					'pattern' => '\.css$',
				),
				'example'      => array(
					'type'                 => 'object',
					'additionalProperties' => array(
						'type' => 'object',
					),
				),
				'icon'         => array(
					'type' => 'string',
				),
				'keywords'     => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
				'name'         => array(
					'type' => 'string',
				),
				'parent'       => array(
					'type'  => 'array',
					'items' => array(
						'type' => 'string',
					),
				),
				'script'       => array(
					'type'    => 'string',
					'pattern' => '\.js$',
				),
				'style'        => array(
					'type'    => 'string',
					'pattern' => '\.css$',
				),
				'styles'       => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'isDefault' => array(
								'type' => 'boolean',
							),
							'label'     => array(
								'type' => 'string',
							),
							'name'      => array(
								'type' => 'string',
							),
						),
					),
				),
				'supports'     => array(
					'type'       => 'object',
					'properties' => array(
						'align'           => array(
							'type'  => array( 'boolean', 'array' ),
							'items' => array(
								'type' => 'string',
								'enum' => array( 'left', 'center', 'right', 'wide', 'full' ),
							),
						),
						'alignWide'       => array(
							'type' => 'boolean',
						),
						'anchor'          => array(
							'type' => 'boolean',
						),
						'className'       => array(
							'type' => 'boolean',
						),
						'customClassName' => array(
							'type' => 'boolean',
						),
						'html'            => array(
							'type' => 'boolean',
						),
						'inserter'        => array(
							'type' => 'boolean',
						),
						'multiple'        => array(
							'type' => 'boolean',
						),
						'reusable'        => array(
							'type' => 'boolean',
						),
					),
				),
				'textdomain'   => array(
					'type' => 'string',
				),
				'title'        => array(
					'type' => 'string',
				),
			),
			'required'             => array( 'name', 'title' ),
			'additionalProperties' => false,
		);
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

		$this->validate_object( $block_json, 'block.json', $schema );
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

		if ( ! isset( $block_json->script ) && ! isset( $block_json->editorScript ) ) {
			$this->messages->add(
				'error',
				sprintf(
					__( 'At least one of the following properties must be present: %s', 'wporg-plugins' ),
					// translators: used between list items, there is a space after the comma.
					'<code>script</code>' . __( ', ', 'wporg-plugins' ) . '<code>editorScript</code>'
				)
			);
			$this->append_error_data( 'block.json:script', 'error' );
			$this->append_error_data( 'block.json:editorScript', 'error' );
		}
	}

	/**
	 * Validate an object and its properties.
	 *
	 * @param object $object The value to validate as an object.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_object( $object, $prop, $schema ) {
		if ( ! is_object( $object ) ) {
			$this->messages->add(
				'error',
				sprintf(
					__( 'The %s property must contain an object value.', 'wporg-plugins' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		$results = array();

		if ( isset( $schema['required'] ) ) {
			foreach ( $schema['required'] as $required_prop ) {
				if ( ! property_exists( $object, $required_prop ) ) {
					$this->messages->add(
						'error',
						sprintf(
							__( 'The %1$s property is required in the %2$s object.', 'wporg-plugins' ),
							'<code>' . $required_prop . '</code>',
							'<code>' . $prop . '</code>'
						)
					);
					$this->append_error_data( "$prop:$required_prop", 'error' );
					$results[] = false;
				}
			}
		}

		if ( isset( $schema['properties'] ) ) {
			foreach ( $schema['properties'] as $subprop => $subschema ) {
				if ( ! isset( $object->$subprop ) ) {
					continue;
				}

				if ( isset( $subschema['type'] ) ) {
					$results[] = $this->route_validation_for_type(
						$subschema['type'],
						$object->$subprop,
						"$prop:$subprop",
						$subschema
					);
				}
			}
		}

		if ( isset( $schema['additionalProperties'] ) ) {
			if ( false === $schema['additionalProperties'] ) {
				foreach ( array_keys( get_object_vars( $object ) ) as $key ) {
					if ( ! isset( $schema['properties'][ $key ] ) ) {
						$this->messages->add(
							'warning',
							sprintf(
								__( '%1$s is not a valid property in the %2$s object.', 'wporg-plugins' ),
								'<code>' . $key . '</code>',
								'<code>' . $prop . '</code>'
							)
						);
						$this->append_error_data( "$prop:$key", 'warning' );
						$results[] = false;
						continue;
					}
				}
			} elseif ( isset( $schema['additionalProperties']['type'] ) ) {
				foreach ( $object as $subprop => $subvalue ) {
					$results[] = $this->route_validation_for_type(
						$schema['additionalProperties']['type'],
						$subvalue,
						"$prop:$subprop",
						$schema['additionalProperties']
					);
				}
			}
		}

		return ! in_array( false, $results, true );
	}

	/**
	 * Validate an array and its items.
	 *
	 * @param array  $array  The value to validate as an array.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_array( $array, $prop, $schema ) {
		if ( ! is_array( $array ) ) {
			$this->messages->add(
				'error',
				sprintf(
					__( 'The %s property must contain an array value.', 'wporg-plugins' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		if ( isset( $schema['items']['type'] ) ) {
			$results = array();
			$index   = 0;

			foreach ( $array as $item ) {
				$results[] = $this->route_validation_for_type(
					$schema['items']['type'],
					$item,
					$prop . "[$index]",
					$schema['items']
				);
				$index ++;
			}

			return ! in_array( false, $results, true );
		}

		return true;
	}

	/**
	 * Validate a string.
	 *
	 * @param string $string The value to validate as a string.
	 * @param string $prop   The name of the property, used in error reporting.
	 * @param array  $schema The schema for the property, used for validation.
	 *
	 * @return bool
	 */
	protected function validate_string( $string, $prop, $schema ) {
		if ( ! is_string( $string ) ) {
			$this->messages->add(
				'error',
				sprintf(
					__( 'The %s property must contain a string value.', 'wporg-plugins' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		if ( isset( $schema['enum'] ) ) {
			if ( ! in_array( $string, $schema['enum'], true ) ) {
				$this->messages->add(
					'warning',
					sprintf(
						__( '"%1$s" is not a valid value for the %2$s property.', 'wporg-plugins' ),
						esc_html( $string ),
						'<code>' . $prop . '</code>'
					)
				);
				$this->append_error_data( $prop, 'warning' );
			}
		}

		if ( isset( $schema['pattern'] ) ) {
			if ( ! preg_match( '#' . $schema['pattern'] . '#', $string ) ) {
				$pattern_description = $this->get_human_readable_pattern_description( $schema['pattern'] );
				if ( $pattern_description ) {
					$message = sprintf(
						$pattern_description,
						'<code>' . $prop . '</code>'
					);
				} else {
					$message = sprintf(
						__( 'The value of %s does not match the required pattern.', 'wporg-plugins' ),
						'<code>' . $prop . '</code>'
					);
				}

				$this->messages->add( 'warning', $message );
				$this->append_error_data( $prop, 'warning' );
			}
		}

		return true;
	}

	/**
	 * Validate a boolean.
	 *
	 * @param bool   $boolean The value to validate as a boolean.
	 * @param string $prop    The name of the property, used in error reporting.
	 *
	 * @return bool
	 */
	protected function validate_boolean( $boolean, $prop ) {
		if ( ! is_bool( $boolean ) ) {
			$this->messages->add(
				'error',
				sprintf(
					__( 'The %s property must contain a boolean value.', 'wporg-plugins' ),
					'<code>' . $prop . '</code>'
				)
			);
			$this->append_error_data( $prop, 'error' );

			return false;
		}

		return true;
	}

	/**
	 * Send a property value to the correct validator depending on which type(s) it can be.
	 *
	 * @param string|array $valid_types
	 * @param mixed        $value
	 * @param string       $prop
	 * @param array        $schema
	 *
	 * @return bool
	 */
	protected function route_validation_for_type( $valid_types, $value, $prop, $schema ) {
		// There is a single valid type.
		if ( is_string( $valid_types ) ) {
			$method = "validate_$valid_types";
			return $this->$method( $value, $prop, $schema );
		}

		// There are multiple valid types in an array.
		foreach ( $valid_types as $type ) {
			switch ( $type ) {
				case 'boolean':
					$check = 'is_bool';
					break;
				default:
					$check = "is_$type";
					break;
			}

			if ( $check( $value ) ) {
				$method = "validate_$type";
				return $this->$method( $value, $prop, $schema );
			}
		}

		// Made it this far, it's none of the valid types.
		$this->messages->add(
			'error',
			sprintf(
				__( 'The %1$s property must contain a value that is one of these types: %2$s', 'wporg-plugins' ),
				'<code>' . $prop . '</code>',
				// translators: used between list items, there is a space after the comma.
				'<code>' . implode( '</code>' . __( ', ', 'wporg-plugins' ) . '<code>', $valid_types ) . '</code>'
			)
		);
		$this->append_error_data( $prop, 'error' );

		return false;
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

	/**
	 * Get a description of a regex pattern that can be understood by humans.
	 *
	 * @param string $pattern A regex pattern.
	 *
	 * @return string
	 */
	protected function get_human_readable_pattern_description( $pattern ) {
		$description = '';

		switch ( $pattern ) {
			case '\.css$':
				$description = __( 'The value of %s must end in ".css".', 'wporg-plugins' );
				break;
			case '\.js$':
				$description = __( 'The value of %s must end in ".js".', 'wporg-plugins' );
				break;
		}

		return $description;
	}
}

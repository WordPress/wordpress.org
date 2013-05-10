<?php

class WCB_Array_Option extends WCB_Option {
	var $keys = array();

	function WCB_Array_Option( $args ) {
		parent::WCB_Option( $args );
	}
	/**
	 * Overload get_option to optionally return a key within the returned option.
	 */
	function get_option( $key=false ) {
		$option = parent::get_option();
		if ( empty( $key ) )
			return $option;

		if ( isset( $option[ $key ] ) )
			return $option[ $key ];

		if ( isset( $this->default[ $key ] ) )
			return $this->default[ $key ];

		return false;
	}

	/**
	 * Overload get_name to optionally add a key index to the result.
	 */
	function get_name( $key=false ) {
		$name = parent::get_name();
		if ( ! empty( $key ) )
			$name .= "[$key]";
		return esc_attr( $name );
	}

	function validate( $input ) {
		if ( ! is_array( $input ) )
			return null;

		foreach ( $this->keys as $key ) {
			$method = "validate_$key";
			$value = isset( $input[ $key ] ) ? $input[ $key ] : null;
			if ( method_exists( $this, $method ) )
				$input[ $key ] = $this->$method( $value );
		}
		return $input;
	}

	function maybe_unserialize( $value ) {
		$value = maybe_unserialize( $value );

		if ( is_array( $value ) )
			$value = array_map( 'maybe_unserialize', $value );

		return $value;
	}
}

?>
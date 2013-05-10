<?php

class WCB_Option {
	var $key;
	var $default;

	function WCB_Option( $args ) {
		$defaults = array(
			'key' => '',
			'default' => '',
		);
		extract( wp_parse_args( $args, $defaults ) );
		$this->key = $key;
		$this->default = $default;
	}

	function maybe_validate( $input ) {
		if ( isset( $input[ $this->key ] ) )
			$input[ $this->key ] = $this->validate( $input[ $this->key ] );
		return $input;
	}

	function validate( $input ) {
		return $input;
	}

	function render() {}

	function get_option() {
		$options = get_option( 'wcb_theme_options' );
		// Use the default value if necessary.
		return isset( $options[ $this->key ] ) ? $this->maybe_unserialize( $options[ $this->key ] ) : $this->default;
	}

	function get_name() {
		$name = 'wcb_theme_options[' . $this->key . ']';
		return esc_attr( $name );
	}

	function name() {
		$args = func_get_args();
		$name = call_user_func_array( array( &$this, 'get_name' ), $args );
		echo " name='$name' ";
	}

	function maybe_unserialize( $value ) {
		return $value;
	}
}

?>
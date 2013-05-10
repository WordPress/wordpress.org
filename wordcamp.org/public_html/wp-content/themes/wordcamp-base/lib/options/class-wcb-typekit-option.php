<?php

class WCB_Typekit_Option extends WCB_Radio_Option {
	var $kit_regex = '|^([a-zA-Z0-9]{5}[a-zA-Z0-9]*)$|i';

	function WCB_Typekit_Option( $args ) {
		parent::WCB_Radio_Option( $args );

		// If the 'custom' label exists, tack on an input field (potentially with the kit id).
		if ( ! empty( $this->values['custom'] ) ) {
			$kit_id = ( $this->get_option() == 'custom' ) ? $this->get_kit_id() : '';
			$this->values['custom'] .= ' <input type="text" name="typekit_custom_id" value="' . esc_attr( $kit_id ) . '" />';
		}

		$this->default = '';
		if ( ! empty( $args['default'] ) && preg_match( $this->kit_regex, $args['default'] ) )
			$this->default = $args['default'];
	}

	// This option will fake being a radio box (internally, it will navigate labels),
	// but externally, it will return the kit id.
	function get_option() {
		$kit_id = $this->get_kit_id();

		if ( ! $kit_id )
			return 'off';

		return ( $kit_id == $this->default ) ? 'default' : 'custom';
	}

	function get_kit_id() {
		return parent::get_option();
	}

	function validate( $input ) {
		$input = parent::validate( $input );

		$kit_id = '';

		if ( $input == 'default' ) {
			$kit_id = $this->default_kit_id;

		} elseif ( $input == 'custom' && ! empty( $_POST['typekit_custom_id'] )
		&& preg_match( $this->kit_regex, $_POST['typekit_custom_id'] ) ) {
			$kit_id = $_POST['typekit_custom_id'];
		}

		return $kit_id;
	}
}

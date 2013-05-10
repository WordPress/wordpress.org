<?php

class WCB_Radio_Option extends WCB_Option {
	var $label;
	var $values;

	function WCB_Radio_Option( $args ) {
		parent::WCB_Option( $args );
		$defaults = array(
			'label' => '',
			'values' => array()
		);
		extract( wp_parse_args( $args, $defaults ) );
		$this->label = $label;
		$this->values = $values;
		if ( empty( $values ) )
			return;
		if ( empty( $this->default ) || ! array_key_exists( $this->default, $this->values ) )
			$this->default = @$this->values[0];
	}

	function validate( $input ) {
		// Our radio option must actually be in our array of radio values
		if ( ! array_key_exists( $input, $this->values ) )
			$input = null;

		return $input;
	}

	function render() {
		if ( empty( $this->values ) )
			return;

		echo "<tr valign='top'><th scope='row'>$this->label</th><td>";
		echo "<fieldset><legend class='screen-reader-text'><span>$this->label</span></legend>";

		$option = $this->get_option();

		foreach ( $this->values as $value => $label ): ?>
			<label class="description"><input type="radio" <?php $this->name(); ?> value="<?php echo esc_attr( $value ); ?>" <?php checked( $value, $option ); ?> /> <?php echo $label; ?></label><br />
			<?php
		endforeach;
		echo '</fieldset></td></tr>';
	}
}

?>
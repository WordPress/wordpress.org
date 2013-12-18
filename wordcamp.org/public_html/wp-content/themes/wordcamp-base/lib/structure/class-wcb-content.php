<?php

class WCB_Content extends WCB_Container {
	var $open = false;

	function WCB_Content( $args ) {
		// $args = wp_parse_args( $args, array( 'id' => 'main' ) );
		parent::WCB_Container( $args );
	}

	function before() {
		parent::before();
		echo '<div id="main">';
	}

	function after() {
		echo '</div>';
		parent::after();
	}

	function render( $resume_only=false ) {
		if ( ! $this->open ) {
			$this->open = true;
			$this->before();
			return false;
		}
		$this->open = false;
		$this->after();
	}
}

?>
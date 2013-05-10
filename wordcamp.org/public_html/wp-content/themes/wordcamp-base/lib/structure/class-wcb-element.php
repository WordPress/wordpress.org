<?php

class WCB_Element {
	function render() {
		$this->before();
		$this->content();
		$this->after();
	}

	function before() {}
	function content() {}
	function after() {}

	function get_id() {
		return '';
	}
}

?>
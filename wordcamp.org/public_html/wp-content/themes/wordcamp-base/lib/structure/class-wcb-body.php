<?php

class WCB_Body extends WCB_Elements {
	function before() {
		echo '<body ';
		body_class();
		echo '>';
	}

	function after() {
		echo '</body>';
	}
}

?>
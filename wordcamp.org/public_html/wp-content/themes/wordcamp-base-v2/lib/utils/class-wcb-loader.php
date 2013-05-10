<?php

class WCB_Loader {
	function WCB_Loader() {
		$this->constants();
		$this->includes();
		$this->hooks();
		$this->loaded();

		// If these methods exist, automatically bind them to actions.
		$method_action_map = array(
			'init'                  => 'init',
			'register_post_types'   => 'init',
			'register_taxonomies'   => 'init',
		);
		foreach ( $method_action_map as $method => $action ) {
			if ( method_exists( $this, $method ) )
				add_action( $action, array( &$this, $method ), 10, 99 ); // 99 is effectively infinite args.
		}
	}

	function constants() {}

	function includes() {}

	function hooks() {}

	function loaded() {}
}

?>
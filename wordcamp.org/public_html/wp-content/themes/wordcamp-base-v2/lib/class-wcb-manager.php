<?php

class WCB_Manager extends WCB_Loader {
	var $components;

	function constants() {
		wcb_maybe_define( 'WCB_LIB_DIR', WCB_DIR . '/lib' );
		wcb_maybe_define( 'WCB_LIB_URL', WCB_URL . '/lib'  );
	}

	function includes() {
		require_once WCB_LIB_DIR . '/options/class-wcb-options.php';
		// require_once WCB_LIB_DIR . '/structure/class-wcb-structure.php';
		require_once WCB_LIB_DIR . '/speakers/class-wcb-speakers.php';
		require_once WCB_LIB_DIR . '/sessions/class-wcb-sessions.php';
		require_once WCB_LIB_DIR . '/sponsors/class-wcb-sponsors.php';
	}

	function loaded() {
		$this->components = array(
			'options'   => new WCB_Options(),
			// 'structure' => new WCB_Structure(),
			'speakers'  => ( apply_filters( 'wcb_load_component_speakers', true ) ) ? new WCB_Speakers() : false,
			'sessions'  => ( apply_filters( 'wcb_load_component_sessions', true ) ) ? new WCB_Sessions() : false,
			'sponsors'  => ( apply_filters( 'wcb_load_component_sponsors', true ) ) ? new WCB_Sponsors() : false,
		);
	}
}

$GLOBALS['wcb_manager'] = new WCB_Manager;

function wcb_get( $component='' ) {
	global $wcb_manager;
	if ( isset( $wcb_manager->components[ $component ] ) )
		return $wcb_manager->components[ $component ];
	else
		return $wcb_manager;
}

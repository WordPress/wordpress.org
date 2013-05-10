<?php
/**
 * A registry that contains a single instance of each registered class.
 */
class WCB_Registry {
	var $instances = array();

	/**
	 * Register and instantiate an instance of a class (if necessary).
	 */
	function register( $classname ) {
		if ( ! isset( $this->instances[ $classname ] ) )
			$this->instances[ $classname ] = & new $classname();
	}
	/**
	 * Retrieve an instance of a class.
	 */
	function get( $classname ) {
		$this->register( $classname );
		return $this->instances[ $classname ];
	}
	/**
	 * Unregister a class instance.
	 */
	function unregister( $classname ) {
		if ( isset( $this->instances[ $classname ] ) )
			unset( $this->instances[ $classname ] );
	}
}

?>
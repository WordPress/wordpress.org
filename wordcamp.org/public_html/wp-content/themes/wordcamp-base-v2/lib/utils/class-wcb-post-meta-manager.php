<?php

class WCB_Post_Meta_Manager {
	var $prefix;
	var $private;
	var $keys;
	var $_key_prefix;

	function WCB_Post_Meta_Manager( $args ) {
		$defaults = array(
			'prefix'    => '',
			'private'   => true,
			'keys'      => array(),
		);
		extract( wp_parse_args( $args, $defaults ) );

		$this->prefix   = $prefix;
		$this->private  = $private;
		$this->keys     = $keys;

		// Generate key prefix
		$this->_key_prefix = ( $private ) ? '_' : '';
		if ( ! empty( $prefix ) )
			$this->_key_prefix .= $prefix . '_';
	}

	function get( $post_id, $key=false ) {
		if ( ! empty( $key ) )
			return get_post_meta( $post_id, $this->meta_key( $key ), true );

		$metadata = array();
		foreach ( $this->keys as $key ) {
			$metadata[ $key ] = $this->get( $post_id, $key );
		}
		return $metadata;
	}

	function meta_key( $key ) {
		return $this->_key_prefix . $key;
	}

	function update( $post_id, $metadata ) {
		foreach ( $this->keys as $key ) {
			$meta_key = $this->meta_key( $key );

			if ( isset( $metadata[ $key ] ) )
				update_post_meta( $post_id, $meta_key, $metadata[ $key ] );
			else
				delete_post_meta( $post_id, $meta_key );
		}
	}
}

?>
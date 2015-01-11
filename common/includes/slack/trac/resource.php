<?php

namespace Dotorg\Slack\Trac;
use Dotorg\Slack\User;

class Resource implements User {

	protected $data;

	static protected $instances = array();

	function __construct( Trac $trac, $id ) {
		$this->trac = $trac;
		$this->id   = $id;
	}

	static protected function get_resource_type() {
		$class = get_called_class();
		while ( get_parent_class( $class ) !== __CLASS__ ) {
			$class = get_parent_class( $class );
		}
		return strtolower( str_replace( __NAMESPACE__ . '\\', '', $class ) );
	}

	function get( Trac $trac, $id ) {
		$key = $trac->get_slug() . ':' . static::get_resource_type() . ':' . $id;
		if ( isset( static::$instances[ $key ] ) ) {
			return static::$instances[ $key ];
		}
		return static::$instances[ $key ] = new static( $trac, $id );
	}

	function get_name() {
		$method = 'get_' . static::get_resource_type() . '_username';
		return $this->trac->$method();
	}

	function get_url() {
		$method = 'get_' . static::get_resource_type() . '_url';
		return $this->trac->$method( $this->id );
	}

	function get_icon() {
		return $this->trac->get_icon();
	}

	function __get( $prop ) {
		return isset( $this->data->$prop ) ? $this->data->$prop : false;
	}

	function __isset( $prop ) {
		return isset( $this->data->$prop );
	}
}

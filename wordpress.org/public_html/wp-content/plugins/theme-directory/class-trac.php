<?php

if ( ! class_exists( 'IXR_Client' ) ) {
	include_once ABSPATH . WPINC . '/class-IXR.php';
}

/**
 * Class Trac
 */
class Trac {

	/**
	 * Client to talk to a passed Trac setup.
	 *
	 * @var IXR_Client
	 */
	public $rpc;

	/**
	 * Array key for the value containing ticket attributes.
	 */
	const attributes = 3;

	/**
	 * @param string $username
	 * @param string $password
	 * @param string $host
	 * @param string $path
	 * @param string $port
	 * @param bool   $ssl
	 */
	function __construct( $username, $password, $host, $path, $port, $ssl = false ) {
		$this->rpc = new IXR_Client( $host, $path, $port );

		$this->rpc->headers['Authorization'] = 'Basic ' . base64_encode( $username . ':' . $password );
		$this->rpc->ssl                      = $ssl;
	}

	/**
	 * Creates a new Trac ticket.
	 *
	 * @param string $subj
	 * @param string $desc
	 * @param array  $attr
	 * @return bool
	 */
	function ticket_create( $subj, $desc, $attr = array() ) {
		if ( empty( $attr ) ) {
			$attr = new IXR_Value( array(), 'struct' );
		}
		$ok = $this->rpc->query( 'ticket.create', $subj, $desc, $attr );
		if ( ! $ok ) {
			// print_r( $this->rpc );
			return false;
		}

		return $this->rpc->getResponse();
	}

	/**
	 * Updates a Trac ticket.
	 *
	 * @param int    $id      Ticket ID.
	 * @param string $comment Comment text.
	 * @param array  $attr    Optional. Ticket attributes. Default: Empty array.
	 * @param bool   $notify  Optional. Whether to notify the author. Default: false.
	 * @return bool
	 */
	function ticket_update( $id, $comment, $attr = array(), $notify = false ) {
		if ( empty( $attr['_ts'] ) ) {
			$get         = $this->ticket_get( $id );
			$attr['_ts'] = $get[ self::attributes ]['_ts'];
		}
		if ( empty( $attr['action'] ) ) {
			$attr['action'] = 'leave';
		}

		$ok = $this->rpc->query( 'ticket.update', $id, $comment, $attr, $notify );
		if ( ! $ok ) {
			return false;
		}

		return $this->rpc->getResponse();
	}

	/**
	 * Searches for a Trac ticket.
	 *
	 * @param string $search
	 * @return bool
	 */
	function ticket_query( $search ) {
		$ok = $this->rpc->query( 'ticket.query', $search );
		if ( ! $ok ) {
			return false;
		}

		return $this->rpc->getResponse();
	}

	/**
	 * Gets a Trac ticket.
	 *
	 * @param int $id Ticket ID.
	 * @return array|bool [id, time_created, time_changed, attributes] or false on failure.
	 */
	function ticket_get( $id ) {
		$ok = $this->rpc->query( 'ticket.get', $id );
		if ( ! $ok ) {
			return false;
		}

		return $this->rpc->getResponse();
	}
}

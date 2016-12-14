<?php
class Trac {
	var $rpc;
	const attributes = 3;

	function __construct( $username, $password, $host, $path = '/', $port = 80, $ssl = FALSE ) {
		// Assume URL to $host, ignore $path, $port, $ssl
		$this->rpc = new WP_HTTP_IXR_Client( $host );

		$http_basic_auth = 'Basic ';
		$http_basic_auth .= base64_encode( $username . ':' . $password );

		$this->rpc->headers['Authorization'] = $http_basic_auth;
	}

	function ticket_create( $subj, $desc, $attr = array() ) {
		if ( empty( $attr ) )
			$attr = new IXR_Value( array(), 'struct' );
		$ok = $this->rpc->query( 'ticket.create', $subj, $desc, $attr );
		if ( !$ok ) {
			// print_r( $this->rpc );
			return FALSE;
		}

		return $this->rpc->getResponse();
	}

	function ticket_update( $id, $comment, $attr = array(), $notify = false ) {
		if ( empty( $attr['_ts'] ) ) {
			$get = $this->ticket_get( $id );
			$attr['_ts'] = $get['_ts'];
		}
		if ( empty( $attr['action'] ) )
			$attr['action'] = 'leave';

		$ok = $this->rpc->query( 'ticket.update', $id, $comment, $attr, $notify );
		if ( ! $ok )
			return false;

		return $this->rpc->getResponse();
	}

	function ticket_query( $search ) {
		$ok = $this->rpc->query( 'ticket.query', $search );
		if ( !$ok ) {
			return FALSE;
		}

		return $this->rpc->getResponse();
	}

	/**
	 * @return [id, time_created, time_changed, attributes] or false on failure.
	 */
	function ticket_get( $id ) {
		$ok = $this->rpc->query( 'ticket.get', $id );
		if ( !$ok ) {
			return FALSE;
		}

		$response = $this->rpc->getResponse();
		$response['id'] = $response[0];
		foreach ( $response[ self::attributes ] as $key => $value ) {
			$response[ $key ] = $value;
		}
		return $response;
	}
}


<?php

/**
 * Sits on the Trac server and responds to calls from Trac_Notifications_HTTP_Client.
 */
class Trac_Notifications_HTTP_Server {
	function __construct( Trac_Notifications_DB $db, $secret ) {
		$this->db     = $db;
		$this->secret = $secret;
	}

	function serve_request() {
		$this->serve( $_GET['call'], $_GET['secret'], json_decode( $_POST['arguments'], true ) );
	}

	function serve( $method, $secret, $arguments ) {
		if ( ! method_exists( 'Trac_Notifications_DB', $method ) || $method[0] === '_' ) {
			header( ( $_SERVER["SERVER_PROTOCOL"] ?: 'HTTP/1.0' ) . ' 404 Method Not Found', true, 404 );
			exit;
		}

		if ( ! hash_equals( $this->secret,  $secret ) ) {
			header( ( $_SERVER["SERVER_PROTOCOL"] ?: 'HTTP/1.0' ) . ' 403 Forbidden', true, 403 );
			exit;
		}

		if ( $arguments ) {
			$result = call_user_func_array( array( $this->db, $method ), $arguments );
		} else {
			$result = call_user_func( array( $this->db, $method ) );
		}

		echo json_encode( $result );
		exit;
	}
}

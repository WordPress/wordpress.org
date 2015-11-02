<?php

class Trac_Notifications_HTTP_Client implements Trac_Notifications_API {
	function __construct( $target, $secret ) {
		$this->target = $target;
		$this->secret = $secret;
	}

	function __call( $method, $arguments ) {
		if ( ! method_exists( 'Trac_Notifications_DB', $method ) || $method[0] === '_' ) {
			return false;
		}

		$url = add_query_arg( array(
			'call'   => $method,
			'secret' => $this->secret,
		), $this->target );

		$args = array(
			'body' => array( 'arguments' => json_encode( $arguments ) ),
		);

		$response = wp_remote_post( $url, $args );

		if ( wp_remote_retrieve_response_code( $response ) != 200 ) {
			return false;
		}

		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
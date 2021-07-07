<?php
namespace WordPressdotorg\API\Trac\GithubPRs;

class Trac {
	protected $trac_uri = false;
	protected $credentials = [];

	public function __construct( $username, $password, $trac_uri ) {
		$this->trac_uri = $trac_uri;
		$this->credentials = [ $username, $password ];
	}

	/**
	 * Retrieve a Trac Ticket by ID.
	 */
	function get( $id ) {
		try {
			$response = $this->api( 'ticket.get', [ $id ] );
		} catch( \Exception $e ) {
			return false;
		}

		return [
			'id' => $response[0],
		] + (array) $response[3];
	}

	/**
	 * Update a Trac ticket to add a comment, or alter ticket properties.
	 *
	 * To set the Author or Time of a comment, the Trac API user must have TICKET_ADMIN priv.
	 */
	function update( $id, $comment, $attr = [], $notify = false, $author = false, $when = false ) {
		if ( empty( $attr['_ts'] ) ) {
			$get = $this->get( $id );
			$attr['_ts'] = $get['_ts'];
		}

		if ( empty( $attr['action'] ) ) {
			$attr['action'] = 'leave';
		}

		try {
			// Retrying this is safe, as the `_ts` parameter must be set correctly for it to succeed.
			$this->api( 'ticket.update', [ $id, $comment, $attr, (bool) $notify, $author, $when ] );

			return true;
		} catch( \Exception $e ) {
			// `_ts` may have been outdated, update and try again.
			if ( isset( $get ) && $attr['_ts'] === $get['_ts'] ) {
				$get = $this->get( $id ); // refetch the ticket.
				if ( $attr['_ts'] === $get['_ts'] ) {
					// Didn't change, api already retried, throw it.
					throw $e;
				}

				try {
					$attr['_ts'] = $get['_ts'];

					$this->api( 'ticket.update', [ $id, $comment, $attr, (bool) $notify, $author, $when ] );
					return true;
				} catch( \Exception $e ) {
					throw $e;
				}
			}

			return false;
		}
	}

	/**
	 * Retrieve ticket comments by Ticket ID.
	 */
	function get_comments( $id ) {
		try {
			$response = $this->api( 'ticket.changeLog', [ $id ] );
		} catch( \Exception $e ) {
			return false;
		}

		foreach ( $response as $i => $c ) {
			$response[ $i ] = [
				'time'      => $c[0],
				'author'    => $c[1],
				'field'     => $c[2],
				'oldvalue'  => $c[3],
				'newvalue'  => $c[4],
				'permanent' => $c[5],
			];
		}

		return $response;
	}

	/**
	 * Fetch/POST a Trac JSONRPC endpoint.
	 */
	public function api( $method, $params, $retry = 3 ) {
		$tries = 0;
		$retry = (int) $retry;

		do {
			try {
				return $this->_api( $method, $params );	
			} catch( \Exception $e ) {
				// Retry with a short delay
				sleep( 1 );
			}
		} while ( ++$tries < $retry );

		// If we make it this far, all retries have failed, throw that exception.
		throw $e;
	}

	protected function _api( $method, $params ) {
		$context = stream_context_create( [ 'http' => [
			'method'        => 'POST',
			'user_agent'    => 'WordPress.org Trac; trac.WordPress.org',
			'max_redirects' => 0,
			'timeout'       => 30,
			'ignore_errors' => true,
			'header'        =>
				[
					'Content-Type: application/json',
					'Cookie: ' . LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( get_user_by( 'login', $this->credentials[0] )->ID, time()+60, 'logged_in' ),
				],
			'content'       => json_encode( [
				'method' => $method,
				'params' => $this->trac_json_objectify( $params ),
			] ),
		] ] );

		$json = file_get_contents(
			$this->trac_uri,
			false,
			$context
		);

		$json = json_decode( $json );
		if ( $json && $json->result ) {
			$json = $json->result;
			$json = $this->trac_json_deobjectify( $json );

		} elseif ( $json && isset( $json->error ) ) {
			throw new \Exception( 'JSON Error: ' . $json->error->code . ' ' . $json->error->message );
		} elseif ( ! $json ) {
			throw new \Exception( 'Trac API Error: Trac Unavailable.' );
		}

		return $json;
	}

	/**
	 * Call the Back-channel WordPress Trac API.
	 *
	 * For valid $methods to call, see: https://meta.trac.wordpress.org/browser/sites/trunk/wordpress.org/public_html/wp-content/plugins/trac-notifications/trac-notifications-db.php
	 */
	public function wpapi( $method, $args = null ) {
		$wpapi_url = str_replace( '/login/rpc', '/wpapi/', $this->trac_uri );
		$wpapi_url .= '?call=' . $method;

		$context = stream_context_create( [ 'http' => [
			'method'        => 'POST',
			'user_agent'    => 'WordPress.org Trac; trac.WordPress.org',
			'max_redirects' => 0,
			'timeout'       => 30,
			'ignore_errors' => true,
			'header'        =>  [
				'Authorization: Basic ' . base64_encode( $this->credentials[0] . ':' . $this->credentials[1] ),
			],
			'content'       => http_build_query( [
				'secret'    => TRAC_NOTIFICATIONS_API_KEY,
				'arguments' => json_encode( $args ),
			] ),
		] ] );

		$json = file_get_contents(
			$wpapi_url,
			false,
			$context
		);

		return json_decode( $json );
	}

	/**
	 * Encodes DateTime objects into the Trac Date JSON format.
	 */
	protected function trac_json_objectify( $json ) {
		foreach ( $json as $k => $v ) {
			$value = $v;
			if ( is_object( $v ) && $v instanceof \DateTime ) {
				$value = (object) [
					'__jsonclass__' => [
						'datetime',
						$v->format('Y-m-d\TH:i:s')
					]
				];
			} elseif ( is_array( $v ) || is_object( $v ) ) {
				$value = $this->trac_json_objectify( $v );
			}

			if ( $value !== $v ) {
				if ( is_object( $json ) ) {
					$json->$k = $value;
				} else {
					$json[ $k ] = $value;
				}
			}
		}

		return $json;
	}

	/**
	 * Decodes a Trac JSON response into binary and DateTime objects where needed.
	 */
	protected function trac_json_deobjectify( $json ) {
		foreach ( $json as $k => $v ) {
			$value = $v;
			if ( is_object( $v ) && isset( $v->__jsonclass__ ) ) {
				switch ( $v->__jsonclass__[0] ) {
					case 'datetime':
						$value = new \DateTime( $v->__jsonclass__[1] );
						break;
					case 'binary':
						$value = base64_decode( $v->__jsonclass__[1] );
						break;
				}
			} elseif ( is_array( $v ) || is_object( $v ) ) {
				$value = $this->trac_json_deobjectify( $v );
			}

			if ( $value !== $v ) {
				if ( is_object( $json ) ) {
					$json->$k = $value;
				} else {
					$json[ $k ] = $value;
				}
			}
		}

		return $json;
	}
}


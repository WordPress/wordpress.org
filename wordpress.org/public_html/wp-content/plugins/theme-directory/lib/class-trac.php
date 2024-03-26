<?php
/**
 * Class to interact with Trac's RPC API.
 *
 * @package WordPressdotorg\Theme_Directory
 */

/**
 * Class Trac
 */
class Trac {

	/**
	 * Attributes key in RPC response array.
	 */
	const ATTRIBUTES = 3;

	/**
	 * Holds a reference to \WP_HTTP_IXR_Client.
	 *
	 * @var \WP_HTTP_IXR_Client
	 */
	protected $rpc;

	/**
	 * Trac constructor.
	 *
	 * @param string $username Trac username.
	 * @param string $password Trac password.
	 * @param string $host     Server to use.
	 * @param string $path     Path.
	 * @param int    $port     Which port to use. Default: 80.
	 * @param bool   $ssl      Whether to use SSL. Default: false.
	 */
	public function __construct( $username, $password, $host, $path = '/', $port = 80, $ssl = false ) {
		// Assume URL to $host, ignore $path, $port, $ssl.
		$this->rpc = new WP_HTTP_IXR_Client( $host, false, false, 60 );

		$http_basic_auth  = 'Basic ';
		$http_basic_auth .= base64_encode( $username . ':' . $password );

		$this->rpc->headers['Authorization'] = $http_basic_auth;

		// themes.trac requires both the Authorization header and the logged in Cookie.
		$user = get_user_by( 'login', $username );
		if ( $user ) {
			$this->rpc->headers['Cookie'] = LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $user->ID, time() + MINUTE_IN_SECONDS, 'logged_in' );
		}

	}

	/**
	 * Creates a new Trac ticket.
	 *
	 * @param string $subj Ticket subject line.
	 * @param string $desc Ticket description.
	 * @param array  $attr Ticket attributes. Default: Empty array.
	 * @return bool|mixed
	 */
	public function ticket_create( $subj, $desc, $attr = array() ) {
		if ( empty( $attr ) ) {
			$attr = new IXR_Value( array(), 'struct' );
		}

		$ok = $this->rpc->query( 'ticket.create', $subj, $desc, $attr );
		if ( ! $ok ) {
			trigger_error( 'Trac: ticket.create: ' . $this->rpc->error->message, E_USER_WARNING );

			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.InlineComment.InvalidEndChar
			// print_r( $this->rpc );
			return false;
		}

		return $this->rpc->getResponse();
	}

	/**
	 * Updates a Trac ticket.
	 *
	 * @param int    $id      Ticket number.
	 * @param string $comment Comment.
	 * @param array  $attr    Ticket attributes. Default: Empty array.
	 * @param bool   $notify  Whether to notify. Default: false.
	 * @return bool|mixed
	 */
	public function ticket_update( $id, $comment, $attr = array(), $notify = false ) {
		if ( empty( $attr['_ts'] ) ) {
			$get         = $this->ticket_get( $id );
			$attr['_ts'] = $get['_ts'];
		}
		if ( empty( $attr['action'] ) ) {
			$attr['action'] = 'leave';
		}

		$ok = $this->rpc->query( 'ticket.update', $id, $comment, $attr, $notify );
		if ( ! $ok ) {
			trigger_error( 'Trac: ticket.update: ' . $this->rpc->error->message, E_USER_WARNING );

			return false;
		}

		return $this->rpc->getResponse();
	}

	/**
	 * Queries Trac tickets.
	 *
	 * @param string $search Trac search query.
	 * @return bool|mixed
	 */
	public function ticket_query( $search ) {
		$ok = $this->rpc->query( 'ticket.query', $search );
		if ( ! $ok ) {
			return false;
		}

		return $this->rpc->getResponse();
	}

	/**
	 * Gets a specific Trac ticket.
	 *
	 * @param int $id Trac ticket id.
	 * @return [id, time_created, time_changed, attributes] or false on failure.
	 */
	public function ticket_get( $id ) {
		$ok = $this->rpc->query( 'ticket.get', $id );
		if ( ! $ok ) {
			return false;
		}

		$response       = $this->rpc->getResponse();
		$response['id'] = $response[0];
		foreach ( $response[ self::ATTRIBUTES ] as $key => $value ) {
			$response[ $key ] = $value;
		}

		return $response;
	}
}

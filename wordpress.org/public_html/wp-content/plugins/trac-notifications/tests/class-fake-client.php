<?php
/**
 * Test fake for Trac_Notifications_HTTP_Client. Each call records `method`
 * and `args` and returns whatever was last assigned to `$next_response`.
 *
 * @package WordPressdotorg\Trac
 */

/**
 * Scriptable HTTP client stand-in.
 */
class Fake_Client {

	/**
	 * Value returned by the next call to any method.
	 *
	 * @var mixed
	 */
	public $next_response = false;

	/**
	 * Recorded calls: array of array{method: string, args: array}.
	 *
	 * @var array
	 */
	public $calls = array();

	/**
	 * Match the Trac_Notifications_HTTP_Client::__call() shape — accept any
	 * method that would exist on Trac_Notifications_DB.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	public function __call( $method, $args ) {
		$this->calls[] = array(
			'method' => $method,
			'args'   => $args,
		);
		return $this->next_response;
	}
}

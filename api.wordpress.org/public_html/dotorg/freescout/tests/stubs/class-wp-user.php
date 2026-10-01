<?php
/**
 * Stand-in for WordPress's user class, for tests that run without WordPress.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

/**
 * Stand-in for WordPress's user class.
 */
class WP_User {

	/**
	 * User ID.
	 *
	 * @var int
	 */
	public $ID;

	/**
	 * Email address.
	 *
	 * @var string
	 */
	public $user_email;

	/**
	 * Username, which doubles as the profile slug here.
	 *
	 * @var string
	 */
	public $user_login;

	/**
	 * Constructor.
	 *
	 * @param int    $id         User ID.
	 * @param string $user_email Email address.
	 * @param string $user_login Username.
	 */
	public function __construct( int $id, string $user_email, string $user_login ) {
		$this->ID         = $id;
		$this->user_email = $user_email;
		$this->user_login = $user_login;
	}
}

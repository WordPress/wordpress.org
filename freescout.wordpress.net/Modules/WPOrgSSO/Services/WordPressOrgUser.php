<?php
/**
 * A WordPress.org account, as api.wordpress.org describes it.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Services;

use RuntimeException;

/**
 * Looks up WordPress.org accounts through api.wordpress.org/dotorg/freescout/account.php.
 */
final class WordPressOrgUser {

	/**
	 * Username (user_login).
	 *
	 * @var string
	 */
	public $username;

	/**
	 * Profile URL.
	 *
	 * @var string
	 */
	public $profile_url;

	/**
	 * First name, or the display name if the account has none.
	 *
	 * @var string
	 */
	public $first_name;

	/**
	 * Last name.
	 *
	 * @var string
	 */
	public $last_name;

	/**
	 * Email address.
	 *
	 * @var string
	 */
	public $email;

	/**
	 * Avatar URL; Gravatar's silhouette if the account has no avatar.
	 *
	 * @var string
	 */
	public $avatar_url;

	/**
	 * Whether the account signs in with two-factor authentication.
	 *
	 * @var bool
	 */
	public $two_factor;

	/**
	 * Whether the account is blocked from logging in.
	 *
	 * @var bool
	 */
	public $blocked;

	/**
	 * Constructor.
	 *
	 * @param array $data Account, as account.php returns it.
	 */
	public function __construct( array $data ) {
		$this->username    = (string) ( $data['username'] ?? '' );
		$this->profile_url = (string) ( $data['profile_url'] ?? '' );
		$this->first_name  = (string) ( $data['first_name'] ?? '' );
		$this->last_name   = (string) ( $data['last_name'] ?? '' );
		$this->email       = (string) ( $data['email'] ?? '' );
		$this->avatar_url  = (string) ( $data['avatar_url'] ?? '' );
		$this->two_factor  = ! empty( $data['two_factor'] );
		$this->blocked     = ! empty( $data['blocked'] );

		if ( '' === $this->first_name ) {
			$this->first_name = (string) ( $data['display_name'] ?? '' );
		}

		if ( '' === $this->first_name ) {
			$this->first_name = $this->username;
		}
	}

	/**
	 * Looks up an account by username or profile slug.
	 *
	 * @param string $username Username or profile slug.
	 * @param Client $client   Signed client for api.wordpress.org.
	 * @return self|null Null if there's no such account.
	 *
	 * @throws RuntimeException If api.wordpress.org can't be reached or answers with nonsense.
	 */
	public static function find( string $username, Client $client ): ?self {
		$username = trim( $username );
		if ( '' === $username ) {
			return null;
		}

		$response = $client->post( 'account.php', array( 'username' => $username ) );
		if ( ! array_key_exists( 'user', $response ) ) {
			throw new RuntimeException( 'Invalid response from account.php.' );
		}

		if ( ! is_array( $response['user'] ) || empty( $response['user']['username'] ) ) {
			return null;
		}

		return new self( $response['user'] );
	}

	/**
	 * Why this account may not log in as a username, if it may not.
	 *
	 * Only the account with that exact username counts; account.php also finds accounts by profile slug.
	 *
	 * @param string $username Username that logged in.
	 * @return string Error message, empty if the account may log in.
	 */
	public function login_error( string $username ): string {
		if ( 0 !== strcasecmp( $this->username, $username ) || $this->blocked ) {
			return __( 'The WordPress.org account :username has no access to this helpdesk.', array( 'username' => $username ) );
		}

		if ( ! $this->two_factor ) {
			return __( 'Turn on two-factor authentication for your WordPress.org account to log in.' );
		}

		return '';
	}

	/**
	 * The fields FreeScout's user form expects, cut to its maximum lengths.
	 *
	 * @return array
	 */
	public function user_fields(): array {
		return array(
			'first_name' => mb_substr( $this->first_name, 0, 20 ),
			'last_name'  => mb_substr( $this->last_name, 0, 30 ),
			'email'      => $this->email,
		);
	}

	/**
	 * The details shown while connecting an account.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array_merge(
			$this->user_fields(),
			array(
				'username'    => $this->username,
				'profile_url' => $this->profile_url,
				'two_factor'  => $this->two_factor,
				'blocked'     => $this->blocked,
			)
		);
	}
}

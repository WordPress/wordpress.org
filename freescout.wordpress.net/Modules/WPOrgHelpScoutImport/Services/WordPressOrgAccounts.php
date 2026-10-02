<?php
/**
 * Connects HelpScout users' FreeScout users to WordPress.org accounts in bulk, from a CSV.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\User;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\UserSync;
use Modules\WPOrgSSO\Services\WordPressOrgUser;

/**
 * Exports HelpScout's users as a CSV to fill in WordPress.org usernames, checks a filled-in one, and connects them.
 *
 * Connecting goes through WP.org SSO, like connecting a user on their profile: their name, email, and avatar come
 * from WordPress.org, and they can log in with it.
 */
final class WordPressOrgAccounts {

	/**
	 * The CSV's columns; `wporg_username` is the one to fill in.
	 *
	 * @var string[]
	 */
	public const COLUMNS = array( 'helpscout_id', 'first_name', 'last_name', 'email', 'mailboxes', 'former', 'freescout_user', 'wporg_username' );

	/**
	 * Use the FreeScout user connected to the account already.
	 *
	 * @var string
	 */
	public const USE = 'use';

	/**
	 * Connect the HelpScout user's FreeScout user, or the one with the account's email, to the account.
	 *
	 * @var string
	 */
	public const CONNECT = 'connect';

	/**
	 * Create a FreeScout user for the HelpScout user, connected to the account.
	 *
	 * @var string
	 */
	public const CREATE = 'create';

	/**
	 * Nothing to do: the HelpScout user's FreeScout user is connected to the account already.
	 *
	 * @var string
	 */
	public const DONE = 'done';

	/**
	 * How long WordPress.org accounts looked up are cached, in minutes: between checking a CSV and connecting it.
	 *
	 * @var int
	 */
	private const LOOKUP_CACHE_MINUTES = 30;

	/**
	 * HelpScout's users.
	 *
	 * @var People
	 */
	private $people;

	/**
	 * Constructor.
	 *
	 * @param People $people HelpScout's users, with a HelpScout client.
	 */
	public function __construct( People $people ) {
		$this->people = $people;
	}

	/**
	 * Whether WP.org SSO is on to look accounts up, and connect users to them: FreeScout loads active modules' providers.
	 *
	 * @return bool
	 */
	public static function available(): bool {
		return class_exists( WordPressOrgUser::class )
			&& class_exists( Account::class )
			&& (bool) app()->getProviders( \Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider::class );
	}

	/**
	 * HelpScout's users, and those it no longer lists that imports met, as a CSV; teams are left out.
	 *
	 * @return string
	 */
	public function export(): string {
		$directory = $this->people->directory();
		$rows      = array( self::COLUMNS );

		foreach ( People::agents( array_merge( $directory, People::former( $directory ) ) ) as $agent ) {
			$helpscout_user = $this->people->helpscout_user( $agent['id'] ) ?? array();
			$rows[]         = array(
				$agent['id'],
				(string) ( $helpscout_user['firstName'] ?? '' ),
				(string) ( $helpscout_user['lastName'] ?? '' ),
				$agent['email'],
				implode( '; ', $agent['mailboxes'] ),
				$agent['former'] ? 'yes' : 'no',
				$agent['user'] ? (string) $agent['user']->email : '',
				$agent['user'] ? Account::username_for( (int) $agent['user']->id ) : '',
			);
		}

		$file = fopen( 'php://temp', 'r+' );
		foreach ( $rows as $row ) {
			fputcsv( $file, array_map( array( self::class, 'cell' ), $row ), ',', '"', '' );
		}
		rewind( $file );
		$csv = (string) stream_get_contents( $file );
		fclose( $file );

		return $csv;
	}

	/**
	 * Reads the rows of a filled-in CSV that name a WordPress.org account.
	 *
	 * @param string $csv CSV with a header row: `helpscout_id` or `email`, and `wporg_username`.
	 * @return array[] Each with `helpscout_id` (0 if the row has none), `email`, and `username`.
	 */
	public static function parse( string $csv ): array {
		$lines  = preg_split( '/\r\n|\r|\n/', trim( $csv ) );
		$header = array_map( 'strtolower', array_map( 'trim', str_getcsv( (string) array_shift( $lines ), ',', '"', '' ) ) );
		$rows   = array();

		foreach ( $lines as $line ) {
			if ( '' === trim( $line ) ) {
				continue;
			}

			$cells = array_map( 'trim', str_getcsv( $line, ',', '"', '' ) );
			$row   = array_combine( $header, array_pad( array_slice( $cells, 0, count( $header ) ), count( $header ), '' ) );

			$username = (string) ( $row['wporg_username'] ?? '' );
			if ( '' === $username ) {
				continue;
			}

			$rows[] = array(
				'helpscout_id' => (int) ( $row['helpscout_id'] ?? 0 ),
				'email'        => (string) ( $row['email'] ?? '' ),
				'username'     => $username,
			);
		}

		return $rows;
	}

	/**
	 * What connecting a CSV's rows would do, without doing it.
	 *
	 * @param array[] $rows Rows, from parse().
	 * @return array[] Each with `row`, `helpscout_user` (or null), `wporg_user` (WordPressOrgUser, or null), `action`
	 *                 (one of the constants, or null), `user` (the FreeScout user it's for, or null for a new one), and
	 *                 `error` (why nothing can be done, or '').
	 */
	public function plan( array $rows ): array {
		$client = Client::from_config();
		$plan   = array();

		foreach ( $rows as $row ) {
			$plan[] = $this->plan_row( $row, $client );
		}

		return $plan;
	}

	/**
	 * Connects what plan() says can be: creates users, connects them, and credits HelpScout users to them.
	 *
	 * @param array[] $plan From plan().
	 * @return User[] The users connected or credited, by HelpScout user ID.
	 */
	public function apply( array $plan ): array {
		$done = array();

		foreach ( $plan as $step ) {
			if ( ! $step['action'] || self::DONE === $step['action'] ) {
				continue;
			}

			// Another row may have connected the account since: two HelpScout users of the same person.
			$connected = Account::user_for( $step['wporg_user']->username );

			$user = \DB::transaction(
				function () use ( $step, $connected ): User {
					$user = $connected ?? $step['user'] ?? $this->people->find_or_create( $step['helpscout_user'] );

					if ( ! $connected ) {
						Account::connect( (int) $user->id, $step['wporg_user']->username );
					}

					People::choose( (int) $step['helpscout_user']['id'], $user );

					return $user;
				}
			);

			// Name, email, and avatar from WordPress.org, as WP.org SSO does when connecting a user on their profile.
			if ( ! $connected ) {
				UserSync::sync( $user, $step['wporg_user'] );
			}

			$done[ (int) $step['helpscout_user']['id'] ] = $user;
		}

		return $done;
	}

	/**
	 * What connecting one row would do.
	 *
	 * @param array  $row    Row, from parse().
	 * @param Client $client Client for api.wordpress.org.
	 * @return array See plan().
	 */
	private function plan_row( array $row, Client $client ): array {
		$step = array(
			'row'            => $row,
			'helpscout_user' => $row['helpscout_id'] ? $this->people->helpscout_user( $row['helpscout_id'] ) : $this->by_email( $row['email'] ),
			'wporg_user'     => null,
			'action'         => null,
			'user'           => null,
			'error'          => '',
		);

		if ( ! $step['helpscout_user'] ) {
			return array( 'error' => __( 'No HelpScout user with that ID or email.' ) ) + $step;
		}

		try {
			$step['wporg_user'] = $this->lookup( $row['username'], $client );
		} catch ( \Throwable $e ) {
			return array( 'error' => __( 'WordPress.org could not be reached. Please try again in a moment.' ) ) + $step;
		}

		if ( ! $step['wporg_user'] ) {
			return array( 'error' => __( 'There is no WordPress.org account with that username.' ) ) + $step;
		}

		$username = $step['wporg_user']->username;
		$existing = People::existing( $step['helpscout_user'] );

		// Someone with a FreeScout user connected to the account already is that user.
		$connected = Account::user_for( $username );
		if ( $connected ) {
			$action = $existing && (int) $existing->id === (int) $connected->id ? self::DONE : self::USE;

			return array(
				'action' => $action,
				'user'   => $connected,
			) + $step;
		}

		// Otherwise their FreeScout user, or the one with the account's email, is connected; or one is created.
		$user = $existing ?? People::by_email( $step['wporg_user']->email );
		if ( $user && '' !== Account::username_for( (int) $user->id ) ) {
			return array(
				'user'  => $user,
				'error' => __(
					':name is connected to the WordPress.org account :username already.',
					array(
						'name'     => $user->getFullName(),
						'username' => Account::username_for( (int) $user->id ),
					)
				),
			) + $step;
		}

		return array(
			'action' => $user ? self::CONNECT : self::CREATE,
			'user'   => $user,
		) + $step;
	}

	/**
	 * A HelpScout user by email, for a row without an ID.
	 *
	 * @param string $email Email.
	 * @return array|null
	 */
	private function by_email( string $email ): ?array {
		if ( '' === $email ) {
			return null;
		}

		$directory = $this->people->directory();
		foreach ( array_merge( $directory, People::former( $directory ) ) as $helpscout_user ) {
			if ( ! People::is_team( $helpscout_user ) && 0 === strcasecmp( (string) ( $helpscout_user['email'] ?? '' ), $email ) ) {
				return $helpscout_user;
			}
		}

		return null;
	}

	/**
	 * Looks a WordPress.org account up, cached between checking a CSV and connecting it.
	 *
	 * @param string $username Username or profile slug.
	 * @param Client $client   Client for api.wordpress.org.
	 * @return WordPressOrgUser|null
	 *
	 * @throws \RuntimeException If api.wordpress.org can't be reached.
	 */
	private function lookup( string $username, Client $client ): ?WordPressOrgUser {
		$key    = 'wporghelpscoutimport.wporg.' . md5( mb_strtolower( $username ) );
		$cached = \Cache::get( $key );
		if ( is_array( $cached ) ) {
			return new WordPressOrgUser( $cached );
		}

		$wporg_user = WordPressOrgUser::find( $username, $client );
		if ( $wporg_user ) {
			\Cache::put( $key, get_object_vars( $wporg_user ), self::LOOKUP_CACHE_MINUTES );
		}

		return $wporg_user;
	}

	/**
	 * A CSV cell that spreadsheets won't run as a formula.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function cell( $value ): string {
		$value = (string) $value;

		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ? "'" . $value : $value;
	}
}

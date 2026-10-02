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
				$agent['user'] && self::available() ? Account::username_for( (int) $agent['user']->id ) : '',
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
		// Read as CSV, not line by line: a quoted cell can hold a line break.
		$file = fopen( 'php://temp', 'r+' );
		fwrite( $file, (string) preg_replace( '/^\xEF\xBB\xBF/', '', $csv ) );
		rewind( $file );

		$header = array_map( 'strtolower', array_map( array( self::class, 'uncell' ), (array) fgetcsv( $file, 0, ',', '"', '' ) ) );
		$rows   = array();

		for ( $cells = fgetcsv( $file, 0, ',', '"', '' ); false !== $cells; $cells = fgetcsv( $file, 0, ',', '"', '' ) ) {
			if ( array( null ) === $cells ) {
				continue;
			}

			$cells = array_map( array( self::class, 'uncell' ), $cells );
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
		fclose( $file );

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
		$client    = Client::from_config();
		$plan      = array();
		$people    = array();
		$targets   = array();
		$usernames = array();

		foreach ( $rows as $row ) {
			$step = $this->plan_row( $row, $client );
			$id   = (int) ( $step['helpscout_user']['id'] ?? 0 );
			$user = $step['user'] ? (int) $step['user']->id : 0;

			// One account per person: a CSV can't connect anyone twice, one account twice, or one FreeScout user to two.
			if ( ! $step['error'] && isset( $usernames[ $step['wporg_user']->username ] ) ) {
				$step = array(
					'action' => null,
					'error'  => __( 'Another row names this WordPress.org account already.' ),
				) + $step;
			} elseif ( ! $step['error'] && isset( $people[ $id ] ) ) {
				$step = array(
					'action' => null,
					'error'  => __( 'Another row is for this HelpScout user already.' ),
				) + $step;
			} elseif ( ! $step['error'] && $user && isset( $targets[ $user ] ) && $targets[ $user ] !== $step['wporg_user']->username ) {
				$step = array(
					'action' => null,
					'error'  => __( 'Another row connects this FreeScout user to :username.', array( 'username' => $targets[ $user ] ) ),
				) + $step;
			}

			if ( ! $step['error'] ) {
				$usernames[ $step['wporg_user']->username ] = true;
				$people[ $id ]                              = true;
				if ( $user ) {
					$targets[ $user ] = $step['wporg_user']->username;
				}
			}

			$plan[] = $step;
		}

		return $plan;
	}

	/**
	 * Connects what plan() says can be: creates users, connects them, and credits HelpScout users to them.
	 *
	 * @param array[] $plan      From plan().
	 * @param int[]   $confirmed HelpScout user IDs of rows whose account doesn't look like theirs, connected anyway.
	 * @return array `done`: the users connected or credited, by HelpScout user ID; `skipped`: why the rest weren't.
	 */
	public function apply( array $plan, array $confirmed = array() ): array {
		$done    = array();
		$skipped = array();

		foreach ( $plan as $step ) {
			if ( ! $step['action'] || self::DONE === $step['action'] ) {
				continue;
			}

			$username = $step['wporg_user']->username;
			$id       = (int) $step['helpscout_user']['id'];

			if ( $step['mismatch'] && ! in_array( $id, array_map( 'intval', $confirmed ), true ) ) {
				$skipped[ $id ] = __( 'Its account doesn’t look like theirs, and wasn’t confirmed.' );
				continue;
			}

			// Another row may have connected the account since: two HelpScout users of the same person.
			$connected = Account::user_for( $username );

			try {
				$user = \DB::transaction(
					function () use ( $step, $connected, $username, $id ): User {
						$user = $connected ?? $step['user'] ?? $this->people->find_or_create( $step['helpscout_user'] );

						// Checked again: what plan() saw may have changed, like another row connecting the same user.
						if ( ! $connected ) {
							$error = self::connect_error( $user, $step['wporg_user'] );
							if ( '' !== $error ) {
								throw new \RuntimeException( $error );
							}

							Account::connect( (int) $user->id, $username );
						}

						People::choose( $id, $user );

						return $user;
					}
				);
			} catch ( \RuntimeException $e ) {
				$skipped[ $id ] = $e->getMessage();
				continue;
			}

			// Name, email, and avatar from WordPress.org, as WP.org SSO does when connecting a user on their profile.
			if ( ! $connected ) {
				UserSync::sync( $user, $step['wporg_user'] );
			}

			$done[ $id ] = $user;
		}

		return array(
			'done'    => $done,
			'skipped' => $skipped,
		);
	}

	/**
	 * Why a FreeScout user can't be connected to a WordPress.org account in bulk, if they can't.
	 *
	 * Connecting someone lets the account's owner log in as them. Only users an import created, who aren't
	 * administrators, or whose email is the account's, are connected in bulk; others are connected on their profile.
	 *
	 * @param User             $user       FreeScout user.
	 * @param WordPressOrgUser $wporg_user WordPress.org account.
	 * @return string Error, or '' if they can be.
	 */
	private static function connect_error( User $user, WordPressOrgUser $wporg_user ): string {
		$connected = Account::username_for( (int) $user->id );
		if ( '' !== $connected ) {
			return __(
				':name is connected to the WordPress.org account :username already.',
				array(
					'name'     => $user->getFullName(),
					'username' => $connected,
				)
			);
		}

		$same_email = '' !== $wporg_user->email && 0 === strcasecmp( (string) $user->email, $wporg_user->email );
		if ( ! $same_email && ( $user->isAdmin() || ! People::was_created( $user ) ) ) {
			return __( ':name was in FreeScout before the import, with another email than the account’s: connect them on their profile.', array( 'name' => $user->getFullName() ) );
		}

		return '';
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
			'mismatch'       => false,
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
				'action'   => $action,
				'user'     => $connected,
				'mismatch' => self::USE === $action && self::mismatch( $step['helpscout_user'], $step['wporg_user'] ),
			) + $step;
		}

		// Otherwise their FreeScout user, or the one with the account's email, is connected; or one is created.
		$user  = $existing ?? People::by_email( $step['wporg_user']->email );
		$error = $user ? self::connect_error( $user, $step['wporg_user'] ) : '';
		if ( '' !== $error ) {
			return array(
				'user'  => $user,
				'error' => $error,
			) + $step;
		}

		return array(
			'action'   => $user ? self::CONNECT : self::CREATE,
			'user'     => $user,
			'mismatch' => self::mismatch( $step['helpscout_user'], $step['wporg_user'] ),
		) + $step;
	}

	/**
	 * Whether a WordPress.org account looks like someone else's than a HelpScout user's: neither name nor email match.
	 *
	 * Connecting it lets its owner log in as the user, so a username a CSV got wrong has to be confirmed.
	 *
	 * @param array            $helpscout_user HelpScout user.
	 * @param WordPressOrgUser $wporg_user     WordPress.org account.
	 * @return bool
	 */
	private static function mismatch( array $helpscout_user, WordPressOrgUser $wporg_user ): bool {
		$email = (string) ( $helpscout_user['email'] ?? '' );
		$name  = trim( ( $helpscout_user['firstName'] ?? '' ) . ' ' . ( $helpscout_user['lastName'] ?? '' ) );

		$same_email = '' !== $email && 0 === strcasecmp( $email, $wporg_user->email );
		$same_name  = '' !== $name && 0 === strcasecmp( $name, trim( $wporg_user->first_name . ' ' . $wporg_user->last_name ) );

		return ! $same_email && ! $same_name;
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
	 * A CSV cell that spreadsheets won't run as a formula, without control characters, like line breaks.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function cell( $value ): string {
		$value = (string) preg_replace( '/[\x00-\x1F\x7F]+/', ' ', (string) $value );

		return '' !== $value && in_array( $value[0], array( '=', '+', '-', '@' ), true ) ? "'" . $value : $value;
	}

	/**
	 * A cell's value, without what cell() added, and the spaces around it.
	 *
	 * @param mixed $value Cell.
	 * @return string
	 */
	private static function uncell( $value ): string {
		$value = trim( (string) $value );

		return (string) preg_replace( "/^'(?=[=+\-@])/", '', $value );
	}
}

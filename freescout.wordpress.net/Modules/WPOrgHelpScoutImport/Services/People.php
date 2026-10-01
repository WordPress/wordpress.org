<?php
/**
 * Finds the FreeScout users and senders for the people in HelpScout's data.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\Customer;
use App\User;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;
use Modules\WPOrgHelpScoutImport\Entities\Person;

/**
 * HelpScout users become the FreeScout users administrators chose, or with the same email; senders become senders.
 */
final class People {

	/**
	 * Email of the user that HelpScout users without a FreeScout user are credited to.
	 *
	 * Core's own robot users have addresses like this.
	 *
	 * @var string
	 */
	public const ROBOT_EMAIL = 'fs-helpscout-import@example.org';

	/**
	 * Cache key for HelpScout's users and their mailboxes.
	 *
	 * @var string
	 */
	private const DIRECTORY_CACHE_KEY = 'wporghelpscoutimport.directory';

	/**
	 * How long HelpScout's users and their mailboxes are cached, in minutes.
	 *
	 * @var int
	 */
	private const DIRECTORY_CACHE_MINUTES = 60;

	/**
	 * FreeScout users found so far, by HelpScout user ID; null if there's none.
	 *
	 * @var array
	 */
	private $users = array();

	/**
	 * HelpScout user IDs remember() kept so far.
	 *
	 * @var true[]
	 */
	private $remembered = array();

	/**
	 * The FreeScout user for a HelpScout user: the one an administrator chose, or else the one with their email.
	 *
	 * @param mixed $person HelpScout person object, like a thread's `createdBy`.
	 * @return User|null Null if it isn't a HelpScout user, or there's no FreeScout user for them.
	 */
	public function user( $person ): ?User {
		if ( ! is_array( $person ) || 'user' !== ( $person['type'] ?? '' ) ) {
			return null;
		}

		$id = (int) ( $person['id'] ?? 0 );
		if ( ! array_key_exists( $id, $this->users ) ) {
			$this->users[ $id ] = self::chosen( $id ) ?? self::by_email( (string) ( $person['email'] ?? '' ) );
		}

		return $this->users[ $id ];
	}

	/**
	 * Keeps who a HelpScout user is, for users HelpScout no longer lists once they're deleted.
	 *
	 * @param mixed $person HelpScout person object, like a thread's `createdBy`.
	 * @return int|null Their HelpScout user ID, or null if it isn't a HelpScout user.
	 */
	public function remember( $person ): ?int {
		$id = is_array( $person ) && 'user' === ( $person['type'] ?? '' ) ? (int) ( $person['id'] ?? 0 ) : 0;
		if ( ! $id ) {
			return null;
		}

		if ( ! isset( $this->remembered[ $id ] ) ) {
			Person::query()->updateOrCreate(
				array( 'helpscout_user_id' => $id ),
				array(
					'first_name' => mb_substr( (string) ( $person['first'] ?? '' ), 0, 100 ),
					'last_name'  => mb_substr( (string) ( $person['last'] ?? '' ), 0, 100 ),
					'email'      => '' !== (string) ( $person['email'] ?? '' ) ? mb_substr( (string) $person['email'], 0, 191 ) : null,
				)
			);
			$this->remembered[ $id ] = true;
		}

		return $id;
	}

	/**
	 * Credits a HelpScout user's imported replies and notes to whoever they're credited to now.
	 *
	 * @param int $helpscout_user_id HelpScout user ID.
	 * @return void
	 */
	public static function recredit( int $helpscout_user_id ): void {
		$person = Person::query()->where( 'helpscout_user_id', $helpscout_user_id )->first();
		if ( ! $person ) {
			return;
		}

		$people = new self();
		$user   = $people->user(
			array(
				'type'  => 'user',
				'id'    => $helpscout_user_id,
				'email' => (string) $person->email,
			)
		) ?? $people->robot();

		// One query, without the events of changed threads: imported ones were written without them too.
		\App\Thread::query()
			->whereIn(
				'id',
				ImportedThread::query()->select( 'thread_id' )->where( 'helpscout_user_id', $helpscout_user_id )->getQuery()
			)
			->where( 'created_by_user_id', '!=', $user->id )
			->update( array( 'created_by_user_id' => $user->id ) );
	}

	/**
	 * HelpScout users the importer met who HelpScout no longer lists, shaped like directory()'s.
	 *
	 * @param array[] $directory HelpScout's users, from directory().
	 * @return array[]
	 */
	public static function former( array $directory ): array {
		$listed = array_map( 'intval', array_column( $directory, 'id' ) );

		return Person::query()
			->whereNotIn( 'helpscout_user_id', $listed ? $listed : array( 0 ) )
			->get()
			->map(
				static function ( Person $person ): array {
					return array(
						'id'        => (int) $person->helpscout_user_id,
						'type'      => 'user',
						'firstName' => $person->first_name,
						'lastName'  => $person->last_name,
						'email'     => (string) $person->email,
						'mailboxes' => array(),
						'former'    => true,
					);
				}
			)
			->all();
	}

	/**
	 * Lists HelpScout's users with the mailboxes each can see; cached, since that takes a request per mailbox.
	 *
	 * @param HelpScout $helpscout HelpScout API client.
	 * @param bool      $refresh   Whether to ask HelpScout again rather than use the cache.
	 * @return array[] Users, as HelpScout lists them, each with `mailboxes`: names by HelpScout mailbox ID.
	 */
	public static function directory( HelpScout $helpscout, bool $refresh = false ): array {
		if ( $refresh ) {
			\Cache::forget( self::DIRECTORY_CACHE_KEY );
		}

		return (array) \Cache::remember(
			self::DIRECTORY_CACHE_KEY,
			self::DIRECTORY_CACHE_MINUTES,
			static function () use ( $helpscout ): array {
				$users = array();
				foreach ( $helpscout->users() as $user ) {
					$users[ (int) $user['id'] ] = $user + array( 'mailboxes' => array() );
				}

				foreach ( $helpscout->mailboxes() as $mailbox ) {
					foreach ( $helpscout->users( (int) $mailbox['id'] ) as $user ) {
						if ( isset( $users[ (int) $user['id'] ] ) ) {
							$users[ (int) $user['id'] ]['mailboxes'][ (int) $mailbox['id'] ] = (string) $mailbox['name'];
						}
					}
				}

				return array_values( $users );
			}
		);
	}

	/**
	 * Lists HelpScout users with the FreeScout user each is credited to, and one to suggest when there's none.
	 *
	 * HelpScout lists its teams as users too; they're left out, since they never write anything.
	 *
	 * @param array[] $helpscout_users HelpScout users, as directory() lists them.
	 * @return array[] By name, each with `id`, `name`, `email`, `mailboxes`, `chosen` (the chosen user, or null),
	 *                 `by_email` (the user with their email, or null), and `suggested` (the only user with their name,
	 *                 if neither is set, or null).
	 */
	public function agents( array $helpscout_users ): array {
		$agents = array();

		foreach ( $helpscout_users as $helpscout_user ) {
			if ( self::is_team( $helpscout_user ) ) {
				continue;
			}

			$id       = (int) ( $helpscout_user['id'] ?? 0 );
			$name     = trim( ( $helpscout_user['firstName'] ?? '' ) . ' ' . ( $helpscout_user['lastName'] ?? '' ) );
			$chosen   = self::chosen( $id );
			$by_email = self::by_email( (string) ( $helpscout_user['email'] ?? '' ) );

			$agents[] = array(
				'id'        => $id,
				'name'      => $name,
				'email'     => (string) ( $helpscout_user['email'] ?? '' ),
				'mailboxes' => (array) ( $helpscout_user['mailboxes'] ?? array() ),
				'former'    => ! empty( $helpscout_user['former'] ),
				'chosen'    => $chosen,
				'by_email'  => $by_email,
				'suggested' => $chosen || $by_email ? null : self::by_name( (string) ( $helpscout_user['firstName'] ?? '' ), (string) ( $helpscout_user['lastName'] ?? '' ) ),
			);
		}

		usort(
			$agents,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $agents;
	}

	/**
	 * Whether a HelpScout user is a team: conversations are assigned to teams, which have no email.
	 *
	 * @param array $helpscout_user HelpScout user, as HelpScout lists them.
	 * @return bool
	 */
	public static function is_team( array $helpscout_user ): bool {
		return 'team' === ( $helpscout_user['type'] ?? 'user' );
	}

	/**
	 * Whether a listed HelpScout user would be credited to the robot.
	 *
	 * @param array $agent HelpScout user, as agents() lists them.
	 * @return bool
	 */
	public static function is_unmatched( array $agent ): bool {
		return ! $agent['chosen'] && ! $agent['by_email'];
	}

	/**
	 * The FreeScout users HelpScout users can be credited to: people, not robots, and not deleted.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function creditable(): \Illuminate\Database\Eloquent\Builder {
		return User::query()
			->where( 'type', '!=', User::TYPE_ROBOT )
			->where( 'status', '!=', User::STATUS_DELETED );
	}

	/**
	 * The only FreeScout user with a name, to suggest; null if there's none, or more than one.
	 *
	 * @param string $first_name First name.
	 * @param string $last_name  Last name.
	 * @return User|null
	 */
	private static function by_name( string $first_name, string $last_name ): ?User {
		if ( '' === trim( $first_name ) || '' === trim( $last_name ) ) {
			return null;
		}

		$users = self::creditable()->where( 'first_name', trim( $first_name ) )->where( 'last_name', trim( $last_name ) )->limit( 2 )->get();

		return 1 === $users->count() ? $users->first() : null;
	}

	/**
	 * The FreeScout user an administrator chose for a HelpScout user.
	 *
	 * @param int $helpscout_user_id HelpScout user ID.
	 * @return User|null
	 */
	private static function chosen( int $helpscout_user_id ): ?User {
		$user_id = $helpscout_user_id ? Agent::query()->where( 'helpscout_user_id', $helpscout_user_id )->value( 'user_id' ) : null;

		return $user_id ? User::find( (int) $user_id ) : null;
	}

	/**
	 * The FreeScout user with an email.
	 *
	 * @param string $email Email.
	 * @return User|null
	 */
	private static function by_email( string $email ): ?User {
		return '' !== $email ? User::query()->where( 'email', mb_strtolower( $email ) )->first() : null;
	}

	/**
	 * The user that HelpScout users without a FreeScout user are credited to, created when first needed.
	 *
	 * A disabled robot: it can't log in, and isn't counted as an agent.
	 *
	 * @return User
	 */
	public function robot(): User {
		$robot = User::query()->where( 'email', self::ROBOT_EMAIL )->first();
		if ( $robot ) {
			return $robot;
		}

		$robot             = new User();
		$robot->first_name = 'HelpScout';
		$robot->last_name  = 'Import';
		$robot->email      = self::ROBOT_EMAIL;
		$robot->password   = User::getDummyPassword();
		$robot->role       = User::ROLE_USER;
		$robot->type       = User::TYPE_ROBOT;
		$robot->status     = User::STATUS_DISABLED;
		$robot->save();

		return $robot;
	}

	/**
	 * The FreeScout sender for a HelpScout customer, created if there's none with their email yet.
	 *
	 * @param mixed $person HelpScout person object, like a conversation's `primaryCustomer`.
	 * @return Customer|null Null if it has no email.
	 */
	public function sender( $person ): ?Customer {
		if ( ! is_array( $person ) || empty( $person['email'] ) ) {
			return null;
		}

		$customer = Customer::create(
			(string) $person['email'],
			array(
				'first_name' => (string) ( $person['first'] ?? '' ),
				'last_name'  => (string) ( $person['last'] ?? '' ),
			)
		);

		return $customer instanceof Customer ? $customer : null;
	}
}

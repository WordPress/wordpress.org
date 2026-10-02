<?php
/**
 * Finds or creates the FreeScout users and senders for the people in HelpScout's data.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\Conversation;
use App\Customer;
use App\Email;
use App\Mailbox;
use App\Thread;
use App\User;
use Modules\WPOrgHelpScoutImport\Entities\Agent;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;
use Modules\WPOrgHelpScoutImport\Entities\Person;

/**
 * Every HelpScout user gets a FreeScout user: the one an administrator chose, the one with their email, or a new one.
 *
 * New users are active while HelpScout still lists them, and disabled once it doesn't. HelpScout's teams become the
 * FreeScout teams (the Teams module's users) an administrator chose, or with the same name; senders become senders.
 */
final class People {

	/**
	 * Email of the user that what nobody in particular wrote is credited to, like HelpScout's own actions.
	 *
	 * Core's own robot users have addresses like this.
	 *
	 * @var string
	 */
	public const ROBOT_EMAIL = 'fs-helpscout-import@example.org';

	/**
	 * Alias of FreeScout's Teams module, whose teams are robot users.
	 *
	 * @var string
	 */
	public const TEAMS_MODULE = 'teams';

	/**
	 * Sender meta key for the HelpScout ID of a sender without an email.
	 *
	 * @var string
	 */
	private const SENDER_META = 'wporghelpscoutimport';

	/**
	 * Domain of the emails given to users whose HelpScout email can't be a FreeScout user's.
	 *
	 * @var string
	 */
	private const PLACEHOLDER_DOMAIN = 'helpscout.invalid';

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
	 * HelpScout API client, to tell users HelpScout still lists from former ones.
	 *
	 * @var HelpScout|null
	 */
	private $helpscout;

	/**
	 * FreeScout users found or created so far, by HelpScout user ID.
	 *
	 * @var User[]
	 */
	private $users = array();

	/**
	 * FreeScout teams found so far, by HelpScout team ID; null if there's none.
	 *
	 * @var array
	 */
	private $teams = array();

	/**
	 * Senders without an email found or created so far, by HelpScout customer ID.
	 *
	 * @var Customer[]
	 */
	private $senders = array();

	/**
	 * HelpScout user IDs remember() kept so far.
	 *
	 * @var true[]
	 */
	private $remembered = array();

	/**
	 * Constructor.
	 *
	 * @param HelpScout|null $helpscout HelpScout API client; without one, users created are disabled.
	 */
	public function __construct( ?HelpScout $helpscout = null ) {
		$this->helpscout = $helpscout;
	}

	/**
	 * The FreeScout user for a HelpScout user, created if there's none yet.
	 *
	 * @param mixed $person HelpScout person object, like a thread's `createdBy`.
	 * @return User|null Null if it isn't a HelpScout user, like a sender, a team, or HelpScout itself.
	 */
	public function user( $person ): ?User {
		if ( ! self::is_user( $person ) ) {
			return null;
		}

		$id = (int) $person['id'];
		if ( ! isset( $this->users[ $id ] ) ) {
			$this->users[ $id ] = self::mapped( $id ) ?? $this->match_or_create( $person );
		}

		return $this->users[ $id ];
	}

	/**
	 * The FreeScout user or team a conversation was assigned to.
	 *
	 * @param mixed $person HelpScout person object, like a conversation's `assignee`.
	 * @return User|null Null if it's unassigned, or assigned to a team FreeScout doesn't have.
	 */
	public function assignee( $person ): ?User {
		return is_array( $person ) && 'team' === ( $person['type'] ?? '' ) ? $this->team( $person ) : $this->user( $person );
	}

	/**
	 * The FreeScout team for a HelpScout team: the one an administrator chose, or the only one with the same name.
	 *
	 * @param array $person HelpScout person object of a team.
	 * @return User|null The Teams module's user for the team, or null if there's none.
	 */
	public function team( array $person ): ?User {
		$id = (int) ( $person['id'] ?? 0 );
		if ( ! $id ) {
			return null;
		}

		if ( ! array_key_exists( $id, $this->teams ) ) {
			$team = self::mapped( $id, true );
			if ( ! $team ) {
				// Kept once found by name, so choosing another team later moves its conversations.
				$team = self::team_by_name( self::name( $person ) );
				if ( $team ) {
					Agent::query()->updateOrCreate( array( 'helpscout_user_id' => $id ), array( 'user_id' => $team->id ) );
				}
			}
			$this->teams[ $id ] = $team;
		}

		return $this->teams[ $id ];
	}

	/**
	 * A HelpScout user, as HelpScout lists them, or as an import met them if HelpScout no longer does.
	 *
	 * @param int $helpscout_user_id HelpScout user ID.
	 * @return array|null Shaped like directory()'s, with `former` set for users HelpScout no longer lists.
	 */
	public function helpscout_user( int $helpscout_user_id ): ?array {
		$listed = $this->listed( $helpscout_user_id );
		if ( $listed ) {
			return self::is_team( $listed ) ? null : $listed;
		}

		foreach ( self::former( $this->directory() ) as $former ) {
			if ( $former['id'] === $helpscout_user_id ) {
				return $former;
			}
		}

		return null;
	}

	/**
	 * The FreeScout user a HelpScout user has already: the one chosen or created for them, or with their email.
	 *
	 * @param array $helpscout_user HelpScout user, as directory() lists them.
	 * @return User|null Null if they have none yet.
	 */
	public static function existing( array $helpscout_user ): ?User {
		return self::mapped( (int) ( $helpscout_user['id'] ?? 0 ) ) ?? self::by_email( (string) ( $helpscout_user['email'] ?? '' ) );
	}

	/**
	 * The FreeScout user for a HelpScout user, created if there's none: it can log in while HelpScout lists them.
	 *
	 * @param array $helpscout_user HelpScout user, as directory() lists them.
	 * @return User
	 */
	public function find_or_create( array $helpscout_user ): User {
		$id = (int) $helpscout_user['id'];
		if ( ! isset( $this->users[ $id ] ) ) {
			$this->users[ $id ] = self::mapped( $id ) ?? $this->match_or_create( $helpscout_user, empty( $helpscout_user['former'] ) && null !== $this->listed( $id ) );
		}

		return $this->users[ $id ];
	}

	/**
	 * Makes sure every HelpScout user of a mailbox has a FreeScout user with access to the mailbox imported into.
	 *
	 * @param int     $helpscout_mailbox_id HelpScout mailbox ID.
	 * @param Mailbox $mailbox              FreeScout mailbox.
	 * @return User[] The users created.
	 */
	public function prepare( int $helpscout_mailbox_id, Mailbox $mailbox ): array {
		$created = array();

		foreach ( self::mailbox_users( $this->directory(), $helpscout_mailbox_id ) as $helpscout_user ) {
			$id       = (int) $helpscout_user['id'];
			$existing = self::mapped( $id ) ?? self::by_email( (string) ( $helpscout_user['email'] ?? '' ) );
			$user     = $existing ?? $this->match_or_create( $helpscout_user, true );

			$this->users[ $id ] = $user;
			if ( ! $existing ) {
				$created[] = $user;
			}

			self::grant( $user, $mailbox );
		}

		return $created;
	}

	/**
	 * The HelpScout users of a mailbox that an import would create FreeScout users for.
	 *
	 * @param int $helpscout_mailbox_id HelpScout mailbox ID.
	 * @return array[] HelpScout users, as directory() lists them.
	 */
	public function pending( int $helpscout_mailbox_id ): array {
		return array_values(
			array_filter(
				self::mailbox_users( $this->directory(), $helpscout_mailbox_id ),
				static function ( array $helpscout_user ): bool {
					return ! self::mapped( (int) $helpscout_user['id'] ) && ! self::by_email( (string) ( $helpscout_user['email'] ?? '' ) );
				}
			)
		);
	}

	/**
	 * Keeps who a HelpScout user is, for users HelpScout no longer lists once they're deleted.
	 *
	 * @param mixed $person HelpScout person object, like a thread's `createdBy`.
	 * @return int|null Their HelpScout user ID, or null if it isn't a HelpScout user.
	 */
	public function remember( $person ): ?int {
		if ( ! self::is_user( $person ) ) {
			return null;
		}

		$id = (int) $person['id'];
		if ( ! isset( $this->remembered[ $id ] ) ) {
			Person::query()->updateOrCreate(
				array( 'helpscout_user_id' => $id ),
				array(
					'first_name' => mb_substr( self::first_name( $person ), 0, 100 ),
					'last_name'  => mb_substr( self::last_name( $person ), 0, 100 ),
					'email'      => '' !== (string) ( $person['email'] ?? '' ) ? mb_substr( (string) $person['email'], 0, 191 ) : null,
				)
			);
			$this->remembered[ $id ] = true;
		}

		return $id;
	}

	/**
	 * Credits what was imported for a HelpScout user or team to someone else: replies, notes, and assignments.
	 *
	 * Only what was imported for that HelpScout user or team moves, not what others credited to the same FreeScout user
	 * did; and conversations agents worked on in FreeScout keep their assignee.
	 *
	 * @param int       $helpscout_user_id HelpScout user or team ID.
	 * @param User|null $from              Who it's credited to now; null for a team that had none, whose
	 *                                     conversations were imported unassigned.
	 * @param User      $to                Who it's credited to from now on.
	 * @return void
	 */
	public static function recredit( int $helpscout_user_id, ?User $from, User $to ): void {
		$threads  = static function ( string $column ) use ( $helpscout_user_id ): \Illuminate\Database\Query\Builder {
			return ImportedThread::query()->select( 'thread_id' )->where( $column, $helpscout_user_id )->getQuery();
		};
		$imported = static function ( string $column ) use ( $helpscout_user_id ): \Illuminate\Database\Query\Builder {
			return ImportedConversation::query()->select( 'conversation_id' )->where( $column, $helpscout_user_id )->getQuery();
		};
		$is_from  = static function ( $query, string $column ) use ( $from ) {
			return $from ? $query->where( $column, $from->id ) : $query->whereNull( $column );
		};

		// Without the events of changed threads and conversations: imported ones were written without them too.
		if ( $from ) {
			Thread::query()->whereIn( 'id', $threads( 'helpscout_user_id' ) )->where( 'created_by_user_id', $from->id )->update( array( 'created_by_user_id' => $to->id ) );
			Conversation::query()->whereIn( 'id', $imported( 'creator_id' ) )->where( 'created_by_user_id', $from->id )->update( array( 'created_by_user_id' => $to->id ) );
			Conversation::query()->whereIn( 'id', $imported( 'closer_id' ) )->where( 'closed_by_user_id', $from->id )->update( array( 'closed_by_user_id' => $to->id ) );
		}
		$is_from( Thread::query()->whereIn( 'id', $threads( 'helpscout_assignee_id' ) ), 'user_id' )->update( array( 'user_id' => $to->id ) );

		// Conversations agents worked on in FreeScout keep their assignee.
		$assigned  = $is_from( Conversation::query()->whereIn( 'id', $imported( 'assignee_id' ) ), 'user_id' );
		$worked_on = Thread::query()->select( 'conversation_id' )->whereIn( 'conversation_id', $imported( 'assignee_id' ) )->where( 'imported', false )->getQuery();
		$assigned->whereNotIn( 'id', $worked_on );
		$mailboxes = ( clone $assigned )->distinct()->pluck( 'mailbox_id' );
		$assigned->update( array( 'user_id' => $to->id ) );

		// Who sees them under Mine changed.
		foreach ( Mailbox::query()->whereIn( 'id', $mailboxes )->get() as $mailbox ) {
			if ( User::TYPE_USER === (int) $to->type ) {
				self::grant( $to, $mailbox );
			}
			$mailbox->updateFoldersCounters();
		}
	}

	/**
	 * Credits a HelpScout user or team to a FreeScout user, and what's imported already with them.
	 *
	 * @param int  $helpscout_user_id HelpScout user or team ID.
	 * @param User $user              FreeScout user, or team.
	 * @return void
	 */
	public static function choose( int $helpscout_user_id, User $user ): void {
		$previous = Agent::query()->where( 'helpscout_user_id', $helpscout_user_id )->value( 'user_id' );
		$from     = $previous ? User::find( (int) $previous ) : null;

		// The same user again, like when they're connected to WordPress.org: whether an import created them stays.
		if ( $from && (int) $from->id === (int) $user->id ) {
			return;
		}

		Agent::query()->updateOrCreate(
			array( 'helpscout_user_id' => $helpscout_user_id ),
			array(
				'user_id' => $user->id,
				'created' => false,
			)
		);

		// A team without one imported its conversations unassigned: they're assigned to the team chosen now.
		if ( $from || User::TYPE_ROBOT === (int) $user->type ) {
			self::recredit( $helpscout_user_id, $from, $user );
		}
	}

	/**
	 * Whether an import created a user, rather than finding them in FreeScout.
	 *
	 * @param User $user FreeScout user.
	 * @return bool
	 */
	public static function was_created( User $user ): bool {
		return Agent::query()->where( 'user_id', $user->id )->where( 'created', true )->exists();
	}

	/**
	 * Gives an active user, who isn't an administrator, access to a mailbox, with their own folders in it.
	 *
	 * Administrators see every mailbox already.
	 *
	 * @param User    $user    FreeScout user.
	 * @param Mailbox $mailbox FreeScout mailbox.
	 * @return void
	 */
	public static function grant( User $user, Mailbox $mailbox ): void {
		if ( $user->isAdmin() || User::STATUS_ACTIVE !== (int) $user->status || User::TYPE_USER !== (int) $user->type ) {
			return;
		}

		$user->mailboxes()->syncWithoutDetaching( array( (int) $mailbox->id ) );
		$user->syncPersonalFolders( null );
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
	 * Lists HelpScout's users and teams with the mailboxes each can see; cached, since that takes a request per mailbox.
	 *
	 * @param bool $refresh Whether to ask HelpScout again rather than use the cache.
	 * @return array[] Users and teams, as HelpScout lists them, each with `mailboxes`: names by HelpScout mailbox ID.
	 */
	public function directory( bool $refresh = false ): array {
		if ( ! $this->helpscout ) {
			return array();
		}

		if ( $refresh ) {
			\Cache::forget( self::DIRECTORY_CACHE_KEY );
		}

		$helpscout = $this->helpscout;

		return (array) \Cache::remember(
			self::DIRECTORY_CACHE_KEY,
			self::DIRECTORY_CACHE_MINUTES,
			static function () use ( $helpscout ): array {
				$users = array();
				foreach ( $helpscout->users() as $user ) {
					$users[ (int) $user['id'] ] = $user + array( 'mailboxes' => array() );
				}

				// Teams have their own list too, which HelpScout's users may leave them out of.
				try {
					foreach ( $helpscout->teams() as $team ) {
						$users[ $team['id'] ] = ( $users[ $team['id'] ] ?? $team ) + array( 'mailboxes' => array() );
					}
				} catch ( \Modules\WPOrgHelpScoutImport\Exceptions\ApiError $e ) {
					if ( $e instanceof \Modules\WPOrgHelpScoutImport\Exceptions\RateLimited || 0 === $e->status || $e->status >= 500 ) {
						throw $e;
					}
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
	 * Lists HelpScout users with the FreeScout user each is credited to, or would get.
	 *
	 * @param array[] $helpscout_users HelpScout users, as directory() lists them; teams are left out.
	 * @return array[] By name, each with `id`, `name`, `email`, `mailboxes`, `former`, `user` (their FreeScout user, or
	 *                 null if one will be created), and `how` ('chosen', 'email', or 'new').
	 */
	public static function agents( array $helpscout_users ): array {
		$agents = array();

		foreach ( $helpscout_users as $helpscout_user ) {
			if ( self::is_team( $helpscout_user ) ) {
				continue;
			}

			$id       = (int) ( $helpscout_user['id'] ?? 0 );
			$chosen   = self::mapped( $id );
			$by_email = $chosen ? null : self::by_email( (string) ( $helpscout_user['email'] ?? '' ) );

			$agents[] = array(
				'id'        => $id,
				'name'      => self::name( $helpscout_user ),
				'email'     => (string) ( $helpscout_user['email'] ?? '' ),
				'mailboxes' => (array) ( $helpscout_user['mailboxes'] ?? array() ),
				'former'    => ! empty( $helpscout_user['former'] ),
				'user'      => $chosen ?? $by_email,
				'how'       => $chosen ? 'chosen' : ( $by_email ? 'email' : 'new' ),
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
	 * Lists HelpScout teams with the FreeScout team each is assigned to.
	 *
	 * @param array[] $directory HelpScout's users and teams, from directory().
	 * @return array[] By name, each with `id`, `name`, `team` (the FreeScout team, or null), and `chosen`.
	 */
	public static function teams( array $directory ): array {
		$teams = array();

		foreach ( array_filter( $directory, array( self::class, 'is_team' ) ) as $helpscout_team ) {
			$id     = (int) ( $helpscout_team['id'] ?? 0 );
			$chosen = self::mapped( $id, true );

			$teams[] = array(
				'id'     => $id,
				'name'   => self::name( $helpscout_team ),
				'team'   => $chosen ?? self::team_by_name( self::name( $helpscout_team ) ),
				'chosen' => (bool) $chosen,
			);
		}

		usort(
			$teams,
			static function ( array $a, array $b ): int {
				return strcasecmp( $a['name'], $b['name'] );
			}
		);

		return $teams;
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
	 * FreeScout's teams: the Teams module's users, which are robots, apart from this module's own.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function freescout_teams(): \Illuminate\Database\Eloquent\Builder {
		$query = User::query();

		// Other modules, like Workflows, have robot users too: they're only teams with the Teams module on.
		if ( ! self::teams_module_active() ) {
			return $query->whereRaw( '1 = 0' );
		}

		return $query
			->where( 'type', User::TYPE_ROBOT )
			->where( 'status', '!=', User::STATUS_DELETED )
			->where( 'email', '!=', self::ROBOT_EMAIL );
	}

	/**
	 * Whether FreeScout's Teams module is on.
	 *
	 * @return bool
	 */
	public static function teams_module_active(): bool {
		try {
			return (bool) \App\Module::isActive( self::TEAMS_MODULE );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * The user that what nobody in particular wrote is credited to, created when first needed.
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
	 * A customer without an email, like one who only called or chatted, is a sender without one, found again by their
	 * HelpScout ID.
	 *
	 * @param mixed $person HelpScout person object, like a conversation's `primaryCustomer`.
	 * @return Customer|null Null if it isn't a customer with an email or an ID.
	 */
	public function sender( $person ): ?Customer {
		if ( ! is_array( $person ) ) {
			return null;
		}

		$data = array(
			'first_name' => (string) ( $person['first'] ?? '' ),
			'last_name'  => (string) ( $person['last'] ?? '' ),
		);

		if ( ! empty( $person['email'] ) ) {
			$customer = Customer::create( (string) $person['email'], $data );

			return $customer instanceof Customer ? $customer : null;
		}

		$id = (int) ( $person['id'] ?? 0 );
		if ( ! $id ) {
			return null;
		}

		if ( ! isset( $this->senders[ $id ] ) ) {
			$meta     = '"' . self::SENDER_META . '":' . $id;
			$customer = Customer::query()
				->where(
					static function ( $query ) use ( $meta ): void {
						$query->where( 'meta', 'like', '%' . $meta . ',%' )->orWhere( 'meta', 'like', '%' . $meta . '}%' );
					}
				)
				->first();

			if ( ! $customer ) {
				$customer = Customer::createWithoutEmail( $data );
				$customer->setMeta( self::SENDER_META, $id );
				$customer->save();
			}

			$this->senders[ $id ] = $customer;
		}

		return $this->senders[ $id ];
	}

	/**
	 * A user as HelpScout lists them, if it still does; without a client, it lists nobody.
	 *
	 * @param int $helpscout_user_id HelpScout user ID.
	 * @return array|null
	 */
	private function listed( int $helpscout_user_id ): ?array {
		foreach ( $this->directory() as $helpscout_user ) {
			if ( (int) ( $helpscout_user['id'] ?? 0 ) === $helpscout_user_id ) {
				return $helpscout_user;
			}
		}

		return null;
	}

	/**
	 * The FreeScout user with a HelpScout user's email, or a new one, credited to them from now on.
	 *
	 * @param array     $person HelpScout person object, or user.
	 * @param bool|null $active Whether a new user can log in; null for while HelpScout still lists them.
	 * @return User
	 */
	private function match_or_create( array $person, ?bool $active = null ): User {
		$user    = self::by_email( (string) ( $person['email'] ?? '' ) );
		$created = ! $user;
		if ( $created ) {
			// What HelpScout lists about them, like their timezone, which conversations leave out.
			$listed = $this->listed( (int) $person['id'] );
			$user   = self::create( $person + (array) $listed, $active ?? null !== $listed );
		}

		Agent::query()->updateOrCreate(
			array( 'helpscout_user_id' => (int) $person['id'] ),
			array(
				'user_id' => $user->id,
				'created' => $created,
			)
		);

		return $user;
	}

	/**
	 * Creates a FreeScout user for a HelpScout user.
	 *
	 * They have no password: they log in with WordPress.org once an administrator connects them to their account.
	 *
	 * @param array $person HelpScout person object, or user.
	 * @param bool  $active Whether they can log in, or are disabled.
	 * @return User
	 */
	private static function create( array $person, bool $active ): User {
		$email = Email::sanitizeEmail( (string) ( $person['email'] ?? '' ) );
		if ( ! $email || strlen( $email ) > User::EMAIL_MAX_LENGTH || User::mailboxEmailExists( $email ) || User::query()->where( 'email', $email )->exists() ) {
			$email = 'helpscout-' . (int) $person['id'] . '@' . self::PLACEHOLDER_DOMAIN;
		}

		$first_name = self::first_name( $person );
		$last_name  = self::last_name( $person );
		if ( '' === $first_name && '' === $last_name ) {
			$first_name = strstr( $email, '@', true );
		}

		$user               = new User();
		$user->first_name   = $first_name;
		$user->last_name    = $last_name;
		$user->email        = $email;
		$user->password     = User::getDummyPassword();
		$user->role         = User::ROLE_USER;
		$user->type         = User::TYPE_USER;
		$user->status       = $active ? User::STATUS_ACTIVE : User::STATUS_DISABLED;
		$user->invite_state = User::INVITE_STATE_ACTIVATED;
		$user->timezone     = self::timezone( (string) ( $person['timezone'] ?? '' ) );
		$user->save();

		return $user;
	}

	/**
	 * A HelpScout user's timezone, if PHP knows it, or the app's.
	 *
	 * @param string $timezone IANA timezone.
	 * @return string
	 */
	private static function timezone( string $timezone ): string {
		if ( '' !== $timezone && in_array( $timezone, \DateTimeZone::listIdentifiers(), true ) ) {
			return $timezone;
		}

		return (string) ( config( 'app.timezone' ) ? config( 'app.timezone' ) : User::DEFAULT_TIMEZONE );
	}

	/**
	 * The HelpScout users, not teams, who can see a mailbox.
	 *
	 * @param array[] $directory            HelpScout's users, from directory().
	 * @param int     $helpscout_mailbox_id HelpScout mailbox ID.
	 * @return array[]
	 */
	private static function mailbox_users( array $directory, int $helpscout_mailbox_id ): array {
		return array_values(
			array_filter(
				$directory,
				static function ( array $helpscout_user ) use ( $helpscout_mailbox_id ): bool {
					return ! self::is_team( $helpscout_user ) && isset( $helpscout_user['mailboxes'][ $helpscout_mailbox_id ] ) && (int) ( $helpscout_user['id'] ?? 0 ) > 0;
				}
			)
		);
	}

	/**
	 * Whether a HelpScout person object is one of HelpScout's users: not a sender, a team, or HelpScout itself.
	 *
	 * Its AI agents are users too: the API's version 2 calls them so.
	 *
	 * @param mixed $person HelpScout person object.
	 * @return bool
	 */
	private static function is_user( $person ): bool {
		return is_array( $person ) && in_array( $person['type'] ?? 'user', array( 'user', 'system_user' ), true ) && (int) ( $person['id'] ?? 0 ) > 0;
	}

	/**
	 * The FreeScout user or team an administrator chose, or an import created or matched, for a HelpScout user or team.
	 *
	 * @param int  $helpscout_user_id HelpScout user or team ID.
	 * @param bool $team              Whether it's a team.
	 * @return User|null
	 */
	private static function mapped( int $helpscout_user_id, bool $team = false ): ?User {
		$user_id = $helpscout_user_id ? Agent::query()->where( 'helpscout_user_id', $helpscout_user_id )->value( 'user_id' ) : null;
		if ( ! $user_id ) {
			return null;
		}

		return ( $team ? self::freescout_teams() : User::query()->where( 'status', '!=', User::STATUS_DELETED ) )->whereKey( (int) $user_id )->first();
	}

	/**
	 * The only FreeScout team with a name.
	 *
	 * @param string $name Team name.
	 * @return User|null
	 */
	private static function team_by_name( string $name ): ?User {
		if ( '' === $name ) {
			return null;
		}

		$teams = self::freescout_teams()->get()->filter(
			static function ( User $team ) use ( $name ): bool {
				return 0 === strcasecmp( $team->getFullName(), $name );
			}
		);

		return 1 === $teams->count() ? $teams->first() : null;
	}

	/**
	 * The FreeScout user with an email, who isn't a robot or deleted.
	 *
	 * @param string $email Email.
	 * @return User|null
	 */
	public static function by_email( string $email ): ?User {
		return '' !== $email ? self::creditable()->where( 'email', mb_strtolower( $email ) )->first() : null;
	}

	/**
	 * A HelpScout person's first name: `first` in a person object, `firstName` in a user.
	 *
	 * @param array $person HelpScout person object, or user.
	 * @return string
	 */
	private static function first_name( array $person ): string {
		return trim( (string) ( $person['first'] ?? $person['firstName'] ?? '' ) );
	}

	/**
	 * A HelpScout person's last name.
	 *
	 * @param array $person HelpScout person object, or user.
	 * @return string
	 */
	private static function last_name( array $person ): string {
		return trim( (string) ( $person['last'] ?? $person['lastName'] ?? '' ) );
	}

	/**
	 * A HelpScout person's name.
	 *
	 * @param array $person HelpScout person object, or user.
	 * @return string
	 */
	private static function name( array $person ): string {
		return trim( self::first_name( $person ) . ' ' . self::last_name( $person ) );
	}
}

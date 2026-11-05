<?php
/**
 * Finds or creates the users and senders for the people in sources' data.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Services;

use App\Conversation;
use App\Customer;
use App\Email;
use App\Folder;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgFreeScoutImport\Entities\ImportedConversation;
use Modules\WPOrgFreeScoutImport\Entities\ImportedThread;
use Modules\WPOrgFreeScoutImport\Entities\RemoteUser;

/**
 * Every source's users are credited to users here: the one an administrator chose, the one with their email, or a
 * new, disabled one. Connecting them to a WordPress.org account on the Users page switches them on, or creates them so.
 * A source's teams become the teams here an administrator chose, or with the same name; its other robots, like its
 * workflows, become the import's own robot.
 *
 * An instance is for one source's import job, and keeps what it found; its static methods take the source.
 */
final class People {

	/**
	 * Email of the user that what nobody in particular did is credited to, like a source's workflows.
	 *
	 * Core's own robot users have addresses like this.
	 *
	 * @var string
	 */
	public const ROBOT_EMAIL = 'fs-freescout-import@example.org';

	/**
	 * Alias of the Teams module, whose teams are users.
	 *
	 * @var string
	 */
	public const TEAMS_MODULE = 'teams';

	/**
	 * Teams module's service provider, which lists its teams.
	 *
	 * @var string
	 */
	public const TEAMS_PROVIDER = '\\Modules\\Teams\\Providers\\TeamsServiceProvider';

	/**
	 * Emails of robot users, like the Workflows module's and the Teams module's, even once deleted.
	 *
	 * @var string
	 */
	private const ROBOT_PATTERN = '/^fs[^@]*@example\.org(_deleted.*)?$/i';

	/**
	 * Emails of the Teams module's teams, which it names "<name> Team".
	 *
	 * @var string
	 */
	private const TEAM_PATTERN = '/^fsteam-\d+@example\.org(_deleted.*)?$/i';

	/**
	 * Domain of the emails given to users whose remote email can't be a user's here.
	 *
	 * @var string
	 */
	private const PLACEHOLDER_DOMAIN = 'freescout-import.invalid';

	/**
	 * Cache key for a source's users, before its ID.
	 *
	 * @var string
	 */
	private const DIRECTORY_CACHE_KEY = 'wporgfreescoutimport.directory.';

	/**
	 * How long the source's users are cached, in minutes.
	 *
	 * @var int
	 */
	private const DIRECTORY_CACHE_MINUTES = 60;

	/**
	 * Column the Reports module adds to conversations, which it resets to have their metrics computed again.
	 *
	 * @var string
	 */
	private const REPORTS_COLUMN = 'rpt_ready';

	/**
	 * Whether conversations have the Reports module's column; null until it's checked.
	 *
	 * @var bool|null
	 */
	private static $reports = null;

	/**
	 * Source the import job imports from.
	 *
	 * @var int
	 */
	private $source_id;

	/**
	 * Users found or created so far, by remote user ID.
	 *
	 * @var User[]
	 */
	private $users = array();

	/**
	 * Teams found so far, by remote team ID; null if there's none here.
	 *
	 * @var array<int, User|null>
	 */
	private $teams = array();

	/**
	 * Mailboxes users were given access to so far, by user ID and mailbox ID.
	 *
	 * @var bool[][]
	 */
	private $granted = array();

	/**
	 * Constructor.
	 *
	 * @param int $source_id Source the import job imports from.
	 */
	public function __construct( int $source_id ) {
		$this->source_id = $source_id;
	}

	/**
	 * The user for a remote user, created if there's none yet; the import's robot for the source's robots and teams.
	 *
	 * @param mixed $person Remote user, as the API gives `createdBy` and `assignedTo`.
	 * @return User|null Null if it isn't a user.
	 */
	public function user( mixed $person ): ?User {
		$id = self::user_id( $person );
		if ( ! $id ) {
			return null;
		}

		if ( self::is_robot( $person ) ) {
			return $this->robot();
		}

		if ( ! isset( $this->users[ $id ] ) ) {
			$this->users[ $id ] = self::mapped( $this->source_id, $id ) ?? self::match_or_create( $this->source_id, $person );
		}

		return $this->users[ $id ];
	}

	/**
	 * The user or team a conversation was assigned to.
	 *
	 * @param mixed $person Remote user, as the API gives `assignee` and `assignedTo`.
	 * @return User|null Null if it's unassigned, or assigned to a team there's none of here, or another robot.
	 */
	public function assignee( mixed $person ): ?User {
		if ( self::is_team( $person ) ) {
			return $this->team( $person );
		}

		return self::is_robot( $person ) ? null : $this->user( $person );
	}

	/**
	 * The team here for a source's team: the one an administrator chose, or the only one with the same name.
	 *
	 * @param array $person Remote team, as the API gives `assignee`.
	 * @return User|null The Teams module's user for the team, or null if there's none.
	 */
	public function team( array $person ): ?User {
		$id = (int) ( $person['id'] ?? 0 );
		if ( ! $id ) {
			return null;
		}

		if ( ! array_key_exists( $id, $this->teams ) ) {
			$team = self::mapped( $this->source_id, $id, true );
			if ( ! $team ) {
				/*
				 * Kept once found by name, so choosing another team later moves its conversations. Those imported
				 * unassigned before, while there was none, are assigned to it now.
				 */
				$team = self::team_by_name( self::team_name( $person ) );
				if ( $team ) {
					RemoteUser::query()->updateOrCreate(
						array(
							'source_id'      => $this->source_id,
							'remote_user_id' => $id,
						),
						array(
							'user_id' => $team->id,
							'created' => false,
						)
					);
					self::recredit( $this->source_id, $id, null, $team );
				}
			}
			$this->teams[ $id ] = $team;
		}

		return $this->teams[ $id ];
	}

	/**
	 * Gives a user an import credits something in a mailbox access to it, once per import job.
	 *
	 * Only active users get it: those who'll work here.
	 *
	 * @param User    $user    User.
	 * @param Mailbox $mailbox Mailbox.
	 * @return void
	 */
	public function grant_once( User $user, Mailbox $mailbox ): void {
		if ( empty( $this->granted[ $user->id ][ $mailbox->id ] ) ) {
			self::grant( $user, $mailbox );
			$this->granted[ $user->id ][ $mailbox->id ] = true;
		}
	}

	/**
	 * A remote user's ID, for crediting what's imported for them to someone else later.
	 *
	 * @param mixed $person Remote person.
	 * @return int|null Null if it isn't a user.
	 */
	public static function user_id( mixed $person ): ?int {
		$id = is_array( $person ) && 'user' === ( $person['type'] ?? 'user' ) ? (int) ( $person['id'] ?? 0 ) : 0;

		return $id > 0 ? $id : null;
	}

	/**
	 * The user a remote user has already: the one chosen or created for them, or with their email.
	 *
	 * @param int   $source_id   Source.
	 * @param array $remote_user Remote user.
	 * @return array|null `user`, and `how`: `chosen`, `created`, or `email`; null if there's none yet.
	 */
	public static function existing( int $source_id, array $remote_user ): ?array {
		$record = RemoteUser::query()->where( 'source_id', $source_id )->where( 'remote_user_id', (int) ( $remote_user['id'] ?? 0 ) )->first();
		$user   = $record ? self::mapped( $source_id, (int) $record->remote_user_id ) : null;
		if ( $user ) {
			$how = 'chosen';
			if ( $record->created ) {
				$how = 'created';
			} elseif ( mb_strtolower( (string) $user->email ) === mb_strtolower( (string) ( $remote_user['email'] ?? '' ) ) ) {
				// Imports keep the user they matched by email too.
				$how = 'email';
			}

			return array(
				'user' => $user,
				'how'  => $how,
			);
		}

		$user = self::by_email( (string) ( $remote_user['email'] ?? '' ) );

		return $user ? array(
			'user' => $user,
			'how'  => 'email',
		) : null;
	}

	/**
	 * A remote user, as the source lists them.
	 *
	 * @param RemoteApi $api            The source's API.
	 * @param int       $remote_user_id Remote user ID.
	 * @return array|null
	 */
	public static function remote_user( RemoteApi $api, int $remote_user_id ): ?array {
		foreach ( self::people( $api ) as $remote_user ) {
			if ( (int) ( $remote_user['id'] ?? 0 ) === $remote_user_id ) {
				return $remote_user;
			}
		}

		return null;
	}

	/**
	 * The user for a remote user who'll work here: the one they have already, or a new, active one.
	 *
	 * @param int   $source_id   Source.
	 * @param array $remote_user Remote user.
	 * @return User
	 */
	public static function find_or_create( int $source_id, array $remote_user ): User {
		$existing = self::existing( $source_id, $remote_user );

		return $existing ? $existing['user'] : self::match_or_create( $source_id, $remote_user, true );
	}

	/**
	 * Whether an import created a user, rather than finding them here.
	 *
	 * @param User $user User.
	 * @return bool
	 */
	public static function was_created( User $user ): bool {
		return RemoteUser::query()->where( 'user_id', $user->id )->where( 'created', true )->exists();
	}

	/**
	 * Switches on a user an import created disabled, with access to the mailboxes it credited them with something in.
	 *
	 * @param User $user User.
	 * @return void
	 */
	public static function activate( User $user ): void {
		if ( User::STATUS_DISABLED === (int) $user->status && self::was_created( $user ) ) {
			$user->status = User::STATUS_ACTIVE;
			$user->save();
		}

		$conversations = Thread::query()
			->where( 'created_by_user_id', $user->id )
			->orWhere( 'user_id', $user->id )
			->select( 'conversation_id' )
			->getQuery();
		$imported      = ImportedConversation::query()->select( 'conversation_id' )->getQuery();

		$mailboxes = Conversation::query()->whereIn( 'id', $conversations )->whereIn( 'id', $imported )->distinct()->pluck( 'mailbox_id' );
		foreach ( Mailbox::query()->whereIn( 'id', $mailboxes )->get() as $mailbox ) {
			self::grant( $user, $mailbox );
		}
	}

	/**
	 * Lists a source's users, cached, since every import page reads them.
	 *
	 * @param RemoteApi $api     The source's API.
	 * @param bool      $refresh Whether to read them again.
	 * @return array[] Remote users.
	 */
	public static function directory( RemoteApi $api, bool $refresh = false ): array {
		if ( $refresh ) {
			self::forget_directory( (int) $api->source()->id );
		}

		return (array) \Cache::remember(
			self::DIRECTORY_CACHE_KEY . (int) $api->source()->id,
			self::DIRECTORY_CACHE_MINUTES,
			static function () use ( $api ): array {
				return $api->users();
			}
		);
	}

	/**
	 * Forgets a source's users, so they're read again.
	 *
	 * @param int $source_id Source.
	 * @return void
	 */
	public static function forget_directory( int $source_id ): void {
		\Cache::forget( self::DIRECTORY_CACHE_KEY . $source_id );
	}

	/**
	 * Lists a source's users who are people: not its teams, workflows, or other robots.
	 *
	 * @param RemoteApi $api     The source's API.
	 * @param bool      $refresh Whether to read them again.
	 * @return array[] Remote users.
	 */
	public static function people( RemoteApi $api, bool $refresh = false ): array {
		return array_values(
			array_filter(
				self::directory( $api, $refresh ),
				static function ( array $remote_user ): bool {
					return ! self::is_robot( $remote_user );
				}
			)
		);
	}

	/**
	 * Lists a source's teams with the team here each is assigned to.
	 *
	 * @param RemoteApi $api The source's API.
	 * @return array[] By name, each with `id`, `name`, `team` (the team here, or null), `chosen`, and `missing`
	 *                 (names of the mailboxes its conversations are in that the team here has no access to).
	 */
	public static function teams( RemoteApi $api ): array {
		$source_id = (int) $api->source()->id;
		$teams     = array();

		foreach ( self::directory( $api ) as $remote_team ) {
			if ( ! self::is_team( $remote_team ) ) {
				continue;
			}

			$id     = (int) ( $remote_team['id'] ?? 0 );
			$chosen = self::mapped( $source_id, $id, true );
			$team   = $chosen ?? self::team_by_name( self::team_name( $remote_team ) );

			$teams[] = array(
				'id'      => $id,
				'name'    => self::team_name( $remote_team ),
				'team'    => $team,
				'chosen'  => (bool) $chosen,
				'missing' => $team ? self::missing_mailboxes( $source_id, $id, $team ) : array(),
			);
		}

		usort(
			$teams,
			static function ( array $a, array $b ): int {
				return array( mb_strtolower( $a['name'] ), $a['id'] ) <=> array( mb_strtolower( $b['name'] ), $b['id'] );
			}
		);

		return $teams;
	}

	/**
	 * Whether a remote user is a robot, like the Workflows module's user or a team: core makes up their emails.
	 *
	 * @param mixed $person Remote user, as the API gives them.
	 * @return bool
	 */
	public static function is_robot( mixed $person ): bool {
		return is_array( $person ) && 'user' === ( $person['type'] ?? 'user' ) && 1 === preg_match( self::ROBOT_PATTERN, (string) ( $person['email'] ?? '' ) );
	}

	/**
	 * Whether a remote user is one of the Teams module's teams.
	 *
	 * @param mixed $person Remote user, as the API gives them.
	 * @return bool
	 */
	public static function is_team( mixed $person ): bool {
		return self::is_robot( $person ) && 1 === preg_match( self::TEAM_PATTERN, (string) ( $person['email'] ?? '' ) );
	}

	/**
	 * A team's name, as the Teams module shows it: its first name, followed by "Team" as its last name.
	 *
	 * @param array $person Remote team.
	 * @return string
	 */
	public static function team_name( array $person ): string {
		return trim( (string) ( $person['firstName'] ?? '' ) );
	}

	/**
	 * The teams here: the users the Teams module lists as its teams.
	 *
	 * The module keeps its teams as users that aren't like others, so it's asked which they are. If it can't say, that
	 * isn't taken for none: conversations would be imported unassigned instead of failing, to be imported again.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 * @throws \UnexpectedValueException If the module doesn't list its teams.
	 */
	public static function freescout_teams(): \Illuminate\Database\Eloquent\Builder {
		$query = User::query();

		if ( ! self::teams_module_active() || ! class_exists( self::TEAMS_PROVIDER ) ) {
			return $query->whereRaw( '1 = 0' );
		}

		$teams = call_user_func( array( self::TEAMS_PROVIDER, 'getTeams' ) );
		if ( ! $teams instanceof \Illuminate\Support\Collection ) {
			throw new \UnexpectedValueException( 'The Teams module did not list its teams.' );
		}

		return $query->whereKey( $teams->pluck( 'id' )->map( 'intval' )->all() );
	}

	/**
	 * Whether the Teams module is on.
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
	 * Credits a remote user to a user here, or a remote team to a team here, and what's imported already with them.
	 *
	 * @param int  $source_id      Source.
	 * @param int  $remote_user_id Remote user or team ID.
	 * @param User $user           User or team here.
	 * @return void
	 */
	public static function choose( int $source_id, int $remote_user_id, User $user ): void {
		$previous = RemoteUser::query()->where( 'source_id', $source_id )->where( 'remote_user_id', $remote_user_id )->value( 'user_id' );
		$from     = $previous ? User::find( (int) $previous ) : null;
		$same     = $from && (int) $from->id === (int) $user->id;

		if ( ! $same ) {
			RemoteUser::query()->updateOrCreate(
				array(
					'source_id'      => $source_id,
					'remote_user_id' => $remote_user_id,
				),
				array(
					'user_id' => $user->id,
					'created' => false,
				)
			);
		}

		if ( $from && ! $same ) {
			self::recredit( $source_id, $remote_user_id, $from, $user );
		}

		// Conversations imported unassigned, while the team had no team here, are assigned to the one chosen now.
		if ( User::TYPE_ROBOT === (int) $user->type ) {
			self::recredit( $source_id, $remote_user_id, null, $user );
		}
	}

	/**
	 * Credits what was imported for a remote user to someone else: replies, notes, and assignments.
	 *
	 * Only what was imported for that remote user moves, not what others credited to the same user did; and
	 * conversations agents worked on here keep their assignee. Conversations keep their dates, and with the Reports
	 * module, their metrics are computed again.
	 *
	 * @param int       $source_id      Source.
	 * @param int       $remote_user_id Remote user or team ID.
	 * @param User|null $from           Who it's credited to now; null for a team that had none, whose conversations
	 *                                  were imported unassigned.
	 * @param User      $to             Who it's credited to from now on.
	 * @return void
	 */
	public static function recredit( int $source_id, int $remote_user_id, ?User $from, User $to ): void {
		$threads  = static function ( string $column ) use ( $source_id, $remote_user_id ): \Illuminate\Database\Query\Builder {
			return ImportedThread::query()->select( 'thread_id' )->where( 'source_id', $source_id )->where( $column, $remote_user_id )->getQuery();
		};
		$imported = static function ( string $column ) use ( $source_id, $remote_user_id ): \Illuminate\Database\Query\Builder {
			return ImportedConversation::query()->select( 'conversation_id' )->where( 'source_id', $source_id )->where( $column, $remote_user_id )->getQuery();
		};
		$is_from  = static function ( \Illuminate\Database\Eloquent\Builder $query, string $column ) use ( $from ): \Illuminate\Database\Eloquent\Builder {
			return $from ? $query->where( $column, $from->id ) : $query->whereNull( $column );
		};

		// Without the events of changed threads and conversations, and without touching updated_at, like the import.
		$reports              = self::has_reports();
		$update_conversations = static function ( \Illuminate\Database\Eloquent\Builder $query, array $values ) use ( $reports ): void {
			$query->toBase()->update( $reports ? $values + array( self::REPORTS_COLUMN => false ) : $values );
		};
		$update_threads       = static function ( \Illuminate\Database\Eloquent\Builder $query, array $values ) use ( $reports ): void {
			if ( $reports ) {
				Conversation::query()
					->whereIn( 'id', ( clone $query )->select( 'conversation_id' )->getQuery() )
					->toBase()
					->update( array( self::REPORTS_COLUMN => false ) );
			}
			$query->toBase()->update( $values );
		};

		if ( $from ) {
			$update_threads( Thread::query()->whereIn( 'id', $threads( 'remote_user_id' ) )->where( 'created_by_user_id', $from->id ), array( 'created_by_user_id' => $to->id ) );
			$update_conversations( Conversation::query()->whereIn( 'id', $imported( 'creator_id' ) )->where( 'created_by_user_id', $from->id ), array( 'created_by_user_id' => $to->id ) );
			$update_conversations( Conversation::query()->whereIn( 'id', $imported( 'closer_id' ) )->where( 'closed_by_user_id', $from->id ), array( 'closed_by_user_id' => $to->id ) );
		}
		$update_threads( $is_from( Thread::query()->whereIn( 'id', $threads( 'remote_assignee_id' ) ), 'user_id' ), array( 'user_id' => $to->id ) );

		// Conversations agents worked on here keep their assignee.
		$assigned = $is_from( Conversation::query()->whereIn( 'id', $imported( 'assignee_id' ) ), 'user_id' )
			->whereNotIn( 'id', self::worked_on()->select( 'conversation_id' )->whereIn( 'conversation_id', $imported( 'assignee_id' ) )->getQuery() );

		$mailboxes = Mailbox::query()->whereIn( 'id', ( clone $assigned )->distinct()->pluck( 'mailbox_id' ) )->get();

		// Open ones that were unassigned move to the Assigned folder, as core moves them when they're assigned.
		foreach ( $mailboxes as $mailbox ) {
			$unassigned_folder = $mailbox->folders()->where( 'type', Folder::TYPE_UNASSIGNED )->value( 'id' );
			$assigned_folder   = $mailbox->folders()->where( 'type', Folder::TYPE_ASSIGNED )->value( 'id' );
			if ( $unassigned_folder && $assigned_folder ) {
				$update_conversations(
					( clone $assigned )
						->where( 'mailbox_id', $mailbox->id )
						->where( 'folder_id', $unassigned_folder )
						->whereIn( 'status', array( Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING ) )
						->where( 'state', Conversation::STATE_PUBLISHED ),
					array(
						'user_id'   => $to->id,
						'folder_id' => $assigned_folder,
					)
				);
			}
		}
		$update_conversations( $assigned, array( 'user_id' => $to->id ) );

		// Who sees them under Mine changed, and what's in Unassigned and Assigned.
		foreach ( $mailboxes as $mailbox ) {
			self::grant( $to, $mailbox );
			Importer::update_counters( $mailbox );
		}
	}

	/**
	 * Threads that show agents worked on a conversation here: those not imported, but robots' line items.
	 *
	 * Automatic workflows add a line item by their robot user whenever they run, which nobody did.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function worked_on(): \Illuminate\Database\Eloquent\Builder {
		return Thread::query()
			->where( 'imported', false )
			->where(
				static function ( \Illuminate\Database\Eloquent\Builder $query ): void {
					$query->where( 'type', '!=', Thread::TYPE_LINEITEM )
						->orWhereNull( 'created_by_user_id' )
						->orWhereNotIn( 'created_by_user_id', User::getRobotsCondition()->select( 'id' )->getQuery() );
				}
			);
	}

	/**
	 * Gives an active user, who isn't an administrator, access to a mailbox, with their own folders in it.
	 *
	 * @param User    $user    User.
	 * @param Mailbox $mailbox Mailbox.
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
	 * The users remote users can be credited to: people, not robots, and not deleted.
	 *
	 * @return \Illuminate\Database\Eloquent\Builder
	 */
	public static function creditable(): \Illuminate\Database\Eloquent\Builder {
		return User::query()->where( 'type', User::TYPE_USER )->where( 'status', '!=', User::STATUS_DELETED );
	}

	/**
	 * The user with an email, who isn't a robot or deleted.
	 *
	 * @param string $email Email.
	 * @return User|null
	 */
	public static function by_email( string $email ): ?User {
		return '' !== $email ? self::creditable()->where( 'email', mb_strtolower( $email ) )->first() : null;
	}

	/**
	 * The user that what nobody in particular did is credited to, created when first needed.
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
		$robot->first_name = 'FreeScout';
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
	 * The sender for a source sender, created if there's none with their email yet.
	 *
	 * Created like core's helpers create senders, but without announcing them: modules that react to a new sender,
	 * like the API & Webhooks module's webhooks, would announce every sender of the mailbox's history.
	 *
	 * @param mixed $person Source sender, as the API gives `customer` and `createdBy`.
	 * @return Customer|null Null if it isn't a sender with an email.
	 */
	public function sender( mixed $person ): ?Customer {
		if ( ! is_array( $person ) || 'customer' !== ( $person['type'] ?? 'customer' ) ) {
			return null;
		}

		$email = ! empty( $person['email'] ) ? Email::sanitizeEmail( (string) $person['email'] ) : false;
		if ( ! $email ) {
			return null;
		}

		$email     = Email::sanitizeLength( $email );
		$email_row = Email::query()->where( 'email', $email )->first();
		$sender    = $email_row && $email_row->customer ? $email_row->customer : new Customer();

		$data = array(
			'first_name' => trim( (string) ( $person['firstName'] ?? '' ) ),
			'last_name'  => trim( (string) ( $person['lastName'] ?? '' ) ),
		);
		if ( $sender->setData( $data, false ) || ! $sender->id ) {
			$sender->save();
		}

		if ( ! $email_row ) {
			$email_row        = new Email();
			$email_row->email = $email;
		}

		if ( (int) $email_row->customer_id !== (int) $sender->id ) {
			$email_row->customer()->associate( $sender );
			$email_row->save();
		}

		return $sender;
	}

	/**
	 * The user an administrator chose, or an import created or matched, for a remote user; or the team for a team.
	 *
	 * @param int  $source_id      Source.
	 * @param int  $remote_user_id Remote user or team ID.
	 * @param bool $team           Whether it's a team, which only a team here can be.
	 * @return User|null
	 */
	private static function mapped( int $source_id, int $remote_user_id, bool $team = false ): ?User {
		$user_id = RemoteUser::query()->where( 'source_id', $source_id )->where( 'remote_user_id', $remote_user_id )->value( 'user_id' );
		if ( ! $user_id ) {
			return null;
		}

		return ( $team ? self::freescout_teams() : User::query()->where( 'status', '!=', User::STATUS_DELETED ) )->whereKey( (int) $user_id )->first();
	}

	/**
	 * The only team here with a name.
	 *
	 * @param string $name Team name.
	 * @return User|null
	 */
	private static function team_by_name( string $name ): ?User {
		if ( '' === $name ) {
			return null;
		}

		// By its own name: the module keeps "Team" as every team's last name.
		$teams = self::freescout_teams()->get()->filter(
			static function ( User $team ) use ( $name ): bool {
				return 0 === strcasecmp( (string) $team->first_name, $name );
			}
		);

		return 1 === $teams->count() ? $teams->first() : null;
	}

	/**
	 * The mailboxes a source team's imported conversations are in that its team here has no access to.
	 *
	 * The Teams module gives a team a folder, and lets it be assigned, only in the mailboxes ticked on its page.
	 *
	 * @param int  $source_id      Source.
	 * @param int  $remote_team_id Remote team ID.
	 * @param User $team           Team here.
	 * @return string[] Mailbox names, by ID.
	 */
	private static function missing_mailboxes( int $source_id, int $remote_team_id, User $team ): array {
		$imported = ImportedConversation::query()->select( 'conversation_id' )->where( 'source_id', $source_id )->where( 'assignee_id', $remote_team_id )->getQuery();

		return Mailbox::query()
			->whereIn( 'id', Conversation::query()->select( 'mailbox_id' )->whereIn( 'id', $imported )->getQuery() )
			->whereNotIn( 'id', $team->mailboxes()->pluck( 'mailboxes.id' )->all() )
			->orderBy( 'name' )
			->pluck( 'name', 'id' )
			->all();
	}

	/**
	 * The user with a remote user's email, or a new one, credited to them from now on.
	 *
	 * @param int   $source_id Source.
	 * @param array $person    Remote user.
	 * @param bool  $active    Whether a new user can log in, once connected to WordPress.org, or is disabled.
	 * @return User
	 */
	private static function match_or_create( int $source_id, array $person, bool $active = false ): User {
		$user    = self::by_email( (string) ( $person['email'] ?? '' ) );
		$created = ! $user;
		if ( $created ) {
			$user = self::create( $source_id, $person, $active );
		}

		RemoteUser::query()->updateOrCreate(
			array(
				'source_id'      => $source_id,
				'remote_user_id' => (int) $person['id'],
			),
			array(
				'user_id' => $user->id,
				'created' => $created,
			)
		);

		return $user;
	}

	/**
	 * Creates a user for a remote user, so what they did keeps their name.
	 *
	 * They have no password: they log in with WordPress.org once they're connected to their account.
	 *
	 * @param int   $source_id Source.
	 * @param array $person    Remote user.
	 * @param bool  $active    Whether they can log in, or are disabled.
	 * @return User
	 */
	private static function create( int $source_id, array $person, bool $active ): User {
		$email = Email::sanitizeEmail( (string) ( $person['email'] ?? '' ) );
		if ( ! $email || strlen( $email ) > User::EMAIL_MAX_LENGTH || User::mailboxEmailExists( $email ) || User::query()->where( 'email', $email )->exists() ) {
			$email = 'user-' . $source_id . '-' . (int) $person['id'] . '@' . self::PLACEHOLDER_DOMAIN;
		}

		$first_name = trim( (string) ( $person['firstName'] ?? '' ) );
		$last_name  = trim( (string) ( $person['lastName'] ?? '' ) );
		if ( '' === $first_name && '' === $last_name ) {
			$first_name = (string) strstr( $email, '@', true );
		}

		$user               = new User();
		$user->first_name   = mb_substr( $first_name, 0, 20 );
		$user->last_name    = mb_substr( $last_name, 0, 30 );
		$user->email        = $email;
		$user->password     = User::getDummyPassword();
		$user->role         = User::ROLE_USER;
		$user->type         = User::TYPE_USER;
		$user->status       = $active ? User::STATUS_ACTIVE : User::STATUS_DISABLED;
		$user->invite_state = User::INVITE_STATE_ACTIVATED;
		$user->save();

		return $user;
	}

	/**
	 * Whether conversations have the Reports module's column, checked once.
	 *
	 * @return bool
	 */
	private static function has_reports(): bool {
		if ( null === self::$reports ) {
			self::$reports = Schema::hasColumn( 'conversations', self::REPORTS_COLUMN );
		}

		return self::$reports;
	}
}

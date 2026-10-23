<?php
/**
 * Finds or creates the users and senders for the people in the other FreeScout's data.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Services;

use App\Conversation;
use App\Customer;
use App\Email;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgFreeScoutImport\Entities\ImportedConversation;
use Modules\WPOrgFreeScoutImport\Entities\ImportedThread;
use Modules\WPOrgFreeScoutImport\Entities\SourceUser;

/**
 * Every source user is credited to a user here: the one an administrator chose, the one with their email, or a new,
 * disabled one. Connecting them to a WordPress.org account on the Users page switches them on, or creates them so.
 */
final class People {

	/**
	 * Email of the user that what nobody in particular did is credited to, like the other FreeScout's workflows.
	 *
	 * Core's own robot users have addresses like this.
	 *
	 * @var string
	 */
	public const ROBOT_EMAIL = 'fs-freescout-import@example.org';

	/**
	 * Domain of the emails given to users whose source email can't be a user's here.
	 *
	 * @var string
	 */
	private const PLACEHOLDER_DOMAIN = 'freescout-import.invalid';

	/**
	 * Cache key for the source's users.
	 *
	 * @var string
	 */
	private const DIRECTORY_CACHE_KEY = 'wporgfreescoutimport.directory';

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
	 * Users found or created so far, by source user ID.
	 *
	 * @var User[]
	 */
	private $users = array();

	/**
	 * Mailboxes users were given access to so far, by user ID and mailbox ID.
	 *
	 * @var bool[][]
	 */
	private $granted = array();

	/**
	 * The user for a source user, created if there's none yet.
	 *
	 * @param mixed $person Source user, as the API gives `createdBy` and `assignedTo`.
	 * @return User|null Null if it isn't a user.
	 */
	public function user( mixed $person ): ?User {
		$id = self::user_id( $person );
		if ( ! $id ) {
			return null;
		}

		if ( ! isset( $this->users[ $id ] ) ) {
			$this->users[ $id ] = self::mapped( $id ) ?? self::match_or_create( $person );
		}

		return $this->users[ $id ];
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
	 * A source user's ID, for crediting what's imported for them to someone else later.
	 *
	 * @param mixed $person Source person.
	 * @return int|null Null if it isn't a user.
	 */
	public static function user_id( mixed $person ): ?int {
		$id = is_array( $person ) && 'user' === ( $person['type'] ?? 'user' ) ? (int) ( $person['id'] ?? 0 ) : 0;

		return $id > 0 ? $id : null;
	}

	/**
	 * The user a source user has already: the one chosen or created for them, or with their email.
	 *
	 * @param array $source_user Source user.
	 * @return array|null `user`, and `how`: `chosen`, `created`, or `email`; null if there's none yet.
	 */
	public static function existing( array $source_user ): ?array {
		$record = SourceUser::query()->where( 'source_user_id', (int) ( $source_user['id'] ?? 0 ) )->first();
		$user   = $record ? self::mapped( (int) $record->source_user_id ) : null;
		if ( $user ) {
			$how = 'chosen';
			if ( $record->created ) {
				$how = 'created';
			} elseif ( mb_strtolower( (string) $user->email ) === mb_strtolower( (string) ( $source_user['email'] ?? '' ) ) ) {
				// Imports keep the user they matched by email too.
				$how = 'email';
			}

			return array(
				'user' => $user,
				'how'  => $how,
			);
		}

		$user = self::by_email( (string) ( $source_user['email'] ?? '' ) );

		return $user ? array(
			'user' => $user,
			'how'  => 'email',
		) : null;
	}

	/**
	 * A source user, as the source lists them.
	 *
	 * @param Source $source         The other FreeScout.
	 * @param int    $source_user_id Source user ID.
	 * @return array|null
	 */
	public static function source_user( Source $source, int $source_user_id ): ?array {
		foreach ( self::directory( $source ) as $source_user ) {
			if ( (int) ( $source_user['id'] ?? 0 ) === $source_user_id ) {
				return $source_user;
			}
		}

		return null;
	}

	/**
	 * The user for a source user who'll work here: the one they have already, or a new, active one.
	 *
	 * @param array $source_user Source user.
	 * @return User
	 */
	public static function find_or_create( array $source_user ): User {
		$existing = self::existing( $source_user );

		return $existing ? $existing['user'] : self::match_or_create( $source_user, true );
	}

	/**
	 * Whether an import created a user, rather than finding them here.
	 *
	 * @param User $user User.
	 * @return bool
	 */
	public static function was_created( User $user ): bool {
		return SourceUser::query()->where( 'user_id', $user->id )->where( 'created', true )->exists();
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
	 * Lists the source's users, cached, since every import page reads them.
	 *
	 * @param Source $source  The other FreeScout.
	 * @param bool   $refresh Whether to read them again.
	 * @return array[] Source users.
	 */
	public static function directory( Source $source, bool $refresh = false ): array {
		if ( $refresh ) {
			self::forget_directory();
		}

		return (array) \Cache::remember(
			self::DIRECTORY_CACHE_KEY,
			self::DIRECTORY_CACHE_MINUTES,
			static function () use ( $source ): array {
				return $source->users();
			}
		);
	}

	/**
	 * Forgets the source's users, so they're read again.
	 *
	 * @return void
	 */
	public static function forget_directory(): void {
		\Cache::forget( self::DIRECTORY_CACHE_KEY );
	}

	/**
	 * Credits a source user to a user here, and what's imported already with them.
	 *
	 * @param int  $source_user_id Source user ID.
	 * @param User $user           User here.
	 * @return void
	 */
	public static function choose( int $source_user_id, User $user ): void {
		$previous = SourceUser::query()->where( 'source_user_id', $source_user_id )->value( 'user_id' );
		$from     = $previous ? User::find( (int) $previous ) : null;
		if ( $from && (int) $from->id === (int) $user->id ) {
			return;
		}

		SourceUser::query()->updateOrCreate(
			array( 'source_user_id' => $source_user_id ),
			array(
				'user_id' => $user->id,
				'created' => false,
			)
		);

		if ( $from ) {
			self::recredit( $source_user_id, $from, $user );
		}
	}

	/**
	 * Credits what was imported for a source user to someone else: replies, notes, and assignments.
	 *
	 * Only what was imported for that source user moves, not what others credited to the same user did; and
	 * conversations agents worked on here keep their assignee. Conversations keep their dates, and with the Reports
	 * module, their metrics are computed again.
	 *
	 * @param int  $source_user_id Source user ID.
	 * @param User $from           Who it's credited to now.
	 * @param User $to             Who it's credited to from now on.
	 * @return void
	 */
	public static function recredit( int $source_user_id, User $from, User $to ): void {
		$threads  = static function ( string $column ) use ( $source_user_id ): \Illuminate\Database\Query\Builder {
			return ImportedThread::query()->select( 'thread_id' )->where( $column, $source_user_id )->getQuery();
		};
		$imported = static function ( string $column ) use ( $source_user_id ): \Illuminate\Database\Query\Builder {
			return ImportedConversation::query()->select( 'conversation_id' )->where( $column, $source_user_id )->getQuery();
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

		$update_threads( Thread::query()->whereIn( 'id', $threads( 'source_user_id' ) )->where( 'created_by_user_id', $from->id ), array( 'created_by_user_id' => $to->id ) );
		$update_threads( Thread::query()->whereIn( 'id', $threads( 'source_assignee_id' ) )->where( 'user_id', $from->id ), array( 'user_id' => $to->id ) );
		$update_conversations( Conversation::query()->whereIn( 'id', $imported( 'creator_id' ) )->where( 'created_by_user_id', $from->id ), array( 'created_by_user_id' => $to->id ) );
		$update_conversations( Conversation::query()->whereIn( 'id', $imported( 'closer_id' ) )->where( 'closed_by_user_id', $from->id ), array( 'closed_by_user_id' => $to->id ) );

		// Conversations agents worked on here keep their assignee.
		$assigned = Conversation::query()
			->whereIn( 'id', $imported( 'assignee_id' ) )
			->where( 'user_id', $from->id )
			->whereNotIn( 'id', self::worked_on()->select( 'conversation_id' )->whereIn( 'conversation_id', $imported( 'assignee_id' ) )->getQuery() );

		$mailboxes = Mailbox::query()->whereIn( 'id', ( clone $assigned )->distinct()->pluck( 'mailbox_id' ) )->get();
		$update_conversations( $assigned, array( 'user_id' => $to->id ) );

		// Who sees them under Mine changed.
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
	 * The users source users can be credited to: people, not robots, and not deleted.
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
		$customer  = $email_row && $email_row->customer ? $email_row->customer : new Customer();

		$data = array(
			'first_name' => trim( (string) ( $person['firstName'] ?? '' ) ),
			'last_name'  => trim( (string) ( $person['lastName'] ?? '' ) ),
		);
		if ( $customer->setData( $data, false ) || ! $customer->id ) {
			$customer->save();
		}

		if ( ! $email_row ) {
			$email_row        = new Email();
			$email_row->email = $email;
		}

		if ( (int) $email_row->customer_id !== (int) $customer->id ) {
			$email_row->customer()->associate( $customer );
			$email_row->save();
		}

		return $customer;
	}

	/**
	 * The user an administrator chose, or an import created or matched, for a source user.
	 *
	 * @param int $source_user_id Source user ID.
	 * @return User|null
	 */
	private static function mapped( int $source_user_id ): ?User {
		$user_id = SourceUser::query()->where( 'source_user_id', $source_user_id )->value( 'user_id' );

		return $user_id ? User::query()->where( 'status', '!=', User::STATUS_DELETED )->whereKey( (int) $user_id )->first() : null;
	}

	/**
	 * The user with a source user's email, or a new one, credited to them from now on.
	 *
	 * @param array $person Source user.
	 * @param bool  $active Whether a new user can log in, once connected to WordPress.org, or is disabled.
	 * @return User
	 */
	private static function match_or_create( array $person, bool $active = false ): User {
		$user    = self::by_email( (string) ( $person['email'] ?? '' ) );
		$created = ! $user;
		if ( $created ) {
			$user = self::create( $person, $active );
		}

		SourceUser::query()->updateOrCreate(
			array( 'source_user_id' => (int) $person['id'] ),
			array(
				'user_id' => $user->id,
				'created' => $created,
			)
		);

		return $user;
	}

	/**
	 * Creates a user for a source user, so what they did keeps their name.
	 *
	 * They have no password: they log in with WordPress.org once they're connected to their account.
	 *
	 * @param array $person Source user.
	 * @param bool  $active Whether they can log in, or are disabled.
	 * @return User
	 */
	private static function create( array $person, bool $active ): User {
		$email = Email::sanitizeEmail( (string) ( $person['email'] ?? '' ) );
		if ( ! $email || strlen( $email ) > User::EMAIL_MAX_LENGTH || User::mailboxEmailExists( $email ) || User::query()->where( 'email', $email )->exists() ) {
			$email = 'user-' . (int) $person['id'] . '@' . self::PLACEHOLDER_DOMAIN;
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

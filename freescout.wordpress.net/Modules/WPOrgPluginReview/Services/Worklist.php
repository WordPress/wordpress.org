<?php
/**
 * The conversations a member of the plugins team is working on: My Reviews and My Cases.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

use App\Conversation;
use App\Folder;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;

/**
 * A folder of the plugins team's mailbox for one of its teams: the team's open and pending conversations that a
 * member replied to, that they're mentioned in, and that need someone, and how many active ones each member replied
 * to, for the folder list.
 *
 * My Reviews is the Plugin Reviews team's, and My Cases the Plugin Admin team's, which can also be filtered by tag.
 * Each conversation is only in the first section it belongs to. One query tells, for every conversation, which
 * sections it belongs to, through the threads' index on conversation and type.
 */
final class Worklist {

	/**
	 * What the team writes in a note when a conversation needs someone to take it.
	 *
	 * @var string
	 */
	public const NEEDS_MARKER = 'PRTRN';

	/**
	 * The sections, in the order a conversation is looked for in them.
	 *
	 * @var string[]
	 */
	public const SECTIONS = array( 'participating', 'mentioned', 'needs' );

	/**
	 * How long the members' counts are kept at most, in minutes; changes to conversations forget them before that.
	 *
	 * @var int
	 */
	private const COUNTS_MINUTES = 10;

	/**
	 * The worklists, by key.
	 *
	 * @var self[]|null
	 */
	private static $all = null;

	/**
	 * Constructor.
	 *
	 * @param string $key         Key: reviews or cases.
	 * @param string $slug        The page's part of its URL.
	 * @param string $team        The name of the team whose conversations it lists.
	 * @param int    $folder_type The folder's type. The folder is added to the mailbox's list as it's shown, and never
	 *                            stored, so the type is only for telling it apart; no other module is known to use it.
	 *                            Its ID is the type's negative, which no stored folder has, so the folder list
	 *                            highlights it on its own page only; review.css finds it in the list by it.
	 * @param string $icon        The folder's Glyphicon.
	 * @param bool   $tag_filter  Whether its conversations can be filtered by tag.
	 */
	private function __construct(
		public readonly string $key,
		public readonly string $slug,
		public readonly string $team,
		public readonly int $folder_type,
		public readonly string $icon,
		public readonly bool $tag_filter
	) {}

	/**
	 * The worklists, in the order the folder list shows them.
	 *
	 * @return self[] By key.
	 */
	public static function all(): array {
		if ( null === self::$all ) {
			self::$all = array(
				'reviews' => new self( 'reviews', 'my-reviews', Reviewers::REVIEW_TEAM, 229, 'tasks', false ),
				'cases'   => new self( 'cases', 'my-cases', Reviewers::ADMIN_TEAM, 230, 'briefcase', true ),
			);
		}

		return self::$all;
	}

	/**
	 * My Reviews: the Plugin Reviews team's.
	 *
	 * @return self
	 */
	public static function reviews(): self {
		return self::all()['reviews'];
	}

	/**
	 * My Cases: the Plugin Admin team's.
	 *
	 * @return self
	 */
	public static function cases(): self {
		return self::all()['cases'];
	}

	/**
	 * The worklist of a page.
	 *
	 * @param string $slug The page's part of its URL.
	 * @return self|null
	 */
	public static function by_slug( string $slug ): ?self {
		foreach ( self::all() as $worklist ) {
			if ( $worklist->slug === $slug ) {
				return $worklist;
			}
		}

		return null;
	}

	/**
	 * The pages' parts of their URLs, for the route.
	 *
	 * @return string[]
	 */
	public static function slugs(): array {
		return array_values(
			array_map(
				static function ( self $worklist ): string {
					return $worklist->slug;
				},
				self::all()
			)
		);
	}

	/**
	 * The worklist of a folder.
	 *
	 * @param mixed $folder Folder.
	 * @return self|null Null if it isn't one of theirs.
	 */
	public static function of_folder( mixed $folder ): ?self {
		if ( $folder instanceof Folder ) {
			foreach ( self::all() as $worklist ) {
				if ( $worklist->folder_type === (int) $folder->type ) {
					return $worklist;
				}
			}
		}

		return null;
	}

	/**
	 * The folder's name.
	 *
	 * @return string
	 */
	public function name(): string {
		return 'cases' === $this->key ? __( 'My Cases' ) : __( 'My Reviews' );
	}

	/**
	 * What a member is called, for choosing whose conversations to show.
	 *
	 * @return string
	 */
	public function member_label(): string {
		return 'cases' === $this->key ? __( 'Volunteer' ) : __( 'Reviewer' );
	}

	/**
	 * The sections' headings.
	 *
	 * @param string|null $name The member's name; null for the agent's own.
	 * @return string[] By section.
	 */
	public function headings( ?string $name ): array {
		if ( 'cases' === $this->key ) {
			return array(
				'participating' => null === $name ? __( 'Cases I’m participating in' ) : __( 'Cases :name is participating in', array( 'name' => $name ) ),
				'mentioned'     => null === $name ? __( 'Cases I’m mentioned in' ) : __( 'Cases :name is mentioned in', array( 'name' => $name ) ),
				'needs'         => __( 'Cases that need a volunteer' ),
			);
		}

		return array(
			'participating' => null === $name ? __( 'Reviews I’m participating in' ) : __( 'Reviews :name is participating in', array( 'name' => $name ) ),
			'mentioned'     => null === $name ? __( 'Reviews I’m mentioned in' ) : __( 'Reviews :name is mentioned in', array( 'name' => $name ) ),
			'needs'         => __( 'Reviews that need a reviewer' ),
		);
	}

	/**
	 * What's in the sections.
	 *
	 * @param string $name The member's name.
	 * @return string[] By section.
	 */
	public function descriptions( string $name ): array {
		return array(
			'participating' => __( 'With a reply from :name.', array( 'name' => $name ) ),
			'mentioned'     => __( 'With a note that mentions :name.', array( 'name' => $name ) ),
			'needs'         => 'cases' === $this->key
				? __( 'With :marker in a note, or without a reply from a volunteer yet.', array( 'marker' => self::NEEDS_MARKER ) )
				: __( 'With :marker in a note, or without a reply from a reviewer yet.', array( 'marker' => self::NEEDS_MARKER ) ),
		);
	}

	/**
	 * What an empty section says.
	 *
	 * @return string
	 */
	public function empty_text(): string {
		return 'cases' === $this->key ? __( 'No cases.' ) : __( 'No reviews.' );
	}

	/**
	 * The page of the folder.
	 *
	 * @param int $mailbox_id Mailbox ID.
	 * @return string
	 */
	public function url( int $mailbox_id ): string {
		return route(
			'wporgpluginreview.worklist',
			array(
				'mailbox_id' => $mailbox_id,
				'worklist'   => $this->slug,
			)
		);
	}

	/**
	 * The folder's ID.
	 *
	 * @return int
	 */
	public function folder_id(): int {
		return -$this->folder_type;
	}

	/**
	 * Whether a folder is this worklist's.
	 *
	 * @param mixed $folder Folder.
	 * @return bool
	 */
	public function is_folder( mixed $folder ): bool {
		return $folder instanceof Folder && $this->folder_type === (int) $folder->type;
	}

	/**
	 * The folder, as the mailbox's folder list shows it.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @return Folder
	 */
	public function folder( Mailbox $mailbox ): Folder {
		$folder               = new Folder();
		$folder->id           = $this->folder_id();
		$folder->mailbox_id   = (int) $mailbox->id;
		$folder->type         = $this->folder_type;
		$folder->active_count = 0;
		$folder->total_count  = 0;

		return $folder;
	}

	/**
	 * Adds the worklists' folders to a mailbox's folder list, after Unassigned and before Mine, with how many active
	 * conversations the agent is participating in.
	 *
	 * @param Collection $folders The mailbox's folders.
	 * @param Mailbox    $mailbox Mailbox.
	 * @param User       $user    The agent.
	 * @return Collection
	 */
	public static function add_folders( Collection $folders, Mailbox $mailbox, User $user ): Collection {
		$added = array();
		foreach ( self::all() as $worklist ) {
			$folder = $worklist->folder( $mailbox );
			try {
				$folder->active_count = $worklist->active_count( $mailbox, $user );
			} catch ( \Throwable $e ) {
				\Log::error( '[WPOrgPluginReview] Could not count the ' . $worklist->key . ' of user ' . $user->id . ': ' . $e->getMessage() );
			}
			$added[] = $folder;
		}

		$list   = array();
		$placed = false;
		foreach ( $folders as $item ) {
			if ( ! $placed && Folder::TYPE_MINE === (int) $item->type ) {
				array_push( $list, ...$added );
				$placed = true;
			}

			$list[] = $item;

			if ( ! $placed && Folder::TYPE_UNASSIGNED === (int) $item->type ) {
				array_push( $list, ...$added );
				$placed = true;
			}
		}

		if ( ! $placed ) {
			array_unshift( $list, ...$added );
		}

		return $folders->make( $list );
	}

	/**
	 * How many active conversations an agent is participating in, as the folder list shows it: the ones whose sender
	 * wrote last, like core's folders count.
	 *
	 * Every member's count comes from one query, kept until something changes them; agents who may only see what's
	 * assigned to them get theirs counted on its own.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @param User    $user    The agent.
	 * @return int
	 */
	public function active_count( Mailbox $mailbox, User $user ): int {
		if ( $user->canSeeOnlyAssignedConversations() ) {
			return $this->conversations( $mailbox, $user )
				->where( 'conversations.status', Conversation::STATUS_ACTIVE )
				->addWhereExistsQuery( self::threads( Thread::TYPE_MESSAGE )->where( 'threads.created_by_user_id', $user->id ) )
				->count();
		}

		$counts = \Cache::remember(
			$this->counts_key( (int) $mailbox->id ),
			now()->addMinutes( self::COUNTS_MINUTES ),
			function () use ( $mailbox ): array {
				return $this->conversations( $mailbox )
					->where( 'conversations.status', Conversation::STATUS_ACTIVE )
					->join( 'threads', 'threads.conversation_id', '=', 'conversations.id' )
					->where( 'threads.type', Thread::TYPE_MESSAGE )
					->where( 'threads.state', Thread::STATE_PUBLISHED )
					->whereNotNull( 'threads.created_by_user_id' )
					->groupBy( 'threads.created_by_user_id' )
					->toBase()
					->selectRaw( 'threads.created_by_user_id as user_id, count(distinct conversations.id) as total' )
					->pluck( 'total', 'user_id' )
					->map(
						static function ( mixed $total ): int {
							return (int) $total;
						}
					)
					->all();
			}
		);

		return (int) ( $counts[ (int) $user->id ] ?? 0 );
	}

	/**
	 * Forgets the worklists' counts of a mailbox, after something that may change them.
	 *
	 * @param int $mailbox_id Mailbox ID.
	 * @return void
	 */
	public static function forget_counts( int $mailbox_id ): void {
		try {
			foreach ( self::all() as $worklist ) {
				\Cache::forget( $worklist->counts_key( $mailbox_id ) );
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not forget the worklists\' counts of mailbox ' . $mailbox_id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Where the members' counts of a mailbox are kept.
	 *
	 * @param int $mailbox_id Mailbox ID.
	 * @return string
	 */
	private function counts_key( int $mailbox_id ): string {
		return 'wporgpluginreview.worklist.' . $this->key . '.active_counts.' . $mailbox_id;
	}

	/**
	 * The members whose conversations can be shown: the mailbox's users who are members of the plugins team's teams.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @return Collection Users, by name.
	 */
	public static function reviewers( Mailbox $mailbox ): Collection {
		$reviewers = app( Reviewers::class );

		// The Teams module adds its teams to the list, as users who aren't active.
		return collect( $mailbox->usersHavingAccess( true ) )
			->filter(
				static function ( mixed $user ) use ( $reviewers ): bool {
					return $user instanceof User && $user->isActive() && $reviewers->includes( $user );
				}
			)
			->values();
	}

	/**
	 * A member's conversations, by section.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @param User    $user    The member.
	 * @param User    $viewer  Who's looking: they only see the conversations they may.
	 * @return array<string, Conversation[]> Conversations by section, the longest waiting first.
	 */
	public function sections( Mailbox $mailbox, User $user, User $viewer ): array {
		return self::load( $this->section_ids( $mailbox, $user, $viewer ) );
	}

	/**
	 * The IDs of a member's conversations, by section.
	 *
	 * A conversation needs someone when a note says so, or when no member replied to it yet: none of the mailbox's
	 * users who are members of the plugins team's teams, as they are now.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @param User    $user    The member.
	 * @param User    $viewer  Who's looking: they only see the conversations they may.
	 * @return array<string, int[]> Conversation IDs by section, the longest waiting first.
	 */
	public function section_ids( Mailbox $mailbox, User $user, User $viewer ): array {
		$member_ids = self::reviewers( $mailbox )->pluck( 'id' )->all();

		$rows = $this->conversations( $mailbox, $viewer )
			->select( 'conversations.id' )
			->selectSub( self::threads( Thread::TYPE_MESSAGE )->where( 'threads.created_by_user_id', $user->id ), 'participating' )
			->selectSub( self::threads( Thread::TYPE_NOTE )->where( 'threads.body', 'like', '%' . self::mention( $user ) . '%' ), 'mentioned' )
			->selectSub( self::threads( Thread::TYPE_NOTE )->where( 'threads.body', 'like', '%' . self::NEEDS_MARKER . '%' ), 'needs' )
			->selectSub( self::threads( Thread::TYPE_MESSAGE )->whereIn( 'threads.created_by_user_id', $member_ids ), 'answered' )
			->orderBy( 'conversations.last_reply_at' )
			->orderBy( 'conversations.id' )
			->toBase()
			->get();

		$ids = array_fill_keys( self::SECTIONS, array() );
		foreach ( $rows as $row ) {
			$row->needs = $row->needs || ! $row->answered;

			foreach ( self::SECTIONS as $section ) {
				if ( $row->$section ) {
					$ids[ $section ][] = (int) $row->id;
					break;
				}
			}
		}

		return $ids;
	}

	/**
	 * The tags of conversations, to filter them by, with the Tags module.
	 *
	 * @param array<string, int[]> $ids      Conversation IDs by section.
	 * @param int                  $selected The tag filtered by, if any, which is listed even when none of the
	 *                                       conversations have it anymore.
	 * @return array<int, array{name: string, count: int}> By tag ID, by name.
	 */
	public static function tags( array $ids, int $selected = 0 ): array {
		$all = array_merge( ...array_values( $ids ) );
		if ( ! self::has_tags() ) {
			return array();
		}

		try {
			$tags = array();
			if ( $all ) {
				$rows = \DB::table( 'conversation_tag' )
					->join( 'tags', 'tags.id', '=', 'conversation_tag.tag_id' )
					->whereIn( 'conversation_tag.conversation_id', $all )
					->groupBy( 'tags.id', 'tags.name' )
					->select( 'tags.id', 'tags.name' )
					->selectRaw( 'count(*) as total' )
					->get();

				foreach ( $rows as $row ) {
					$tags[ (int) $row->id ] = array(
						'name'  => (string) $row->name,
						'count' => (int) $row->total,
					);
				}
			}

			if ( $selected && ! isset( $tags[ $selected ] ) ) {
				$name = \DB::table( 'tags' )->where( 'id', $selected )->value( 'name' );
				if ( null !== $name ) {
					$tags[ $selected ] = array(
						'name'  => (string) $name,
						'count' => 0,
					);
				}
			}

			uasort(
				$tags,
				static function ( array $a, array $b ): int {
					return strnatcasecmp( $a['name'], $b['name'] );
				}
			);

			return $tags;
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not read the tags to filter by: ' . $e->getMessage() );

			return array();
		}
	}

	/**
	 * Keeps the conversations with a tag.
	 *
	 * @param array<string, int[]> $ids    Conversation IDs by section.
	 * @param int                  $tag_id Tag ID.
	 * @return array<string, int[]>
	 */
	public static function filter_by_tag( array $ids, int $tag_id ): array {
		$all    = array_merge( ...array_values( $ids ) );
		$tagged = $all
			? array_flip(
				\DB::table( 'conversation_tag' )
					->where( 'tag_id', $tag_id )
					->whereIn( 'conversation_id', $all )
					->pluck( 'conversation_id' )
					->map(
						static function ( mixed $id ): int {
							return (int) $id;
						}
					)
					->all()
			)
			: array();

		return array_map(
			static function ( array $section ) use ( $tagged ): array {
				return array_values(
					array_filter(
						$section,
						static function ( int $id ) use ( $tagged ): bool {
							return isset( $tagged[ $id ] );
						}
					)
				);
			},
			$ids
		);
	}

	/**
	 * Loads conversations by section, with what conversation lists show of them.
	 *
	 * @param array<string, int[]> $ids Conversation IDs by section.
	 * @return array<string, Conversation[]>
	 */
	public static function load( array $ids ): array {
		$all = array_merge( ...array_values( $ids ) );
		if ( ! $all ) {
			return $ids;
		}

		$conversations = self::preload( Conversation::whereIn( 'id', $all )->with( 'customer' )->get() )->keyBy( 'id' );

		return array_map(
			static function ( array $section ) use ( $conversations ): array {
				return array_values( array_filter( array_map( array( $conversations, 'get' ), $section ) ) );
			},
			$ids
		);
	}

	/**
	 * Whether there are tags to filter by: the Tags module is on, and its tables are there.
	 *
	 * @return bool
	 */
	public static function has_tags(): bool {
		try {
			return \App\Module::isActive( 'tags' ) && \Schema::hasTable( 'tags' ) && \Schema::hasTable( 'conversation_tag' );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Lets modules load what they show in conversation lists for all the conversations at once, like the Tags module
	 * their tags, as core's lists do.
	 *
	 * @param Collection $conversations Conversations.
	 * @return Collection
	 */
	private static function preload( Collection $conversations ): Collection {
		try {
			$preloaded = \Eventy::filter( 'conversations_table.preload_table_data', $conversations );

			return $preloaded instanceof Collection ? $preloaded : $conversations;
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not preload the worklist\'s conversations: ' . $e->getMessage() );

			return $conversations;
		}
	}

	/**
	 * The team's open and pending conversations in the mailbox, or, without the Teams module, the mailbox's.
	 *
	 * @param Mailbox   $mailbox Mailbox.
	 * @param User|null $viewer  Who's looking, if they only see the conversations they may.
	 * @return Builder
	 */
	private function conversations( Mailbox $mailbox, ?User $viewer = null ): Builder {
		$query = Conversation::query()
			->where( 'conversations.mailbox_id', $mailbox->id )
			->where( 'conversations.state', Conversation::STATE_PUBLISHED )
			->whereIn( 'conversations.status', array( Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING ) );

		$team_id = app( Reviewers::class )->team_id( $this->team );
		if ( null !== $team_id ) {
			$query->where( 'conversations.user_id', $team_id );
		}

		// Like core's folders, for users who may only see what's assigned to them; Teams adds their teams' conversations.
		if ( $viewer && $viewer->canSeeOnlyAssignedConversations() && ! \Eventy::filter( 'folder.only_assigned_condition', false, $query, (int) $viewer->id ) ) {
			$query->where( 'conversations.user_id', $viewer->id );
		}

		return $query;
	}

	/**
	 * Whether a conversation has a published thread of a type, to narrow down.
	 *
	 * @param int $type Thread type.
	 * @return QueryBuilder
	 */
	private static function threads( int $type ): QueryBuilder {
		return \DB::table( 'threads' )
			->selectRaw( '1' )
			->whereColumn( 'threads.conversation_id', 'conversations.id' )
			->where( 'threads.type', $type )
			->where( 'threads.state', Thread::STATE_PUBLISHED )
			->limit( 1 );
	}

	/**
	 * How the Mentions module marks a user up in a note.
	 *
	 * @param User $user User.
	 * @return string
	 */
	private static function mention( User $user ): string {
		return 'data-mentioned-id="' . (int) $user->id . '"';
	}
}

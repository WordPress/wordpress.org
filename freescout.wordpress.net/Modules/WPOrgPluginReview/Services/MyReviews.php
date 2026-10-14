<?php
/**
 * The reviews a reviewer is working on.
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
 * Sorts the open and pending conversations of the Plugin Reviews team into the ones a reviewer replied to, the ones
 * they're mentioned in, and the ones that need a reviewer, for the mailbox's My Reviews folder, and counts the ones
 * each reviewer replied to for the folder list.
 *
 * Each conversation is only in the first section it belongs to. One query tells, for every conversation, which
 * sections it belongs to, through the threads' index on conversation and type.
 */
final class MyReviews {

	/**
	 * The folder's type. The folder is added to the mailbox's list as it's shown, and never stored, so the type is
	 * only for telling it apart; no other module is known to use it.
	 *
	 * @var int
	 */
	public const FOLDER_TYPE = 229;

	/**
	 * The folder's ID, which no stored folder has, so the folder list highlights it on its own page only; review.css
	 * finds it in the list by it.
	 *
	 * @var int
	 */
	public const FOLDER_ID = -229;

	/**
	 * What the team writes in a note when a conversation needs a reviewer.
	 *
	 * @var string
	 */
	public const NEEDS_REVIEWER = 'PRTRN';

	/**
	 * The sections, in the order a conversation is looked for in them.
	 *
	 * @var string[]
	 */
	public const SECTIONS = array( 'participating', 'mentioned', 'needs_reviewer' );

	/**
	 * How long the reviewers' counts are kept at most, in minutes; changes to conversations forget them before that.
	 *
	 * @var int
	 */
	private const COUNTS_MINUTES = 10;

	/**
	 * The folder, as the mailbox's folder list shows it.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @return Folder
	 */
	public static function folder( Mailbox $mailbox ): Folder {
		$folder               = new Folder();
		$folder->id           = self::FOLDER_ID;
		$folder->mailbox_id   = (int) $mailbox->id;
		$folder->type         = self::FOLDER_TYPE;
		$folder->active_count = 0;
		$folder->total_count  = 0;

		return $folder;
	}

	/**
	 * Adds the folder to a mailbox's folder list, after Unassigned and before Mine, with how many reviews the agent is
	 * participating in.
	 *
	 * @param Collection $folders The mailbox's folders.
	 * @param Mailbox    $mailbox Mailbox.
	 * @param User       $user    The agent.
	 * @return Collection
	 */
	public static function add_folder( Collection $folders, Mailbox $mailbox, User $user ): Collection {
		$folder = self::folder( $mailbox );
		try {
			$folder->active_count = self::participating_count( $mailbox, $user );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not count the My Reviews of user ' . $user->id . ': ' . $e->getMessage() );
		}

		$list  = array();
		$added = false;
		foreach ( $folders as $item ) {
			if ( ! $added && Folder::TYPE_MINE === (int) $item->type ) {
				$list[] = $folder;
				$added  = true;
			}

			$list[] = $item;

			if ( ! $added && Folder::TYPE_UNASSIGNED === (int) $item->type ) {
				$list[] = $folder;
				$added  = true;
			}
		}

		if ( ! $added ) {
			array_unshift( $list, $folder );
		}

		return $folders->make( $list );
	}

	/**
	 * How many reviews an agent is participating in, as the folder list shows it.
	 *
	 * Every reviewer's count comes from one query, kept until something changes them; agents who may only see what's
	 * assigned to them get theirs counted on its own.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @param User    $user    The agent.
	 * @return int
	 */
	public static function participating_count( Mailbox $mailbox, User $user ): int {
		if ( $user->canSeeOnlyAssignedConversations() ) {
			return self::conversations( $mailbox, $user )
				->addWhereExistsQuery( self::threads( Thread::TYPE_MESSAGE )->where( 'threads.created_by_user_id', $user->id ) )
				->count();
		}

		$counts = \Cache::remember(
			self::counts_key( (int) $mailbox->id ),
			now()->addMinutes( self::COUNTS_MINUTES ),
			static function () use ( $mailbox ): array {
				return self::conversations( $mailbox )
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
	 * Forgets the reviewers' counts of a mailbox, after something that may change them.
	 *
	 * @param int $mailbox_id Mailbox ID.
	 * @return void
	 */
	public static function forget_counts( int $mailbox_id ): void {
		try {
			\Cache::forget( self::counts_key( $mailbox_id ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not forget the My Reviews counts of mailbox ' . $mailbox_id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Where the reviewers' counts of a mailbox are kept.
	 *
	 * @param int $mailbox_id Mailbox ID.
	 * @return string
	 */
	private static function counts_key( int $mailbox_id ): string {
		return 'wporgpluginreview.my_reviews.counts.' . $mailbox_id;
	}

	/**
	 * Whether a folder is the My Reviews folder.
	 *
	 * @param mixed $folder Folder.
	 * @return bool
	 */
	public static function is_folder( mixed $folder ): bool {
		return $folder instanceof Folder && self::FOLDER_TYPE === (int) $folder->type;
	}

	/**
	 * The reviewers whose reviews can be shown: the mailbox's users who are members of the plugins team's teams.
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
	 * A reviewer's conversations, by section.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @param User    $user    The reviewer.
	 * @param User    $viewer  Who's looking: they only see the conversations they may.
	 * @return array<string, Conversation[]> Conversations by section, the longest waiting first.
	 */
	public static function sections( Mailbox $mailbox, User $user, User $viewer ): array {
		$rows = self::conversations( $mailbox, $viewer )
			->select( 'conversations.id' )
			->selectSub( self::threads( Thread::TYPE_MESSAGE )->where( 'threads.created_by_user_id', $user->id ), 'participating' )
			->selectSub( self::threads( Thread::TYPE_NOTE )->where( 'threads.body', 'like', '%' . self::mention( $user ) . '%' ), 'mentioned' )
			->selectSub( self::threads( Thread::TYPE_NOTE )->where( 'threads.body', 'like', '%' . self::NEEDS_REVIEWER . '%' ), 'needs_reviewer' )
			->orderBy( 'conversations.last_reply_at' )
			->orderBy( 'conversations.id' )
			->toBase()
			->get();

		$ids = array_fill_keys( self::SECTIONS, array() );
		foreach ( $rows as $row ) {
			foreach ( self::SECTIONS as $section ) {
				if ( $row->$section ) {
					$ids[ $section ][] = (int) $row->id;
					break;
				}
			}
		}

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
			\Log::error( '[WPOrgPluginReview] Could not preload the My Reviews conversations: ' . $e->getMessage() );

			return $conversations;
		}
	}

	/**
	 * The Plugin Reviews team's open and pending conversations in the mailbox, or, without the Teams module, the
	 * mailbox's.
	 *
	 * @param Mailbox   $mailbox Mailbox.
	 * @param User|null $viewer  Who's looking, if they only see the conversations they may.
	 * @return Builder
	 */
	private static function conversations( Mailbox $mailbox, ?User $viewer = null ): Builder {
		$query = Conversation::query()
			->where( 'conversations.mailbox_id', $mailbox->id )
			->where( 'conversations.state', Conversation::STATE_PUBLISHED )
			->whereIn( 'conversations.status', array( Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING ) );

		$team_id = app( Reviewers::class )->review_team_id();
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

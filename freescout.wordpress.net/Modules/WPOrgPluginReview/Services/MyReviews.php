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
 * they're mentioned in, and the ones that need a reviewer, for the mailbox's My Reviews folder.
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
	 * The folder's ID, which no stored folder has, so the folder list highlights it on its own page only.
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
	 * Adds the folder to a mailbox's folder list, after the Plugin Reviews team's folder.
	 *
	 * @param Collection $folders The mailbox's folders.
	 * @param Mailbox    $mailbox Mailbox.
	 * @return Collection
	 */
	public static function add_folder( Collection $folders, Mailbox $mailbox ): Collection {
		$team_id = app( Reviewers::class )->review_team_id();
		$folder  = self::folder( $mailbox );
		$list    = array();
		$added   = false;

		foreach ( $folders as $item ) {
			$list[] = $item;

			if ( ! $added && $team_id && Reviewers::TEAM_FOLDER_TYPE === (int) $item->type && $team_id === (int) $item->user_id ) {
				$list[] = $folder;
				$added  = true;
			}
		}

		if ( ! $added ) {
			$list[] = $folder;
		}

		return $folders->make( $list );
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
	 * @return array<string, Conversation[]> Conversations by section, open ones first, then by their latest reply.
	 */
	public static function sections( Mailbox $mailbox, User $user, User $viewer ): array {
		$rows = self::conversations( $mailbox, $viewer )
			->select( 'conversations.id' )
			->selectSub( self::threads( Thread::TYPE_MESSAGE )->where( 'threads.created_by_user_id', $user->id ), 'participating' )
			->selectSub( self::threads( Thread::TYPE_NOTE )->where( 'threads.body', 'like', '%' . self::mention( $user ) . '%' ), 'mentioned' )
			->selectSub( self::threads( Thread::TYPE_NOTE )->where( 'threads.body', 'like', '%' . self::NEEDS_REVIEWER . '%' ), 'needs_reviewer' )
			->orderBy( 'conversations.status' )
			->orderBy( 'conversations.last_reply_at', 'desc' )
			->orderBy( 'conversations.id', 'desc' )
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

		$conversations = Conversation::whereIn( 'id', $all )->with( 'customer' )->get()->keyBy( 'id' );

		return array_map(
			static function ( array $section ) use ( $conversations ): array {
				return array_values( array_filter( array_map( array( $conversations, 'get' ), $section ) ) );
			},
			$ids
		);
	}

	/**
	 * The Plugin Reviews team's open and pending conversations in the mailbox, or, without the Teams module, the
	 * mailbox's.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @param User    $viewer  Who's looking.
	 * @return Builder
	 */
	private static function conversations( Mailbox $mailbox, User $viewer ): Builder {
		$query = Conversation::query()
			->where( 'conversations.mailbox_id', $mailbox->id )
			->where( 'conversations.state', Conversation::STATE_PUBLISHED )
			->whereIn( 'conversations.status', array( Conversation::STATUS_ACTIVE, Conversation::STATUS_PENDING ) );

		$team_id = app( Reviewers::class )->review_team_id();
		if ( null !== $team_id ) {
			$query->where( 'conversations.user_id', $team_id );
		}

		// Like core's folders, for users who may only see what's assigned to them; Teams adds their teams' conversations.
		if ( $viewer->canSeeOnlyAssignedConversations() && ! \Eventy::filter( 'folder.only_assigned_condition', false, $query, (int) $viewer->id ) ) {
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

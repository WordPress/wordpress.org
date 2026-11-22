<?php
/**
 * Which active conversations the worklists' Open buttons open, and in what order.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

use App\Conversation;
use App\Thread;
use App\User;
use Carbon\Carbon;

/**
 * Picks the active conversations a member is participating in to open in new tabs, up to how many they open at a
 * time: the longest waiting first, or by progress, which makes conversations wait longer the more the team replied to
 * them, the ones that need the most attention.
 *
 * A conversation's age is how long its sender has been waiting: since its latest reply, as Waiting Since shows.
 */
final class OpenOrder {

	/**
	 * How many conversations are opened at a time, unless the agent chooses otherwise in their profile.
	 *
	 * @var int
	 */
	public const DEFAULT_LIMIT = 25;

	/**
	 * The most conversations an agent can open at a time.
	 *
	 * @var int
	 */
	public const MAX_LIMIT = 100;

	/**
	 * Conversations whose sender has waited less than this, in hours, are never opened.
	 *
	 * @var int
	 */
	public const MIN_AGE_HOURS = 12;

	/**
	 * How many days a conversation waits at least before it's opened by progress, by how many replies the team sent
	 * to it: the most replies each wait is for. Replies are the team's emails, including the first if the team wrote
	 * it, and not notes.
	 *
	 * @var array<int, int>
	 */
	public const WAIT_DAYS = array(
		0           => 0,
		2           => 1,
		4           => 2,
		6           => 4,
		8           => 5,
		10          => 6,
		PHP_INT_MAX => 8,
	);

	/**
	 * The days conversations with these tags wait on top of that.
	 *
	 * @var array<string, int>
	 */
	public const TAG_WAIT_DAYS = array(
		'sockpuppet'    => 4,
		'sockpuppeting' => 4,
	);

	/**
	 * Seconds in an hour.
	 *
	 * @var int
	 */
	private const HOUR = 3600;

	/**
	 * Seconds in a day.
	 *
	 * @var int
	 */
	private const DAY = 86400;

	/**
	 * Where the agents' choices of how many to open are kept, followed by their user ID.
	 *
	 * @var string
	 */
	private const LIMIT_OPTION = 'wporgpluginreview.open_limit.';

	/**
	 * The active conversations to open, the longest waiting first.
	 *
	 * @param Conversation[] $conversations Conversations.
	 * @param int            $limit         How many to open at most.
	 * @return int[] Conversation IDs, in the order to open them.
	 */
	public static function oldest_first( array $conversations, int $limit ): array {
		$waits = self::waits( $conversations );
		arsort( $waits );

		return array_slice( array_keys( $waits ), 0, max( 0, $limit ) );
	}

	/**
	 * The active conversations to open by progress: the longest waiting, after each waits as many days as WAIT_DAYS and
	 * TAG_WAIT_DAYS make it. A conversation that waited that long is ahead of one that waited longer but has to wait
	 * more.
	 *
	 * @param Conversation[] $conversations Conversations.
	 * @param int            $limit         How many to open at most.
	 * @return int[] Conversation IDs, in the order to open them.
	 */
	public static function progress_based( array $conversations, int $limit ): array {
		$waits   = self::waits( $conversations );
		$replies = self::team_replies( array_keys( $waits ) );
		$tags    = self::tags( array_keys( $waits ) );

		// How long each waited beyond what it has to; the ones that didn't wait enough yet are left out.
		$ahead = array();
		foreach ( $waits as $id => $seconds ) {
			$beyond = $seconds - self::wait_days( $replies[ $id ] ?? 0, $tags[ $id ] ?? array() ) * self::DAY;
			if ( $beyond >= 0 ) {
				$ahead[ $id ] = $beyond;
			}
		}
		arsort( $ahead );

		return array_slice( array_keys( $ahead ), 0, max( 0, $limit ) );
	}

	/**
	 * How many days a conversation waits at least before it's opened by progress.
	 *
	 * @param int      $replies How many replies the team sent to it.
	 * @param string[] $tags    Its tags.
	 * @return int
	 */
	public static function wait_days( int $replies, array $tags ): int {
		$days = 0;
		foreach ( self::WAIT_DAYS as $most => $wait ) {
			if ( $replies <= $most ) {
				$days = $wait;
				break;
			}
		}

		foreach ( $tags as $tag ) {
			$days += self::TAG_WAIT_DAYS[ strtolower( $tag ) ] ?? 0;
		}

		return $days;
	}

	/**
	 * How many conversations an agent opens at a time.
	 *
	 * @param User $user Agent.
	 * @return int
	 */
	public static function limit( User $user ): int {
		try {
			// Not from Option's cache, which setting and removing options leave as it was.
			$limit = (int) \Option::get( self::LIMIT_OPTION . $user->id, self::DEFAULT_LIMIT, true, false );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not read how many conversations user ' . $user->id . ' opens: ' . $e->getMessage() );

			return self::DEFAULT_LIMIT;
		}

		return $limit >= 1 ? min( $limit, self::MAX_LIMIT ) : self::DEFAULT_LIMIT;
	}

	/**
	 * Sets how many conversations an agent opens at a time, between 1 and MAX_LIMIT; the default when it's empty.
	 *
	 * @param User  $user  Agent.
	 * @param mixed $limit How many, as sent.
	 * @return void
	 */
	public static function set_limit( User $user, mixed $limit ): void {
		if ( ! is_numeric( $limit ) || self::DEFAULT_LIMIT === (int) $limit ) {
			\Option::remove( self::LIMIT_OPTION . $user->id );

			return;
		}

		\Option::set( self::LIMIT_OPTION . $user->id, max( 1, min( (int) $limit, self::MAX_LIMIT ) ) );
	}

	/**
	 * Forgets an agent's choice, when they're deleted.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public static function forget_limit( int $user_id ): void {
		\Option::remove( self::LIMIT_OPTION . $user_id );
	}

	/**
	 * How long the senders of the active conversations that are old enough to open have waited.
	 *
	 * @param Conversation[] $conversations Conversations.
	 * @return array<int, int> Seconds, by conversation ID.
	 */
	private static function waits( array $conversations ): array {
		$now   = Carbon::now();
		$waits = array();
		foreach ( $conversations as $conversation ) {
			if ( Conversation::STATUS_ACTIVE !== (int) $conversation->status ) {
				continue;
			}

			$since = $conversation->last_reply_at ?? $conversation->created_at;
			if ( ! $since ) {
				continue;
			}

			$since   = $since instanceof \DateTimeInterface ? $since->getTimestamp() : Carbon::parse( (string) $since )->getTimestamp();
			$seconds = $now->getTimestamp() - $since;
			if ( $seconds >= self::MIN_AGE_HOURS * self::HOUR ) {
				$waits[ (int) $conversation->id ] = $seconds;
			}
		}

		// Ties go to the conversation that came in first.
		ksort( $waits );

		return $waits;
	}

	/**
	 * How many replies the team sent to conversations: their published emails written by agents, the first one
	 * included, and not notes or the plugin directory's upload confirmations.
	 *
	 * @param int[] $ids Conversation IDs.
	 * @return array<int, int> By conversation ID.
	 */
	private static function team_replies( array $ids ): array {
		if ( ! $ids ) {
			return array();
		}

		return \DB::table( 'threads' )
			->whereIn( 'conversation_id', $ids )
			->where( 'type', Thread::TYPE_MESSAGE )
			->where( 'state', Thread::STATE_PUBLISHED )
			->where( 'body', 'not like', '%' . Review::UPLOAD_CONFIRMATION . '%' )
			->groupBy( 'conversation_id' )
			->selectRaw( 'conversation_id, count(*) as total' )
			->pluck( 'total', 'conversation_id' )
			->map(
				static function ( mixed $total ): int {
					return (int) $total;
				}
			)
			->all();
	}

	/**
	 * The tags of conversations that make them wait longer, with the Tags module.
	 *
	 * @param int[] $ids Conversation IDs.
	 * @return array<int, string[]> Tag names, by conversation ID.
	 */
	private static function tags( array $ids ): array {
		if ( ! $ids || ! Worklist::has_tags() ) {
			return array();
		}

		try {
			$tags = array();
			$rows = \DB::table( 'conversation_tag' )
				->join( 'tags', 'tags.id', '=', 'conversation_tag.tag_id' )
				->whereIn( 'conversation_tag.conversation_id', $ids )
				->whereIn( 'tags.name', array_keys( self::TAG_WAIT_DAYS ) )
				->select( 'conversation_tag.conversation_id', 'tags.name' )
				->get();

			foreach ( $rows as $row ) {
				$tags[ (int) $row->conversation_id ][] = (string) $row->name;
			}

			return $tags;
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not read the tags of the conversations to open: ' . $e->getMessage() );

			return array();
		}
	}
}

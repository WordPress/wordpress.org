<?php
/**
 * A conversation's plugin reviews.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

use App\Conversation;
use App\Mailbox;
use App\Thread;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Finds the review emails of a conversation, keeps the index of which replies are review emails and of what type, and
 * works out what the sidebar shows about the latest one.
 *
 * A review email is a reply from the team with a Review ID line. The emails that follow up on a review, like the one
 * saying the changes weren't made, are indexed too, as they matter for whether an update is owed, but the sidebar shows
 * the review they follow up on. Only the plugins team's mailbox has reviews; replies elsewhere aren't read.
 */
final class Review {

	/**
	 * Table of review emails, by thread.
	 *
	 * @var string
	 */
	public const TABLE = 'wporgpluginreview_reviews';

	/**
	 * Flag for a review the author hasn't uploaded an update for since the team's last reply.
	 *
	 * @var string
	 */
	public const FLAG_UPDATE = 'UPD';

	/**
	 * How the plugins team's mailbox address starts, as api.wordpress.org's plugin-review.php tells it.
	 *
	 * @var string
	 */
	private const MAILBOX_PREFIX = 'plugins';

	/**
	 * A thread's fields that make it a review email, or not.
	 *
	 * @var string[]
	 */
	public const INDEXED_FIELDS = array( 'body', 'state', 'type', 'conversation_id' );

	/**
	 * What WordPress.org's upload form writes into the review conversation when the author uploads an update.
	 *
	 * @var string
	 */
	public const UPLOAD_CONFIRMATION = 'This is an automated message to confirm that we have received your updated plugin file.';

	/**
	 * Type of the email that approves the plugin; once there is one, no update is waited for.
	 *
	 * @var string
	 */
	private const TYPE_APPROVED = 'APPROVED';

	/**
	 * Types of review that ask the author for an update.
	 *
	 * @var string[]
	 */
	private const UPDATE_TYPES = array( 'OWN', 'TRM', 'F1', 'R', 'AUTOPREREVIEW' );

	/**
	 * Second-level labels that come before a country's own, like co.uk.
	 *
	 * @var string[]
	 */
	private const SECOND_LEVELS = array( 'com', 'co', 'net', 'org', 'gov', 'edu' );

	/**
	 * What a reply's body has to contain to be worth reading for a Review ID line, in SQL's LIKE.
	 *
	 * @var string[]
	 */
	private const REVIEW_ID_LIKE = array( '%Review%ID:%', '%Review:%' );

	/**
	 * The conversation's latest review, as the sidebar shows it.
	 *
	 * Read from the index, which threads' hooks, moves, imports, and the module's command keep. A conversation is read in
	 * full, and indexed again, when its latest rows' threads were changed without their hooks; replies newer than the
	 * latest row that may hold a Review ID, but aren't indexed, are read on their own.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return array|null Null if the conversation has no review email.
	 */
	public static function latest( Conversation $conversation ): ?array {
		if ( ! self::is_plugins_mailbox( $conversation->mailbox ) ) {
			return null;
		}

		$rows    = self::rows( (int) $conversation->id );
		$threads = self::threads( $conversation, $rows );

		if ( $rows->isNotEmpty() && ( ! $threads['reference'] || ( ! $threads['review'] && self::review_row( $rows ) ) ) ) {
			self::reindex( $conversation );
			$rows    = self::rows( (int) $conversation->id );
			$threads = self::threads( $conversation, $rows );
		} elseif ( self::index_newer( $conversation, $rows ) ) {
			$rows    = self::rows( (int) $conversation->id );
			$threads = self::threads( $conversation, $rows );
		}

		$thread = $threads['review'];
		$email  = $thread ? new ReviewEmail( (string) $thread->body ) : null;
		$review = $email ? $email->review_id() : null;
		if ( ! $review || ! $threads['reference'] ) {
			return null;
		}

		$flags = $review['flags'];
		if ( self::waits_for_update( $rows->pluck( 'type' )->all(), $threads['reference'] ) ) {
			array_unshift( $flags, self::FLAG_UPDATE );
		}

		return array(
			'thread_id' => (int) $thread->id,
			'review_id' => $review,
			'flags'     => array_values( array_unique( $flags ) ),
			'issues'    => $email->issues(),
			'names'     => in_array( 'TRM', $flags, true ) ? self::names( $conversation, $email ) : null,
			'owner'     => in_array( 'OWN', $flags, true ) ? self::owner( $email, $review ) : null,
		);
	}

	/**
	 * Makes the index match a conversation's review emails, reading all of its replies.
	 *
	 * For conversations whose threads changed without their hooks: imported, moved to another mailbox, or changed
	 * directly in the database. Never throws: it runs while conversations are moved, or imported.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return void
	 */
	public static function reindex( Conversation $conversation ): void {
		try {
			$reviews = array();
			if ( ! self::is_plugins_mailbox( $conversation->mailbox ) ) {
				self::sync( (int) $conversation->id, $reviews );

				return;
			}

			$threads = $conversation->threads()
				->where( 'type', Thread::TYPE_MESSAGE )
				->where( 'state', Thread::STATE_PUBLISHED )
				->orderBy( 'created_at' )
				->orderBy( 'id' )
				->get();

			foreach ( $threads as $thread ) {
				$review_id = ( new ReviewEmail( (string) $thread->body ) )->review_id();
				if ( $review_id ) {
					$reviews[] = compact( 'thread', 'review_id' );
				}
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not read conversation ' . $conversation->id . ': ' . $e->getMessage() );

			return;
		}

		self::sync( (int) $conversation->id, $reviews );
	}

	/**
	 * Takes conversations out of the index, as they're deleted for good, which core does without their threads' hooks.
	 *
	 * Never throws: it runs while core deletes them.
	 *
	 * @param int[] $conversation_ids Conversation IDs.
	 * @return void
	 */
	public static function forget( array $conversation_ids ): void {
		try {
			$conversation_ids = array_values( array_filter( array_map( 'intval', $conversation_ids ) ) );
			if ( $conversation_ids ) {
				\DB::table( self::TABLE )->whereIn( 'conversation_id', $conversation_ids )->delete();
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not forget the reviews of conversations ' . implode( ', ', $conversation_ids ) . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Whether a mailbox is the plugins team's.
	 *
	 * @param Mailbox|null $mailbox Mailbox.
	 * @return bool
	 */
	public static function is_plugins_mailbox( ?Mailbox $mailbox ): bool {
		return $mailbox && str_starts_with( strtolower( (string) $mailbox->email ), self::MAILBOX_PREFIX );
	}

	/**
	 * IDs of the plugins team's mailboxes.
	 *
	 * @return int[]
	 */
	public static function plugins_mailbox_ids(): array {
		return Mailbox::all()
			->filter(
				static function ( Mailbox $mailbox ): bool {
					return self::is_plugins_mailbox( $mailbox );
				}
			)
			->pluck( 'id' )
			->map( 'intval' )
			->values()
			->all();
	}

	/**
	 * A conversation's rows in the index, oldest review first.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return Collection
	 */
	private static function rows( int $conversation_id ): Collection {
		return \DB::table( self::TABLE )
			->where( 'conversation_id', $conversation_id )
			->orderBy( 'reviewed_at' )
			->orderBy( 'thread_id' )
			->get();
	}

	/**
	 * The latest row of a review, rather than of an email following up on one.
	 *
	 * @param Collection $rows A conversation's rows in the index.
	 * @return object|null
	 */
	private static function review_row( Collection $rows ): ?object {
		return $rows->reject(
			static function ( object $row ): bool {
				return ReviewId::is_follow_up( (string) $row->type );
			}
		)->last();
	}

	/**
	 * The threads of the latest indexed review emails, if they're still published replies in the conversation.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param Collection   $rows         Its rows in the index.
	 * @return array The latest review, whose content the sidebar shows, and the latest email of any type, which the
	 *               author's update is counted from; null for those that aren't current.
	 */
	private static function threads( Conversation $conversation, Collection $rows ): array {
		$ids = array_filter(
			array(
				'review'    => (int) ( self::review_row( $rows )->thread_id ?? 0 ),
				'reference' => (int) ( $rows->last()->thread_id ?? 0 ),
			)
		);

		$found = $ids ? Thread::query()->whereIn( 'id', array_unique( $ids ) )->get()->keyBy( 'id' ) : collect();

		return array_map(
			static function ( int $id ) use ( $conversation, $found ): ?Thread {
				$thread  = $found->get( $id );
				$current = $thread
					&& (int) $thread->conversation_id === (int) $conversation->id
					&& Thread::TYPE_MESSAGE === (int) $thread->type
					&& Thread::STATE_PUBLISHED === (int) $thread->state;

				return $current ? $thread : null;
			},
			array_merge(
				array(
					'review'    => 0,
					'reference' => 0,
				),
				$ids
			)
		);
	}

	/**
	 * Indexes the replies newer than a conversation's latest row that may hold a Review ID, but aren't in the index, like
	 * ones written without their hooks.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param Collection   $rows         Its rows in the index.
	 * @return bool Whether one was a review email.
	 */
	private static function index_newer( Conversation $conversation, Collection $rows ): bool {
		$query = $conversation->threads()
			->where( 'type', Thread::TYPE_MESSAGE )
			->where( 'state', Thread::STATE_PUBLISHED )
			->whereNotIn( 'id', $rows->pluck( 'thread_id' )->map( 'intval' )->push( 0 )->all() )
			->where(
				static function ( Builder $any ): void {
					foreach ( self::REVIEW_ID_LIKE as $like ) {
						$any->orWhere( 'body', 'like', $like );
					}
				}
			);

		$latest = $rows->last();
		if ( $latest && $latest->reviewed_at ) {
			$query->where( 'created_at', '>=', $latest->reviewed_at );
		}

		$indexed = false;
		foreach ( $query->get() as $thread ) {
			$review_id = ( new ReviewEmail( (string) $thread->body ) )->review_id();
			if ( $review_id ) {
				self::write( $thread, $review_id );
				$indexed = true;
			}
		}

		return $indexed;
	}

	/**
	 * Brings the index up to date with one thread, as it's written, changed, or deleted.
	 *
	 * Never throws: it runs while core saves the thread.
	 *
	 * @param Thread $thread  Thread.
	 * @param bool   $deleted Whether it's being deleted.
	 * @return void
	 */
	public static function index_thread( Thread $thread, bool $deleted = false ): void {
		try {
			if ( $deleted ) {
				self::unindex( $thread );

				return;
			}

			// Only the team's replies are review emails; core updates other threads for things like being opened.
			if ( Thread::TYPE_MESSAGE !== (int) $thread->type ) {
				if ( $thread->isDirty( 'type' ) ) {
					self::unindex( $thread );
				}

				return;
			}

			// Replies are saved as drafts as they're written; only one that was published can have been indexed.
			if ( Thread::STATE_PUBLISHED !== (int) $thread->state ) {
				if ( Thread::STATE_PUBLISHED === (int) $thread->getOriginal( 'state' ) ) {
					self::unindex( $thread );
				}

				return;
			}

			// A reply only leaves the plugins team's mailbox indexed by moving to another conversation, as merging does.
			if ( ! self::is_plugins_mailbox( $thread->conversation->mailbox ?? null ) ) {
				if ( $thread->isDirty( 'conversation_id' ) ) {
					self::unindex( $thread );
				}

				return;
			}

			$review_id = ( new ReviewEmail( (string) $thread->body ) )->review_id();
			if ( $review_id ) {
				self::write( $thread, $review_id );
			} else {
				self::unindex( $thread );
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not index thread ' . $thread->id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Indexes a review email.
	 *
	 * @param Thread $thread    Thread of the email.
	 * @param array  $review_id Its Review ID, see ReviewId::parse().
	 * @return void
	 */
	private static function write( Thread $thread, array $review_id ): void {
		\DB::table( self::TABLE )->updateOrInsert( array( 'thread_id' => $thread->id ), self::row( $thread, $review_id ) );
	}

	/**
	 * Takes a thread out of the index.
	 *
	 * @param Thread $thread Thread.
	 * @return void
	 */
	private static function unindex( Thread $thread ): void {
		\DB::table( self::TABLE )->where( 'thread_id', $thread->id )->delete();
	}

	/**
	 * A review email's row in the index, without its thread ID.
	 *
	 * @param Thread $thread    Thread of the email.
	 * @param array  $review_id Its Review ID, see ReviewId::parse().
	 * @return array
	 */
	private static function row( Thread $thread, array $review_id ): array {
		return array(
			'conversation_id' => (int) $thread->conversation_id,
			'type'            => mb_substr( $review_id['type'], 0, 50 ),
			'reviewed_at'     => $thread->created_at ? (string) $thread->created_at : null,
		);
	}

	/**
	 * Makes the index match a conversation's review emails, which merging and moving threads can change.
	 *
	 * Never throws: it runs while conversations are moved, or imported.
	 *
	 * @param int     $conversation_id Conversation ID.
	 * @param array[] $reviews         Its review emails: thread, and Review ID.
	 * @return void
	 */
	private static function sync( int $conversation_id, array $reviews ): void {
		try {
			// Rows of the conversation, and of its threads, which may have been merged into it.
			$thread_ids = array_map(
				static function ( array $review ): int {
					return (int) $review['thread']->id;
				},
				$reviews
			);
			$indexed    = \DB::table( self::TABLE )
				->where( 'conversation_id', $conversation_id )
				->orWhereIn( 'thread_id', $thread_ids ? $thread_ids : array( 0 ) )
				->get()
				->keyBy( 'thread_id' );

			// Only rows that changed are written.
			foreach ( $reviews as $review ) {
				$row      = self::row( $review['thread'], $review['review_id'] );
				$existing = $indexed->get( (int) $review['thread']->id );
				if ( ! $existing || self::differs( $row, (array) $existing ) ) {
					self::write( $review['thread'], $review['review_id'] );
				}
			}

			$stale = $indexed->keys()->map( 'intval' )->diff( $thread_ids )->values()->all();
			if ( $stale ) {
				\DB::table( self::TABLE )->whereIn( 'thread_id', $stale )->where( 'conversation_id', $conversation_id )->delete();
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not index conversation ' . $conversation_id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Whether an index row differs from what it should be.
	 *
	 * @param array $row      What it should be.
	 * @param array $existing What it is.
	 * @return bool
	 */
	private static function differs( array $row, array $existing ): bool {
		foreach ( $row as $column => $value ) {
			$indexed = (string) ( $existing[ $column ] ?? '' );
			if ( $indexed !== (string) $value ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the author still owes an update.
	 *
	 * They do when a review asked for one, none approved the plugin, and since the latest review email, WordPress.org
	 * hasn't confirmed an upload, or the team wrote to the author again after the latest confirmation. The team reviews an
	 * update before it answers, so an answer that isn't a review email of its own is asking for more, like the replies the
	 * panel offers for the flags: whatever the team wrote last tells whether the author still has something to upload.
	 *
	 * The plugin directory confirms an upload with an agent's reply to the author, in HelpScout as in FreeScout; an email
	 * from the author doesn't count, as anyone can write the text. Replies from the agent that posted the confirmation
	 * don't count as the team's: that's WordPress.org's own account, whose other replies are automated too.
	 *
	 * @param string[] $types     Types of the conversation's review emails.
	 * @param Thread   $reference The latest review email, of any type.
	 * @return bool
	 */
	private static function waits_for_update( array $types, Thread $reference ): bool {
		if ( in_array( self::TYPE_APPROVED, $types, true ) || ! array_intersect( self::UPDATE_TYPES, $types ) ) {
			return false;
		}

		$confirmation = self::replies_after( $reference )
			->where( 'body', 'like', '%' . self::UPLOAD_CONFIRMATION . '%' )
			->orderBy( 'created_at', 'desc' )
			->orderBy( 'id', 'desc' )
			->first();
		if ( ! $confirmation ) {
			return true;
		}

		$author = (int) $confirmation->created_by_user_id;

		return self::replies_after( $confirmation )
			->where( 'body', 'not like', '%' . self::UPLOAD_CONFIRMATION . '%' )
			->where(
				static function ( Builder $query ) use ( $author ): void {
					if ( $author ) {
						$query->whereNull( 'created_by_user_id' )->orWhere( 'created_by_user_id', '!=', $author );
					} else {
						$query->whereNotNull( 'created_by_user_id' );
					}
				}
			)
			->exists();
	}

	/**
	 * The conversation's published replies written after a thread.
	 *
	 * @param Thread $thread Thread.
	 * @return Builder
	 */
	private static function replies_after( Thread $thread ): Builder {
		return Thread::query()
			->where( 'conversation_id', $thread->conversation_id )
			->where( 'type', Thread::TYPE_MESSAGE )
			->where( 'state', Thread::STATE_PUBLISHED )
			->where(
				static function ( Builder $query ) use ( $thread ): void {
					$query->where( 'created_at', '>', $thread->created_at )
						->orWhere(
							static function ( Builder $same ) use ( $thread ): void {
								$same->where( 'created_at', $thread->created_at )->where( 'id', '>', $thread->id );
							}
						);
				}
			);
	}

	/**
	 * The plugin's name as submitted, and the name and slug the review suggests instead.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param ReviewEmail  $email        The latest review email.
	 * @return array Original, suggested, and suggested slug, empty if unknown; the slug only while the review is in progress.
	 */
	private static function names( Conversation $conversation, ReviewEmail $email ): array {
		$subject     = trim( (string) $conversation->subject );
		$in_progress = (bool) preg_match( '/review\s+in\s+progress/i', $subject );

		if ( preg_match( '/Review in Progress:\s*(.+)$/i', $subject, $matches ) ) {
			$original = trim( $matches[1] );
		} else {
			$colon    = mb_strrpos( $subject, ':' );
			$original = false === $colon ? $subject : trim( mb_substr( $subject, $colon + 1 ) );
		}

		return array(
			'original'       => $original,
			'suggested'      => $email->suggested( 'name' ),
			'suggested_slug' => $in_progress ? $email->suggested( 'slug' ) : '',
		);
	}

	/**
	 * What the review says about who owns the plugin: the domains its header declares, and whose account submitted it.
	 *
	 * @param ReviewEmail $email     The latest review email.
	 * @param array       $review_id Its Review ID.
	 * @return array Author host, plugin host, and username; empty if unknown.
	 */
	private static function owner( ReviewEmail $email, array $review_id ): array {
		return array(
			'author_host' => self::domain( $email->declared_url( 'Author URI' ) ),
			'plugin_host' => self::domain( $email->declared_url( 'Plugin URI' ) ),
			'username'    => $review_id['username'],
		);
	}

	/**
	 * The domain a URL's site is registered under, as near as can be told without the public suffix list.
	 *
	 * @param string $url URL.
	 * @return string Empty if it has no host.
	 */
	public static function domain( string $url ): string {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		$host = (string) preg_replace( '/^www\./', '', $host );
		if ( '' === $host || filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return $host;
		}

		$labels = explode( '.', $host );
		$count  = count( $labels );
		if ( $count <= 2 ) {
			return $host;
		}

		$keep = 2 === strlen( $labels[ $count - 1 ] ) && in_array( $labels[ $count - 2 ], self::SECOND_LEVELS, true ) ? 3 : 2;

		return implode( '.', array_slice( $labels, -$keep ) );
	}
}

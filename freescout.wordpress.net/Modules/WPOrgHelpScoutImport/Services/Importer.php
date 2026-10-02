<?php
/**
 * Imports one HelpScout conversation.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Services;

use App\Attachment;
use App\Conversation;
use App\Customer;
use App\Email;
use App\Mailbox;
use App\Thread;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;

/**
 * Creates the conversation, or adds what's new to it, and keeps its status, assignee, and dates as HelpScout has them.
 *
 * Everything is written as imported, without the events live email fires, so nothing is sent, no workflow runs,
 * and our other modules leave it alone.
 */
final class Importer {

	/**
	 * Created a conversation.
	 *
	 * @var string
	 */
	public const IMPORTED = 'imported';

	/**
	 * Brought an imported conversation up to date.
	 *
	 * @var string
	 */
	public const UPDATED = 'updated';

	/**
	 * Left it out: spam.
	 *
	 * @var string
	 */
	public const SKIPPED_SPAM = 'spam';

	/**
	 * Left it out: a draft, or deleted in HelpScout.
	 *
	 * @var string
	 */
	public const SKIPPED_UNPUBLISHED = 'unpublished';

	/**
	 * Left it out: its sender has no email.
	 *
	 * @var string
	 */
	public const SKIPPED_NO_SENDER = 'no_sender';

	/**
	 * Left it out: deleted in FreeScout since it was imported.
	 *
	 * @var string
	 */
	public const SKIPPED_DELETED = 'deleted';

	/**
	 * Left it out: it has nothing to import, like only line items.
	 *
	 * @var string
	 */
	public const SKIPPED_EMPTY = 'empty';

	/**
	 * Left it out: HelpScout no longer has it, or merged it into another.
	 *
	 * @var string
	 */
	public const SKIPPED_GONE = 'gone';

	/**
	 * How old an email can be for HelpScout to still keep its original, with its Message-ID, in days: 2 years.
	 *
	 * @var int
	 */
	private const ORIGINAL_KEPT_DAYS = 730;

	/**
	 * HelpScout's read-tracking image, which its emails carry, and replies quote.
	 *
	 * @var string
	 */
	private const TRACKER = '#<img\b[^>]*\bsrc\s*=\s*(["\'])https?://secure\.helpscout\.net/notification/[^"\']*\1[^>]*>#i';

	/**
	 * HelpScout conversation statuses, as FreeScout's.
	 *
	 * @var int[]
	 */
	private const STATUSES = array(
		'active'  => Conversation::STATUS_ACTIVE,
		'pending' => Conversation::STATUS_PENDING,
		'closed'  => Conversation::STATUS_CLOSED,
	);

	/**
	 * HelpScout thread types, as FreeScout's; chats depend on who wrote them, and line items are left out.
	 *
	 * Phone calls and forwards become notes: FreeScout has no thread type for them.
	 *
	 * @var int[]
	 */
	private const THREAD_TYPES = array(
		'customer'      => Thread::TYPE_CUSTOMER,
		'message'       => Thread::TYPE_MESSAGE,
		'note'          => Thread::TYPE_NOTE,
		'phone'         => Thread::TYPE_NOTE,
		'forwardparent' => Thread::TYPE_NOTE,
		'forwardchild'  => Thread::TYPE_NOTE,
	);

	/**
	 * HelpScout API client.
	 *
	 * @var HelpScout
	 */
	private $helpscout;

	/**
	 * Users and senders.
	 *
	 * @var People
	 */
	private $people;

	/**
	 * Constructor.
	 *
	 * @param HelpScout $helpscout HelpScout API client.
	 * @param People    $people    Users and senders.
	 */
	public function __construct( HelpScout $helpscout, People $people ) {
		$this->helpscout = $helpscout;
		$this->people    = $people;
	}

	/**
	 * Whether import() left a conversation out.
	 *
	 * @param string $result What import() returned.
	 * @return bool
	 */
	public static function is_skipped( string $result ): bool {
		return ! in_array( $result, array( self::IMPORTED, self::UPDATED ), true );
	}

	/**
	 * Imports a conversation, or brings an imported one up to date.
	 *
	 * Everything is read from HelpScout first, then written in one transaction, so a failure leaves nothing half
	 * imported; running it again picks up where it stopped.
	 *
	 * Once agents worked on a conversation in FreeScout, importing it again only adds HelpScout's new threads: its
	 * status and assignee stay FreeScout's.
	 *
	 * @param array   $source  HelpScout conversation, as HelpScout lists it.
	 * @param Mailbox $mailbox FreeScout mailbox to import into.
	 * @return string IMPORTED, UPDATED, or one of the SKIPPED_ constants.
	 *
	 * @throws \Throwable If it couldn't be read or written; nothing of it is written then.
	 */
	public function import( array $source, Mailbox $mailbox ): string {
		$helpscout_id = (int) ( $source['id'] ?? 0 );
		$status       = self::STATUSES[ $source['status'] ?? '' ] ?? null;

		if ( 'spam' === ( $source['status'] ?? '' ) ) {
			return self::SKIPPED_SPAM;
		}

		if ( ! $helpscout_id || ! $status || 'published' !== ( $source['state'] ?? '' ) ) {
			return self::SKIPPED_UNPUBLISHED;
		}

		$imported     = ImportedConversation::query()->where( 'helpscout_id', $helpscout_id )->first();
		$conversation = $imported ? Conversation::find( $imported->conversation_id ) : null;

		// Deleted in FreeScout since: it stays deleted.
		if ( $imported && ! $conversation ) {
			return self::SKIPPED_DELETED;
		}

		$sender = $conversation ? $conversation->customer : $this->people->sender( $source['primaryCustomer'] ?? null );
		if ( ! $sender ) {
			return self::SKIPPED_NO_SENDER;
		}

		$threads = $this->new_threads( $helpscout_id, $this->helpscout->threads( $helpscout_id ) );
		if ( ! $conversation && ! $threads ) {
			return self::SKIPPED_EMPTY;
		}

		// Users are found or created before the transaction: they stay, even if this conversation fails.
		$this->meet_people( $source, $threads );

		$attachments = array();

		try {
			\DB::transaction(
				function () use ( &$conversation, &$attachments, $source, $mailbox, $sender, $threads, $status ): void {
					$worked_on = $conversation && $conversation->threads()->where( 'imported', false )->exists();

					if ( ! $conversation ) {
						$conversation = $this->create_conversation( $source, $mailbox, $sender );
					}

					foreach ( $threads as $thread ) {
						$attachments = array_merge( $attachments, $this->create_thread( $conversation, $thread, $sender ) );
					}

					$this->update_conversation( $conversation, $source, $status, ! $worked_on );

					ImportedConversation::query()->updateOrCreate(
						array( 'helpscout_id' => (int) $source['id'] ),
						array(
							'helpscout_number' => (int) ( $source['number'] ?? 0 ),
							'conversation_id'  => (int) $conversation->id,
							'tags'             => self::json( array_values( array_filter( array_map( array( self::class, 'tag_name' ), (array) ( $source['tags'] ?? array() ) ) ) ) ),
							'custom_fields'    => self::json( (array) ( $source['customFields'] ?? array() ) ),
						)
					);
				}
			);
		} catch ( \Throwable $e ) {
			// The attachments' rows are rolled back; their files aren't.
			foreach ( $attachments as $attachment ) {
				Attachment::getDisk()->delete( $attachment->getStorageFilePath() );
			}

			throw $e;
		} finally {
			foreach ( $threads as $thread ) {
				foreach ( $thread['files'] as $file ) {
					if ( is_resource( $file['data'] ) ) {
						fclose( $file['data'] );
					}
				}
			}
		}

		return $imported ? self::UPDATED : self::IMPORTED;
	}

	/**
	 * Finds or creates the FreeScout users for the HelpScout users a conversation names.
	 *
	 * @param array   $source  HelpScout conversation.
	 * @param array[] $threads Threads to import, from new_threads().
	 * @return void
	 */
	private function meet_people( array $source, array $threads ): void {
		$people = array( $source['createdBy'] ?? null, $source['assignee'] ?? null, $source['closedByUser'] ?? null );
		foreach ( $threads as $thread ) {
			$people[] = $thread['createdBy'] ?? null;
			$people[] = $thread['assignedTo'] ?? null;
		}

		foreach ( $people as $person ) {
			if ( $this->people->assignee( $person ) ) {
				$this->people->remember( $person );
			}
		}
	}

	/**
	 * Picks the threads to import that weren't imported yet, and reads what they need from HelpScout.
	 *
	 * @param int     $conversation_id HelpScout conversation ID.
	 * @param array[] $threads         The conversation's threads, oldest first.
	 * @return array[] Threads, each with `fs_type` set to FreeScout's, plus `message_id`, `files`, and `images`.
	 */
	private function new_threads( int $conversation_id, array $threads ): array {
		$ids      = array_map( 'intval', array_column( $threads, 'id' ) );
		$done     = $ids ? ImportedThread::query()->whereIn( 'helpscout_id', $ids )->pluck( 'helpscout_id' )->map( 'intval' )->all() : array();
		$original = Carbon::now()->subDays( self::ORIGINAL_KEPT_DAYS );
		$new      = array();

		foreach ( $threads as $thread ) {
			$type = self::thread_type( $thread );
			if ( ! $type || in_array( (int) ( $thread['id'] ?? 0 ), $done, true ) ) {
				continue;
			}

			$thread['fs_type']    = $type;
			$thread['message_id'] = null;
			$thread['files']      = array();

			// Replies to these emails find the conversation by their Message-ID; HelpScout keeps them for 2 years.
			$created_at = self::date( $thread['createdAt'] ?? null );
			if ( 'email' === ( $thread['source']['type'] ?? '' ) && in_array( $type, array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ), true ) && $created_at && $created_at->greaterThan( $original ) ) {
				$thread['message_id'] = self::message_id( (string) $this->helpscout->original_headers( $conversation_id, (int) $thread['id'] ) );
			}

			foreach ( (array) ( $thread['_embedded']['attachments'] ?? array() ) as $attachment ) {
				// HelpScout found a virus in it, and won't give it out.
				if ( 'virus' === ( $attachment['state'] ?? '' ) ) {
					continue;
				}

				$thread['files'][] = array(
					'name' => (string) ( $attachment['filename'] ?? '' ),
					'mime' => (string) ( $attachment['mimeType'] ?? 'application/octet-stream' ),
					'data' => $this->helpscout->attachment( $conversation_id, (int) ( $attachment['id'] ?? 0 ) ),
				);
			}

			$thread['images'] = $this->images( (string) ( $thread['body'] ?? '' ) );

			$new[] = $thread;
		}

		return $new;
	}

	/**
	 * Downloads the images in a body that HelpScout hosts, which would go with the account.
	 *
	 * An image that can't be downloaded keeps its link.
	 *
	 * @param string $body Thread body.
	 * @return array[] Files by their `src` in the body, each with `name`, `mime`, and `data`.
	 */
	private function images( string $body ): array {
		$hosts  = array_map( 'strtolower', (array) config( 'wporghelpscoutimport.image_hosts', array() ) );
		$images = array();

		preg_match_all( '/<img\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1/i', $body, $matches );

		foreach ( array_unique( $matches[2] ) as $src ) {
			$url = html_entity_decode( $src, ENT_QUOTES | ENT_HTML5 );
			if ( str_starts_with( $url, '//' ) ) {
				$url = 'https:' . $url;
			}

			if ( ! in_array( strtolower( (string) parse_url( $url, PHP_URL_HOST ) ), $hosts, true ) ) {
				continue;
			}

			$data = $this->helpscout->image( $url );
			$mime = $data ? (string) finfo_buffer( finfo_open(), $data, FILEINFO_MIME_TYPE ) : '';
			if ( ! str_starts_with( $mime, 'image/' ) ) {
				continue;
			}

			$name           = basename( (string) parse_url( $url, PHP_URL_PATH ) );
			$images[ $src ] = array(
				'name' => '' !== $name ? $name : 'image',
				'mime' => $mime,
				'data' => $data,
			);
		}

		return $images;
	}

	/**
	 * Creates the conversation, without threads yet.
	 *
	 * @param array    $source  HelpScout conversation.
	 * @param Mailbox  $mailbox FreeScout mailbox.
	 * @param Customer $sender  Its sender.
	 * @return Conversation
	 */
	private function create_conversation( array $source, Mailbox $mailbox, Customer $sender ): Conversation {
		$by_user = 'user' === ( $source['createdBy']['type'] ?? '' );

		$conversation                 = new Conversation();
		$conversation->type           = self::conversation_type( (string) ( $source['type'] ?? '' ) );
		$conversation->subject        = (string) ( $source['subject'] ?? '' );
		$conversation->mailbox_id     = $mailbox->id;
		$conversation->customer_id    = $sender->id;
		$conversation->customer_email = self::email( $source['primaryCustomer']['email'] ?? null ) ?? (string) $sender->getMainEmail();
		$conversation->source_via     = $by_user ? Conversation::PERSON_USER : Conversation::PERSON_CUSTOMER;
		$conversation->source_type    = self::source_type( (string) ( $source['source']['type'] ?? '' ) );
		$conversation->state          = Conversation::STATE_PUBLISHED;
		$conversation->status         = Conversation::STATUS_ACTIVE;
		$conversation->imported       = true;
		$conversation->read_by_user   = true;
		$conversation->created_at     = self::date( $source['createdAt'] ?? null ) ?? Carbon::now();

		if ( $by_user ) {
			$creator                          = $this->people->user( $source['createdBy'] ?? null );
			$conversation->created_by_user_id = $creator ? $creator->id : $this->people->robot()->id;
		} else {
			$conversation->created_by_customer_id = $sender->id;
		}

		$conversation->setCc( (array) ( $source['cc'] ?? array() ) );
		$conversation->setBcc( (array) ( $source['bcc'] ?? array() ) );
		$conversation->updateFolder( $mailbox );

		// Core numbers new conversations, and uses up the next number an administrator set for live email doing so.
		$next_number = \Option::get( 'next_ticket', 0, true, false );
		$conversation->save();
		if ( $next_number ) {
			\Option::set( 'next_ticket', $next_number );
		}

		// HelpScout's number, which people quote, unless a FreeScout conversation has it already.
		$number = (int) ( $source['number'] ?? 0 );
		if ( $number > 0 && ! Conversation::query()->where( 'number', $number )->exists() ) {
			$conversation->number = $number;
			$conversation->save();
		}

		return $conversation;
	}

	/**
	 * Adds a thread, without the observers and events of a new one.
	 *
	 * Core's thread observer would date the conversation's last reply now, and announce every thread to agents'
	 * open pages; update_conversation() sets what the observer would.
	 *
	 * An email FreeScout has already, like one sent to two mailboxes, or fetched by FreeScout too, keeps its Message-ID
	 * where it is: it's unique. If it's in this conversation already, it isn't added again.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param array        $source       HelpScout thread, from new_threads().
	 * @param Customer     $sender       The conversation's sender.
	 * @return Attachment[] Attachments created.
	 */
	private function create_thread( Conversation $conversation, array $source, Customer $sender ): array {
		$type        = (int) $source['fs_type'];
		$by_customer = Thread::TYPE_CUSTOMER === $type;
		$author      = $by_customer ? ( $this->people->sender( $source['customer'] ?? null ) ?? $this->people->sender( $source['createdBy'] ?? null ) ?? $sender ) : null;
		$user        = $by_customer ? null : $this->people->user( $source['createdBy'] ?? null );
		$created_at  = self::date( $source['createdAt'] ?? null ) ?? Carbon::now();
		$message_id  = $source['message_id'];

		if ( $message_id ) {
			$existing = Thread::query()->where( 'message_id', $message_id )->first( array( 'id', 'conversation_id' ) );
			if ( $existing && (int) $existing->conversation_id === (int) $conversation->id ) {
				$this->remember_thread( $source, (int) $existing->id, $by_customer );

				return array();
			}

			if ( $existing ) {
				$message_id = null;
			}
		}

		$thread                  = new Thread();
		$thread->conversation_id = $conversation->id;
		$thread->type            = $type;
		$thread->status          = self::STATUSES[ $source['status'] ?? '' ] ?? $conversation->status;
		$thread->state           = Thread::STATE_PUBLISHED;
		$thread->body            = (string) preg_replace( self::TRACKER, '', (string) ( $source['body'] ?? '' ) );
		$thread->source_via      = $by_customer ? Thread::PERSON_CUSTOMER : Thread::PERSON_USER;
		$thread->source_type     = self::source_type( (string) ( $source['source']['type'] ?? '' ) );
		$thread->customer_id     = $author ? $author->id : $conversation->customer_id;
		$thread->imported        = true;
		$thread->first           = ! $conversation->threads()->exists();
		$thread->has_attachments = (bool) $source['files'];
		$thread->message_id      = $message_id;
		$thread->created_at      = $created_at;
		$thread->updated_at      = $created_at;

		if ( $by_customer ) {
			$thread->created_by_customer_id = $author ? $author->id : $sender->id;
			$thread->from                   = $author ? (string) $author->getMainEmail() : null;
		} else {
			$thread->created_by_user_id = $user ? $user->id : $this->people->robot()->id;
		}

		$assignee = $this->people->assignee( $source['assignedTo'] ?? null );
		if ( $assignee ) {
			$thread->user_id = $assignee->id;
		}

		$thread->setTo( (array) ( $source['to'] ?? array() ) );
		$thread->setCc( (array) ( $source['cc'] ?? array() ) );
		$thread->setBcc( (array) ( $source['bcc'] ?? array() ) );

		$thread_id   = (int) Thread::query()->insertGetId( $thread->getAttributes() );
		$attachments = array();

		foreach ( $source['files'] as $file ) {
			$attachment = Attachment::create( $file['name'], $file['mime'], null, $file['data'], null, false, $thread_id, $thread->created_by_user_id );
			if ( $attachment ) {
				$attachments[] = $attachment;
			}
		}

		// Like an email's inline images when FreeScout fetches it: embedded, and linked from the body.
		$body = (string) $thread->body;
		foreach ( $source['images'] as $src => $image ) {
			$attachment = Attachment::create( $image['name'], $image['mime'], null, $image['data'], null, true, $thread_id, $thread->created_by_user_id );
			if ( $attachment ) {
				$attachments[] = $attachment;
				$body          = str_replace( $src, $attachment->url(), $body );
			}
		}
		if ( $body !== $thread->body ) {
			Thread::query()->whereKey( $thread_id )->update( array( 'body' => $body ) );
		}

		$this->remember_thread( $source, $thread_id, $by_customer );

		return $attachments;
	}

	/**
	 * Keeps which FreeScout thread a HelpScout thread became, so it isn't imported again.
	 *
	 * @param array $source      HelpScout thread.
	 * @param int   $thread_id   FreeScout thread ID.
	 * @param bool  $by_customer Whether a sender wrote it.
	 * @return void
	 */
	private function remember_thread( array $source, int $thread_id, bool $by_customer ): void {
		ImportedThread::query()->create(
			array(
				'helpscout_id'      => (int) $source['id'],
				'thread_id'         => $thread_id,
				'helpscout_user_id' => $by_customer ? null : $this->people->remember( $source['createdBy'] ?? null ),
			)
		);
	}

	/**
	 * Sets what depends on the threads, and what HelpScout may have changed since the last import.
	 *
	 * @param Conversation $conversation  Conversation.
	 * @param array        $source        HelpScout conversation.
	 * @param int          $status        Its status, as FreeScout's.
	 * @param bool         $from_source   Whether to take HelpScout's status, assignee, and dates too; not once
	 *                                    agents worked on it in FreeScout.
	 * @return void
	 */
	private function update_conversation( Conversation $conversation, array $source, int $status, bool $from_source ): void {
		$threads = $conversation->threads()->where( 'state', Thread::STATE_PUBLISHED )->orderBy( 'created_at' )->orderBy( 'id' )->get();
		$replies = $threads->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ) );
		$last    = $replies->last() ?? $threads->last();

		$conversation->threads_count   = $replies->count();
		$conversation->has_attachments = $threads->contains( 'has_attachments', true );

		if ( $last ) {
			$conversation->setPreview( (string) $last->body );
			$conversation->last_reply_at   = $last->created_at;
			$conversation->last_reply_from = $last->source_via;
		}

		$last_from_sender = $replies->where( 'type', Thread::TYPE_CUSTOMER )->last();
		if ( $last_from_sender && self::has_column( 'last_customer_reply_at' ) ) {
			$conversation->last_customer_reply_at = $last_from_sender->created_at;
		}

		if ( $from_source ) {
			$conversation->status       = $status;
			$conversation->read_by_user = true;

			$assignee              = $this->people->assignee( $source['assignee'] ?? null );
			$conversation->user_id = $assignee ? $assignee->id : null;

			$closer                          = $this->people->user( $source['closedByUser'] ?? null );
			$conversation->closed_at         = Conversation::STATUS_CLOSED === $status ? self::date( $source['closedAt'] ?? null ) : null;
			$conversation->closed_by_user_id = Conversation::STATUS_CLOSED === $status && $closer ? $closer->id : null;

			$updated_at                    = self::date( $source['userUpdatedAt'] ?? null ) ?? ( $last ? $last->created_at : $conversation->created_at );
			$conversation->user_updated_at = $updated_at;
			$conversation->updated_at      = $updated_at;
		}

		$conversation->timestamps = false;
		$conversation->updateFolder();
		$conversation->save();
		$conversation->timestamps = true;
	}

	/**
	 * FreeScout's thread type for a HelpScout thread.
	 *
	 * @param array $thread HelpScout thread.
	 * @return int|null Null to leave it out: line items, and threads that weren't published or hidden.
	 */
	private static function thread_type( array $thread ): ?int {
		$type  = (string) ( $thread['type'] ?? '' );
		$state = (string) ( $thread['state'] ?? '' );

		// Drafts and replies held back for review were never sent.
		if ( ! in_array( $state, array( 'published', 'hidden' ), true ) ) {
			return null;
		}

		// Hidden from what the sender is sent, not from agents: a hidden reply becomes a note, which isn't sent either.
		if ( 'hidden' === $state && 'message' === $type ) {
			return Thread::TYPE_NOTE;
		}

		if ( in_array( $type, array( 'chat', 'beaconchat' ), true ) ) {
			return 'customer' === ( $thread['createdBy']['type'] ?? '' ) ? Thread::TYPE_CUSTOMER : Thread::TYPE_MESSAGE;
		}

		return self::THREAD_TYPES[ $type ] ?? null;
	}

	/**
	 * FreeScout's conversation type for HelpScout's.
	 *
	 * @param string $type HelpScout type.
	 * @return int
	 */
	private static function conversation_type( string $type ): int {
		if ( 'phone' === $type ) {
			return Conversation::TYPE_PHONE;
		}

		return 'chat' === $type ? Conversation::TYPE_CHAT : Conversation::TYPE_EMAIL;
	}

	/**
	 * FreeScout's source type for HelpScout's.
	 *
	 * @param string $type HelpScout source type.
	 * @return int
	 */
	private static function source_type( string $type ): int {
		if ( in_array( $type, array( 'email', 'emailfwd' ), true ) ) {
			return Conversation::SOURCE_TYPE_EMAIL;
		}

		return in_array( $type, array( 'web', 'beacon' ), true ) ? Conversation::SOURCE_TYPE_WEB : Conversation::SOURCE_TYPE_API;
	}

	/**
	 * Reads the Message-ID header of an email.
	 *
	 * @param string $headers The email's headers.
	 * @return string|null Message-ID without angle brackets, as FreeScout stores it.
	 */
	private static function message_id( string $headers ): ?string {
		if ( ! preg_match( '/^Message-ID:\s*<?([^\s<>]+)>?/mi', $headers, $match ) ) {
			return null;
		}

		return mb_substr( $match[1], 0, 998 );
	}

	/**
	 * Parses one of HelpScout's dates.
	 *
	 * @param mixed $date ISO 8601 date, in UTC.
	 * @return Carbon|null In the app's timezone, as core stores dates.
	 */
	private static function date( $date ): ?Carbon {
		if ( ! is_string( $date ) || '' === $date ) {
			return null;
		}

		try {
			return Carbon::parse( $date )->setTimezone( (string) config( 'app.timezone' ) );
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * An email address as FreeScout stores it.
	 *
	 * @param mixed $email Email.
	 * @return string|null Null if it isn't one.
	 */
	private static function email( $email ): ?string {
		$email = is_string( $email ) ? Email::sanitizeEmail( $email ) : false;

		return $email ? $email : null;
	}

	/**
	 * A HelpScout tag's name.
	 *
	 * @param mixed $tag Tag object, or name.
	 * @return string
	 */
	private static function tag_name( $tag ): string {
		return is_array( $tag ) ? (string) ( $tag['tag'] ?? $tag['name'] ?? '' ) : (string) $tag;
	}

	/**
	 * Encodes a list for storage, or null if it's empty.
	 *
	 * @param array $items Items.
	 * @return string|null
	 */
	private static function json( array $items ): ?string {
		return $items ? (string) json_encode( $items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) : null;
	}

	/**
	 * Whether core's conversations table has a column, which newer versions add.
	 *
	 * @param string $column Column name.
	 * @return bool
	 */
	private static function has_column( string $column ): bool {
		static $columns = array();

		if ( ! isset( $columns[ $column ] ) ) {
			$columns[ $column ] = Schema::hasColumn( 'conversations', $column );
		}

		return $columns[ $column ];
	}
}

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
use App\Mailbox;
use App\Thread;
use App\User;
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
	 * Left it out: spam, a draft, deleted, or without a sender.
	 *
	 * @var string
	 */
	public const SKIPPED = 'skipped';

	/**
	 * Thread meta key for what FreeScout can't show otherwise.
	 *
	 * @var string
	 */
	public const META = 'wporghelpscoutimport';

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
	 * Imports a conversation, or brings an imported one up to date.
	 *
	 * Everything is read from HelpScout first, then written in one transaction, so a failure leaves nothing half
	 * imported; running it again picks up where it stopped.
	 *
	 * @param array   $source  HelpScout conversation, as HelpScout lists it.
	 * @param Mailbox $mailbox FreeScout mailbox to import into.
	 * @return string IMPORTED, UPDATED, or SKIPPED.
	 */
	public function import( array $source, Mailbox $mailbox ): string {
		$helpscout_id = (int) ( $source['id'] ?? 0 );
		$status       = self::STATUSES[ $source['status'] ?? '' ] ?? null;

		if ( ! $helpscout_id || ! $status || 'published' !== ( $source['state'] ?? '' ) ) {
			return self::SKIPPED;
		}

		$imported     = ImportedConversation::query()->where( 'helpscout_id', $helpscout_id )->first();
		$conversation = $imported ? Conversation::find( $imported->conversation_id ) : null;

		// Deleted in FreeScout since: it stays deleted.
		if ( $imported && ! $conversation ) {
			return self::SKIPPED;
		}

		$sender = $conversation ? $conversation->customer : $this->people->sender( $source['primaryCustomer'] ?? null );
		if ( ! $sender ) {
			return self::SKIPPED;
		}

		$threads = $this->new_threads( $helpscout_id, $this->helpscout->threads( $helpscout_id ) );
		if ( ! $conversation && ! $threads ) {
			return self::SKIPPED;
		}

		\DB::transaction(
			function () use ( &$conversation, $source, $mailbox, $sender, $threads, $status ): void {
				if ( ! $conversation ) {
					$conversation = $this->create_conversation( $source, $mailbox, $sender );
				}

				foreach ( $threads as $thread ) {
					$this->create_thread( $conversation, $thread, $sender );
				}

				$this->update_conversation( $conversation, $source, $status );

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

		return $imported ? self::UPDATED : self::IMPORTED;
	}

	/**
	 * Picks the threads to import that weren't imported yet, and reads what they need from HelpScout.
	 *
	 * @param int     $conversation_id HelpScout conversation ID.
	 * @param array[] $threads         The conversation's threads, oldest first.
	 * @return array[] Threads, each with `fs_type` set to FreeScout's, plus `message_id`, `files`, and `images`.
	 */
	private function new_threads( int $conversation_id, array $threads ): array {
		$ids  = array_map( 'intval', array_column( $threads, 'id' ) );
		$done = $ids ? ImportedThread::query()->whereIn( 'helpscout_id', $ids )->pluck( 'helpscout_id' )->map( 'intval' )->all() : array();
		$new  = array();

		foreach ( $threads as $thread ) {
			$type = self::thread_type( $thread );
			if ( ! $type || in_array( (int) ( $thread['id'] ?? 0 ), $done, true ) || 'published' !== ( $thread['state'] ?? '' ) ) {
				continue;
			}

			$thread['fs_type']    = $type;
			$thread['message_id'] = null;
			$thread['files']      = array();

			// Replies to these emails find the conversation by their Message-ID.
			if ( 'email' === ( $thread['source']['type'] ?? '' ) && in_array( $type, array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ), true ) ) {
				$thread['message_id'] = self::message_id( (string) $this->helpscout->original_source( $conversation_id, (int) $thread['id'] ) );
			}

			foreach ( (array) ( $thread['_embedded']['attachments'] ?? array() ) as $attachment ) {
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
		$conversation->customer_email = (string) $sender->getMainEmail();
		$conversation->source_via     = $by_user ? Conversation::PERSON_USER : Conversation::PERSON_CUSTOMER;
		$conversation->source_type    = self::source_type( (string) ( $source['source']['type'] ?? '' ) );
		$conversation->state          = Conversation::STATE_PUBLISHED;
		$conversation->status         = Conversation::STATUS_ACTIVE;
		$conversation->imported       = true;
		$conversation->read_by_user   = true;
		$conversation->created_at     = self::date( $source['createdAt'] ?? null ) ?? Carbon::now();

		if ( $by_user ) {
			$creator                          = $this->people->user( $source['createdBy'] );
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
	 * @param Conversation $conversation Conversation.
	 * @param array        $source       HelpScout thread, from new_threads().
	 * @param Customer     $sender       The conversation's sender.
	 * @return void
	 */
	private function create_thread( Conversation $conversation, array $source, Customer $sender ): void {
		$type        = (int) $source['fs_type'];
		$by_customer = Thread::TYPE_CUSTOMER === $type;
		$author      = $by_customer ? ( $this->people->sender( $source['customer'] ?? null ) ?? $this->people->sender( $source['createdBy'] ?? null ) ?? $sender ) : null;
		$user        = $by_customer ? null : $this->people->user( $source['createdBy'] ?? null );
		$created_at  = self::date( $source['createdAt'] ?? null ) ?? Carbon::now();

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
		$thread->message_id      = $source['message_id'];
		$thread->created_at      = $created_at;
		$thread->updated_at      = $created_at;

		if ( $by_customer ) {
			$thread->created_by_customer_id = $author ? $author->id : $sender->id;
			$thread->from                   = $author ? (string) $author->getMainEmail() : null;
		} else {
			$thread->created_by_user_id = $user ? $user->id : $this->people->robot()->id;

			// Credited to the robot: keep who it was.
			if ( ! $user && is_array( $source['createdBy'] ?? null ) ) {
				$thread->setMeta( self::META, array( 'author' => self::name( $source['createdBy'] ) ) );
			}
		}

		$assignee = $this->people->user( $source['assignedTo'] ?? null );
		if ( $assignee ) {
			$thread->user_id = $assignee->id;
		}

		$thread->setTo( (array) ( $source['to'] ?? array() ) );
		$thread->setCc( (array) ( $source['cc'] ?? array() ) );
		$thread->setBcc( (array) ( $source['bcc'] ?? array() ) );

		$thread_id = (int) Thread::query()->insertGetId( $thread->getAttributes() );

		foreach ( $source['files'] as $file ) {
			Attachment::create( $file['name'], $file['mime'], null, $file['data'], null, false, $thread_id, $thread->created_by_user_id );
		}

		// Like an email's inline images when FreeScout fetches it: embedded, and linked from the body.
		$body = (string) $thread->body;
		foreach ( $source['images'] as $src => $image ) {
			$attachment = Attachment::create( $image['name'], $image['mime'], null, $image['data'], null, true, $thread_id, $thread->created_by_user_id );
			if ( $attachment ) {
				$body = str_replace( $src, $attachment->url(), $body );
			}
		}
		if ( $body !== $thread->body ) {
			Thread::query()->whereKey( $thread_id )->update( array( 'body' => $body ) );
		}

		$author = $by_customer ? null : $this->people->remember( $source['createdBy'] ?? null );

		ImportedThread::query()->create(
			array(
				'helpscout_id'      => (int) $source['id'],
				'thread_id'         => $thread_id,
				'helpscout_user_id' => $author,
			)
		);
	}

	/**
	 * Sets what depends on the threads, and what HelpScout may have changed since the last import.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param array        $source       HelpScout conversation.
	 * @param int          $status       Its status, as FreeScout's.
	 * @return void
	 */
	private function update_conversation( Conversation $conversation, array $source, int $status ): void {
		$threads = $conversation->threads()->where( 'state', Thread::STATE_PUBLISHED )->orderBy( 'created_at' )->orderBy( 'id' )->get();
		$replies = $threads->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ) );
		$last    = $replies->last() ?? $threads->last();

		$conversation->threads_count   = $replies->count();
		$conversation->has_attachments = $threads->contains( 'has_attachments', true );
		$conversation->status          = $status;
		$conversation->read_by_user    = true;

		if ( $last ) {
			$conversation->setPreview( (string) $last->body );
			$conversation->last_reply_at   = $last->created_at;
			$conversation->last_reply_from = $last->source_via;
		}

		$last_from_sender = $replies->where( 'type', Thread::TYPE_CUSTOMER )->last();
		if ( $last_from_sender && self::has_column( 'last_customer_reply_at' ) ) {
			$conversation->last_customer_reply_at = $last_from_sender->created_at;
		}

		$assignee              = $this->people->user( $source['assignee'] ?? null );
		$conversation->user_id = $assignee ? $assignee->id : null;

		$closer                          = $this->people->user( $source['closedByUser'] ?? null );
		$conversation->closed_at         = Conversation::STATUS_CLOSED === $status ? self::date( $source['closedAt'] ?? null ) : null;
		$conversation->closed_by_user_id = Conversation::STATUS_CLOSED === $status && $closer ? $closer->id : null;

		$updated_at                    = self::date( $source['userUpdatedAt'] ?? null ) ?? ( $last ? $last->created_at : $conversation->created_at );
		$conversation->user_updated_at = $updated_at;
		$conversation->updated_at      = $updated_at;
		$conversation->timestamps      = false;

		$conversation->updateFolder();
		$conversation->save();
		$conversation->timestamps = true;
	}

	/**
	 * FreeScout's thread type for a HelpScout thread.
	 *
	 * @param array $thread HelpScout thread.
	 * @return int|null Null to leave it out.
	 */
	private static function thread_type( array $thread ): ?int {
		$type = (string) ( $thread['type'] ?? '' );

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
	 * @param string $email Raw email.
	 * @return string|null Message-ID without angle brackets, as FreeScout stores it.
	 */
	private static function message_id( string $email ): ?string {
		$headers = preg_split( '/\r?\n\r?\n/', $email, 2 )[0];

		if ( ! preg_match( '/^Message-ID:\s*<?([^\s<>]+)>?/mi', (string) $headers, $match ) ) {
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
	 * A HelpScout person's name, or their email without one.
	 *
	 * @param array $person HelpScout person object.
	 * @return string
	 */
	private static function name( array $person ): string {
		$name = trim( ( $person['first'] ?? '' ) . ' ' . ( $person['last'] ?? '' ) );

		return '' !== $name ? $name : (string) ( $person['email'] ?? '' );
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

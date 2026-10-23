<?php
/**
 * Imports one conversation of the other FreeScout.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Services;

use App\Attachment;
use App\Conversation;
use App\Customer;
use App\Email;
use App\Mailbox;
use App\Thread;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgFreeScoutImport\Entities\ImportedConversation;
use Modules\WPOrgFreeScoutImport\Entities\ImportedThread;
use Modules\WPOrgFreeScoutImport\Exceptions\ApiError;

/**
 * Creates the conversation, with a number of this FreeScout's, or adds what's new to it, and keeps its status,
 * assignee, and dates as the other FreeScout has them.
 *
 * Everything is written as imported, without the events live email fires, so nothing is sent, workflows aren't
 * triggered, and our other modules leave it alone. That doesn't keep it from automatic workflows that the Workflows
 * module runs on a schedule, over conversations they apply to: those without the "Imported" condition act on imported
 * conversations too.
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
	 * Left it out: a draft, or deleted there.
	 *
	 * @var string
	 */
	public const SKIPPED_UNPUBLISHED = 'unpublished';

	/**
	 * Left it out: it names no sender with an email.
	 *
	 * @var string
	 */
	public const SKIPPED_NO_SENDER = 'no_sender';

	/**
	 * Left it out: deleted here since it was imported.
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
	 * Left it out: retrying it, it's in another mailbox there now, imported with that one.
	 *
	 * @var string
	 */
	public const SKIPPED_MOVED = 'moved';

	/**
	 * Left it out: the other FreeScout no longer has it.
	 *
	 * @var string
	 */
	public const SKIPPED_GONE = 'gone';

	/**
	 * Most bytes of images in emails' bodies copied per conversation; the rest keep their links.
	 *
	 * @var int
	 */
	private const MAX_IMAGE_BYTES_PER_CONVERSATION = 100 * 1024 * 1024;

	/**
	 * Line items that are kept: status changes, which the Reports module counts closes by, and assignments.
	 *
	 * @var int[]
	 */
	private const LINE_ITEMS = array(
		'changed-ticket-status'   => Thread::ACTION_TYPE_STATUS_CHANGED,
		'changed-ticket-assignee' => Thread::ACTION_TYPE_USER_CHANGED,
	);

	/**
	 * Thread types that are imported as they are.
	 *
	 * @var string[]
	 */
	private const THREAD_TYPES = array( 'customer', 'message', 'note', 'chat' );

	/**
	 * Folder type of the Custom Folders module's folders.
	 *
	 * @var int
	 */
	private const CUSTOM_FOLDER = 200;

	/**
	 * Core's conversation columns that newer versions, or modules, add, by whether they're there.
	 *
	 * @var bool[]
	 */
	private static $columns = array();

	/**
	 * The other FreeScout.
	 *
	 * @var Source
	 */
	private $source;

	/**
	 * Users and senders.
	 *
	 * @var People
	 */
	private $people;

	/**
	 * The source's tag colors, by name; null until they're read.
	 *
	 * @var int[]|null
	 */
	private $tag_colors = null;

	/**
	 * Images downloaded for the conversation being imported, by URL; null for those that weren't.
	 *
	 * @var array
	 */
	private $downloads = array();

	/**
	 * Attachments created for the conversation being imported, whose files go if its transaction is rolled back.
	 *
	 * @var Attachment[]
	 */
	private $attachments = array();

	/**
	 * Bytes of images downloaded for the conversation being imported.
	 *
	 * @var int
	 */
	private $download_bytes = 0;

	/**
	 * Constructor.
	 *
	 * @param Source $source The other FreeScout.
	 * @param People $people Users and senders.
	 */
	public function __construct( Source $source, People $people ) {
		$this->source = $source;
		$this->people = $people;
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
	 * Its attachments and images are downloaded first, then everything is written in one transaction, so a failure
	 * leaves nothing half imported; running it again picks up where it stopped.
	 *
	 * Once agents worked on a conversation here, importing it again only adds the source's new threads: its status and
	 * assignee stay this FreeScout's.
	 *
	 * @param array   $source  Source conversation, with its threads and tags, as the API lists it.
	 * @param Mailbox $mailbox Mailbox to import into.
	 * @return string IMPORTED, UPDATED, or one of the SKIPPED_ constants.
	 *
	 * @throws \Throwable If it couldn't be read or written, like an ApiError for an attachment that couldn't be
	 *                    downloaded; nothing of it is written then.
	 */
	public function import( array $source, Mailbox $mailbox ): string {
		$source_id    = (int) ( $source['id'] ?? 0 );
		$imported     = $source_id ? ImportedConversation::query()->where( 'source_id', $source_id )->first() : null;
		$conversation = $imported ? Conversation::find( $imported->conversation_id ) : null;
		$worked_on    = $conversation && People::worked_on()->where( 'conversation_id', $conversation->id )->exists();

		// Deleted here since: it stays deleted.
		if ( $imported && ! $conversation ) {
			return self::SKIPPED_DELETED;
		}

		if ( 'spam' === ( $source['status'] ?? '' ) ) {
			// Marked spam there since it was imported: it's spam here too, unless agents worked on it.
			if ( $conversation && ! $worked_on && ! $conversation->isSpam() ) {
				$conversation->status     = Conversation::STATUS_SPAM;
				$conversation->timestamps = false;
				$conversation->updateFolder();
				$conversation->save();
				$conversation->timestamps = true;
				self::update_counters( $conversation->mailbox );

				return self::UPDATED;
			}

			return self::SKIPPED_SPAM;
		}

		$status = array_flip( Conversation::$statuses )[ $source['status'] ?? '' ] ?? null;
		if ( ! $source_id || ! $status || 'published' !== ( $source['state'] ?? '' ) ) {
			return self::SKIPPED_UNPUBLISHED;
		}

		$sender = $conversation ? $conversation->customer : $this->people->sender( $source['customer'] ?? null );
		if ( ! $sender ) {
			return self::SKIPPED_NO_SENDER;
		}

		// Moved to another mailbox there since: it moves too, unless agents worked on it.
		$moved_from = $conversation && ! $worked_on && (int) $conversation->mailbox_id !== (int) $mailbox->id ? $conversation->mailbox : null;

		$threads = $this->new_threads( (array) ( $source['_embedded']['threads'] ?? array() ) );
		if ( ! $conversation && ! array_diff( array_column( $threads, 'fs_type' ), array( Thread::TYPE_LINEITEM ) ) ) {
			return self::SKIPPED_EMPTY;
		}

		$this->downloads      = array();
		$this->download_bytes = 0;
		$this->attachments    = array();

		// Users and senders are found or created before the transaction: they stay, even if this conversation fails.
		$this->meet_people( $source, $threads, $mailbox );
		$tags = Tags::available() ? $this->tag_colors() : null;

		$threads = $this->read_threads( $threads );

		try {
			\DB::transaction(
				function () use ( &$conversation, $imported, $source, $mailbox, $sender, $threads, $status, $worked_on, $moved_from, $tags ): void {
					if ( ! $conversation ) {
						$conversation = $this->create_conversation( $source, $mailbox, $sender );
					}

					if ( $moved_from ) {
						$conversation->mailbox_id = $mailbox->id;
						$conversation->setRelation( 'mailbox', $mailbox );
					}

					foreach ( $threads as $thread ) {
						$this->create_thread( $conversation, $thread, $sender );
					}

					$this->update_conversation( $conversation, $source, $status, ! $worked_on, (bool) $threads );

					$record = array(
						'source_number'   => (int) ( $source['number'] ?? 0 ),
						'conversation_id' => (int) $conversation->id,
						'creator_id'      => People::user_id( $source['createdBy'] ?? null ),
						'assignee_id'     => People::user_id( $source['assignee'] ?? null ),
						'closer_id'       => People::user_id( $source['closedByUser'] ?? null ),
					);

					// What agents changed stays; what the source changed since the last import comes across.
					if ( null !== $tags ) {
						$names                  = array_filter( array_map( 'strval', array_column( (array) ( $source['_embedded']['tags'] ?? array() ), 'name' ) ), 'strlen' );
						$written                = Tags::sync( (int) $conversation->id, $names, self::decode( $imported->written_tags ?? null ), $tags );
						$record['written_tags'] = (string) json_encode( (object) $written, JSON_UNESCAPED_UNICODE );
					}

					ImportedConversation::query()->updateOrCreate( array( 'source_id' => (int) $source['id'] ), $record );
				}
			);
		} catch ( \Throwable $e ) {
			// The attachments' rows are rolled back; their files aren't.
			foreach ( $this->attachments as $attachment ) {
				Attachment::getDisk()->delete( $attachment->getStorageFilePath() );
			}

			throw $e;
		} finally {
			$this->attachments = array();
			$this->close_downloads( $threads );
		}

		// Live email can take this number before it's committed, then this takes the next; the stored one, as the accessor may give the ID.
		if ( ! $imported && Conversation::query()->where( 'number', (int) $conversation->getOriginal( 'number' ) )->where( 'id', '!=', $conversation->id )->exists() ) {
			Conversation::query()->whereKey( $conversation->id )->toBase()->update( array( 'number' => (int) Conversation::query()->max( 'number' ) + 1 ) );
		}

		// The mailbox it left, or, once agents worked on it, the one it stays in: the import refreshes only its own.
		$other_mailbox = $moved_from ?? ( (int) $conversation->mailbox_id !== (int) $mailbox->id ? $conversation->mailbox : null );
		if ( $other_mailbox ) {
			self::update_counters( $other_mailbox );
		}

		return $imported ? self::UPDATED : self::IMPORTED;
	}

	/**
	 * Counts a mailbox's conversations for its folders again.
	 *
	 * Like core does, except for the Custom Folders module's folders of each agent's own conversations: the module
	 * counts those for whoever is logged in, and nobody is while importing, so their counts would be lost.
	 *
	 * @param Mailbox $mailbox Mailbox.
	 * @return void
	 */
	public static function update_counters( Mailbox $mailbox ): void {
		foreach ( $mailbox->folders()->get() as $folder ) {
			if ( self::CUSTOM_FOLDER === (int) $folder->type && ! $folder->user_id && ! empty( $folder->meta['own_only'] ) ) {
				continue;
			}

			$folder->updateCounters();
		}
	}

	/**
	 * The source's tag colors, read once per import job.
	 *
	 * @return int[] By tag name.
	 */
	private function tag_colors(): array {
		if ( null === $this->tag_colors ) {
			$this->tag_colors = $this->source->tag_colors();
		}

		return $this->tag_colors;
	}

	/**
	 * Finds or creates the users a conversation names, and the senders of its emails.
	 *
	 * Active users among them get access to the mailbox: they worked in it there.
	 *
	 * @param array   $source  Source conversation.
	 * @param array[] $threads Threads to import, from new_threads().
	 * @param Mailbox $mailbox Mailbox imported into.
	 * @return void
	 */
	private function meet_people( array $source, array $threads, Mailbox $mailbox ): void {
		$people = array( $source['createdBy'] ?? null, $source['assignee'] ?? null, $source['closedByUser'] ?? null );
		foreach ( $threads as $thread ) {
			$people[] = $thread['createdBy'] ?? null;
			$people[] = $thread['assignedTo'] ?? null;
			$this->people->sender( $thread['createdBy'] ?? null );
		}

		foreach ( $people as $person ) {
			$user = $this->people->user( $person );
			if ( $user ) {
				$this->people->grant_once( $user, $mailbox );
			}
		}
	}

	/**
	 * Picks the published threads that weren't imported yet, oldest first.
	 *
	 * @param array[] $threads The source conversation's threads, as the API lists them, newest first.
	 * @return array[] Threads, each with `fs_type` and `fs_status` set to this FreeScout's, and `fs_action` for line items.
	 */
	private function new_threads( array $threads ): array {
		usort(
			$threads,
			static function ( array $a, array $b ): int {
				return array( (string) ( $a['createdAt'] ?? '' ), (int) ( $a['id'] ?? 0 ) ) <=> array( (string) ( $b['createdAt'] ?? '' ), (int) ( $b['id'] ?? 0 ) );
			}
		);

		$ids  = array_map( 'intval', array_column( $threads, 'id' ) );
		$done = $ids ? ImportedThread::query()->whereIn( 'source_id', $ids )->pluck( 'source_id' )->map( 'intval' )->all() : array();
		$new  = array();

		foreach ( $threads as $thread ) {
			$id   = (int) ( $thread['id'] ?? 0 );
			$type = (string) ( $thread['type'] ?? '' );
			if ( ! $id || 'published' !== ( $thread['state'] ?? '' ) || in_array( $id, $done, true ) ) {
				continue;
			}

			$action = null;
			if ( 'lineitem' === $type ) {
				$action = self::LINE_ITEMS[ $thread['action']['type'] ?? '' ] ?? null;
				if ( ! $action ) {
					continue;
				}
			} elseif ( ! in_array( $type, self::THREAD_TYPES, true ) ) {
				continue;
			}

			$thread['fs_type']   = (int) array_flip( Thread::$types )[ $type ];
			$thread['fs_status'] = (int) ( array_flip( Thread::$statuses )[ $thread['status'] ?? '' ] ?? Thread::STATUS_NOCHANGE );
			$thread['fs_action'] = $action;
			$new[]               = $thread;
		}

		return $new;
	}

	/**
	 * Downloads what threads need: their attachments and images, and finds their Message-IDs.
	 *
	 * @param array[] $threads Threads, from new_threads().
	 * @return array[] Threads, each with `message_id`, `files`, and `images` too.
	 *
	 * @throws \Throwable If an attachment the source still has couldn't be downloaded; what was downloaded is closed then.
	 */
	private function read_threads( array $threads ): array {
		$message_ids = MessageIds::of( array_map( 'intval', array_column( $threads, 'id' ) ) );
		$read        = array();

		try {
			foreach ( $threads as $thread ) {
				$thread['message_id'] = in_array( (int) $thread['fs_type'], array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ), true ) ? ( $message_ids[ (int) $thread['id'] ] ?? null ) : null;
				$thread['files']      = array();
				$thread['images']     = array();
				$read[]               = $thread;
				$index                = count( $read ) - 1;

				foreach ( (array) ( $thread['_embedded']['attachments'] ?? array() ) as $attachment ) {
					$data = $this->source->attachment( (string) ( $attachment['fileUrl'] ?? '' ) );
					// Deleted there since, or not the source's own: the rest of the thread is imported without it.
					if ( ! $data ) {
						continue;
					}

					$read[ $index ]['files'][] = array(
						'name' => (string) ( $attachment['fileName'] ?? 'attachment' ),
						'mime' => (string) ( $attachment['mimeType'] ?? 'application/octet-stream' ),
						'data' => $data,
					);
				}

				$read[ $index ]['images'] = $this->images( (string) ( $thread['body'] ?? '' ) );
			}
		} catch ( \Throwable $e ) {
			$this->close_downloads( $read );

			throw $e;
		}

		return $read;
	}

	/**
	 * Closes, and so deletes, the temporary files a conversation's attachments and images were downloaded into.
	 *
	 * @param array[] $threads Threads, from read_threads().
	 * @return void
	 */
	private function close_downloads( array $threads ): void {
		$files = array_column( array_filter( $this->downloads ), 'data' );
		foreach ( $threads as $thread ) {
			$files = array_merge( $files, array_column( (array) ( $thread['files'] ?? array() ), 'data' ) );
		}

		foreach ( $files as $file ) {
			if ( is_resource( $file ) ) {
				fclose( $file );
			}
		}

		$this->downloads = array();
	}

	/**
	 * Downloads the images in a body that the other FreeScout keeps, which would go with it: pasted images.
	 *
	 * An image that can't be downloaded keeps its link.
	 *
	 * @param string $body Thread body.
	 * @return array[] Files by their `src` in the body, each with `name`, `mime`, and `data`.
	 */
	private function images( string $body ): array {
		$images = array();

		preg_match_all( '/<img\b[^>]*?\bsrc\s*=\s*(["\'])(.*?)\1/i', $body, $matches );

		foreach ( array_unique( $matches[2] ) as $src ) {
			$url = html_entity_decode( $src, ENT_QUOTES | ENT_HTML5 );
			if ( ! $this->source->is_own( $url ) ) {
				continue;
			}

			// Replies quote earlier emails' images: each is downloaded once per conversation, up to a total.
			if ( ! array_key_exists( $url, $this->downloads ) ) {
				$this->downloads[ $url ] = $this->download_image( $url );
			}

			if ( $this->downloads[ $url ] ) {
				$images[ $src ] = $this->downloads[ $url ];
			}
		}

		return $images;
	}

	/**
	 * Downloads an image into a temporary file, unless the conversation's images are too large already.
	 *
	 * @param string $url Image URL.
	 * @return array|null With `name`, `mime`, and `data`, the file; null if it isn't an image, or wasn't downloaded.
	 */
	private function download_image( string $url ): ?array {
		if ( $this->download_bytes >= self::MAX_IMAGE_BYTES_PER_CONVERSATION ) {
			return null;
		}

		$file = $this->source->image( $url );
		if ( ! $file ) {
			return null;
		}

		$path                  = self::path( $file );
		$this->download_bytes += (int) filesize( $path );
		$mime                  = (string) mime_content_type( $path );
		if ( ! str_starts_with( $mime, 'image/' ) ) {
			fclose( $file );

			return null;
		}

		$name = basename( (string) parse_url( $url, PHP_URL_PATH ) );

		return array(
			'name' => '' !== $name ? $name : 'image',
			'mime' => $mime,
			'data' => $file,
		);
	}

	/**
	 * Saves a downloaded file as an attachment.
	 *
	 * Core takes it as an uploaded file: it reads PDFs whole to check them for scripts, which a stream can't be.
	 *
	 * @param array $file      With `name`, `mime`, and `data`, the temporary file.
	 * @param int   $thread_id Thread ID.
	 * @param int   $user_id   User who added it, or 0 for a sender.
	 * @param bool  $embedded  Whether it's an image in the body.
	 * @return Attachment|null
	 */
	private function attach( array $file, int $thread_id, int $user_id, bool $embedded ): ?Attachment {
		$upload     = new UploadedFile( self::path( $file['data'] ), $file['name'], $file['mime'], null, null, true );
		$attachment = Attachment::create( $file['name'], $file['mime'], null, null, $upload, $embedded, $thread_id, $user_id ? $user_id : null );
		if ( ! $attachment ) {
			return null;
		}

		// Kept as soon as it's saved, so its file goes too if anything after it fails.
		$this->attachments[] = $attachment;

		return $attachment;
	}

	/**
	 * A temporary file's path.
	 *
	 * @param mixed $file Temporary file, a resource.
	 * @return string
	 */
	private static function path( mixed $file ): string {
		return (string) stream_get_meta_data( $file )['uri'];
	}

	/**
	 * Creates the conversation, without threads yet, numbered like a new one.
	 *
	 * @param array    $source  Source conversation.
	 * @param Mailbox  $mailbox Mailbox.
	 * @param Customer $sender  Its sender.
	 * @return Conversation
	 */
	private function create_conversation( array $source, Mailbox $mailbox, Customer $sender ): Conversation {
		$by_user = 'user' === ( $source['createdBy']['type'] ?? '' );

		$conversation                 = new Conversation();
		$conversation->type           = (int) ( array_flip( Conversation::$types )[ $source['type'] ?? '' ] ?? Conversation::TYPE_EMAIL );
		$conversation->subject        = (string) ( $source['subject'] ?? '' );
		$conversation->mailbox_id     = $mailbox->id;
		$conversation->customer_id    = $sender->id;
		$conversation->customer_email = self::email( $source['customer']['email'] ?? null ) ?? self::email( $sender->getMainEmail() );
		$conversation->source_via     = $by_user ? Conversation::PERSON_USER : Conversation::PERSON_CUSTOMER;
		$conversation->source_type    = (int) ( array_flip( Conversation::$source_types )[ $source['source']['type'] ?? '' ] ?? Conversation::SOURCE_TYPE_EMAIL );
		$conversation->state          = Conversation::STATE_PUBLISHED;
		$conversation->status         = Conversation::STATUS_ACTIVE;
		$conversation->imported       = true;
		$conversation->read_by_user   = true;
		$conversation->created_at     = self::date( $source['createdAt'] ?? null ) ?? Carbon::now();

		if ( $by_user ) {
			$creator                          = $this->people->user( $source['createdBy'] ?? null );
			$conversation->created_by_user_id = $creator ? $creator->id : $this->people->robot()->id;
		} else {
			$creator                              = $this->people->sender( $source['createdBy'] ?? null );
			$conversation->created_by_customer_id = $creator ? $creator->id : $sender->id;
		}

		$conversation->setCc( (array) ( $source['cc'] ?? array() ) );
		$conversation->setBcc( (array) ( $source['bcc'] ?? array() ) );
		$conversation->updateFolder( $mailbox );

		/*
		 * Core gives a new conversation the Next Conversation # an administrator set, which is meant for live email: it
		 * goes back, and the conversation gets the number after the highest. Core caches options, so whether it takes
		 * it can only be told after it did.
		 */
		$next_number = (int) \Option::get( 'next_ticket', 0, true, false );
		$conversation->save();
		if ( $next_number ) {
			\Option::set( 'next_ticket', $next_number );

			if ( (int) $conversation->getOriginal( 'number' ) === $next_number ) {
				$conversation->number = (int) Conversation::query()->where( 'id', '!=', $conversation->id )->max( 'number' ) + 1;
				$conversation->save();
			}
		}

		return $conversation;
	}

	/**
	 * Adds a thread, without the observers and events of a new one.
	 *
	 * Core's thread observer would date the conversation's last reply now, and announce every thread to agents'
	 * open pages; update_conversation() sets what the observer would.
	 *
	 * An email this FreeScout has already, like one sent to two mailboxes, keeps its Message-ID where it is: it's
	 * unique. If it's in this conversation already, it isn't added again.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param array        $source       Source thread, from read_threads().
	 * @param Customer     $sender       The conversation's sender.
	 * @return void
	 */
	private function create_thread( Conversation $conversation, array $source, Customer $sender ): void {
		$type         = (int) $source['fs_type'];
		$by_customer  = 'customer' === ( $source['createdBy']['type'] ?? '' );
		$is_line_item = Thread::TYPE_LINEITEM === $type;
		$author       = $by_customer ? ( $this->people->sender( $source['createdBy'] ?? null ) ?? $sender ) : null;
		$user         = $by_customer ? null : $this->people->user( $source['createdBy'] ?? null );
		$created_at   = self::date( $source['createdAt'] ?? null ) ?? Carbon::now();
		$message_id   = $source['message_id'];

		if ( $message_id ) {
			$existing = Thread::query()->where( 'message_id', $message_id )->first( array( 'id', 'conversation_id' ) );
			if ( $existing && (int) $existing->conversation_id === (int) $conversation->id ) {
				$this->remember_thread( $source, (int) $existing->id );

				return;
			}

			if ( $existing ) {
				$message_id = null;
			}
		}

		$customer = $this->people->sender( $source['customer'] ?? null );

		$thread                  = new Thread();
		$thread->conversation_id = $conversation->id;
		$thread->type            = $type;
		$thread->status          = (int) $source['fs_status'];
		$thread->state           = Thread::STATE_PUBLISHED;
		$thread->body            = $is_line_item ? null : (string) ( $source['body'] ?? '' );
		$thread->source_via      = $by_customer ? Thread::PERSON_CUSTOMER : Thread::PERSON_USER;
		$thread->source_type     = (int) ( array_flip( Thread::$source_types )[ $source['source']['type'] ?? '' ] ?? Thread::SOURCE_TYPE_WEB );
		$thread->customer_id     = $customer ? $customer->id : $conversation->customer_id;
		$thread->imported        = true;
		$thread->first           = ! $is_line_item && ! $conversation->threads()->where( 'type', '!=', Thread::TYPE_LINEITEM )->exists();
		$thread->has_attachments = (bool) $source['files'];
		$thread->message_id      = $message_id;
		$thread->opened_at       = self::date( $source['openedAt'] ?? null );
		$thread->created_at      = $created_at;
		$thread->updated_at      = $created_at;

		if ( $is_line_item ) {
			$thread->action_type = (int) $source['fs_action'];
		}

		if ( $by_customer ) {
			$thread->created_by_customer_id = $author->id;
			$thread->from                   = self::email( $author->getMainEmail() );
		} else {
			$thread->created_by_user_id = $user ? $user->id : $this->people->robot()->id;
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
			$this->attach( $file, $thread_id, (int) $thread->created_by_user_id, false );
		}

		// Like an email's inline images when FreeScout fetches it: embedded, and linked from the body.
		$body = (string) $thread->body;
		foreach ( $source['images'] as $src => $image ) {
			$attachment = $this->attach( $image, $thread_id, (int) $thread->created_by_user_id, true );
			if ( $attachment ) {
				$body = str_replace( (string) $src, $attachment->url(), $body );
			}
		}
		if ( $body !== (string) $thread->body ) {
			Thread::query()->whereKey( $thread_id )->update( array( 'body' => $body ) );
		}

		$this->remember_thread( $source, $thread_id );
	}

	/**
	 * Keeps which thread a source thread became, so it isn't imported again.
	 *
	 * @param array $source    Source thread.
	 * @param int   $thread_id Thread ID.
	 * @return void
	 */
	private function remember_thread( array $source, int $thread_id ): void {
		ImportedThread::query()->create(
			array(
				'source_id'          => (int) $source['id'],
				'thread_id'          => $thread_id,
				'source_user_id'     => People::user_id( $source['createdBy'] ?? null ),
				'source_assignee_id' => People::user_id( $source['assignedTo'] ?? null ),
			)
		);
	}

	/**
	 * Sets what depends on the threads, and what the source may have changed since the last import.
	 *
	 * The last reply and preview follow core's thread observer: the last reply is the latest email or reply, or on
	 * a phone conversation, note too; the preview is the latest of any of them, notes included, that isn't a forward.
	 *
	 * @param Conversation $conversation Conversation.
	 * @param array        $source       Source conversation.
	 * @param int          $status       Its status, as this FreeScout's.
	 * @param bool         $from_source  Whether to take the source's status, assignee, and dates too; not once agents
	 *                                   worked on it here.
	 * @param bool         $added        Whether threads were added.
	 * @return void
	 */
	private function update_conversation( Conversation $conversation, array $source, int $status, bool $from_source, bool $added ): void {
		$threads = $conversation->threads()->where( 'state', Thread::STATE_PUBLISHED )->orderBy( 'created_at' )->orderBy( 'id' )->get();
		$replies = $threads->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE ) );
		$notes   = $threads->where( 'type', Thread::TYPE_NOTE );
		$dated   = $conversation->isPhone() ? $threads->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE, Thread::TYPE_NOTE ) ) : $replies;
		$last    = $dated->last() ?? $notes->last();
		$preview = $threads->whereIn( 'type', array( Thread::TYPE_CUSTOMER, Thread::TYPE_MESSAGE, Thread::TYPE_NOTE ) )->reject(
			static function ( Thread $thread ): bool {
				return $thread->isForward();
			}
		)->last();

		$conversation->threads_count   = $replies->count();
		$conversation->has_attachments = $threads->contains( 'has_attachments', true );

		if ( $preview ) {
			$conversation->setPreview( (string) $preview->body );
		}

		if ( $last ) {
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

			$assignee              = $this->people->user( $source['assignee'] ?? null );
			$conversation->user_id = $assignee ? $assignee->id : null;

			// Someone it's assigned to can see it.
			if ( $assignee ) {
				People::grant( $assignee, $conversation->mailbox );
			}

			$closer                          = $this->people->user( $source['closedByUser'] ?? null );
			$conversation->closed_at         = Conversation::STATUS_CLOSED === $status ? self::date( $source['closedAt'] ?? null ) : null;
			$conversation->closed_by_user_id = Conversation::STATUS_CLOSED === $status && $closer ? $closer->id : null;

			$updated_at                    = self::date( $source['userUpdatedAt'] ?? null ) ?? ( $last ? $last->created_at : $conversation->created_at );
			$conversation->user_updated_at = $updated_at;
			$conversation->updated_at      = $updated_at;
		}

		// The Reports module works a conversation's metrics out again when live email adds a reply, closes, or reopens it.
		if ( ( $added || $conversation->isDirty( array( 'status', 'closed_at' ) ) ) && self::has_column( 'rpt_ready' ) ) {
			$conversation->rpt_ready = false;
		}

		$conversation->timestamps = false;
		$conversation->updateFolder( $conversation->mailbox );
		$conversation->save();
		$conversation->timestamps = true;
	}

	/**
	 * Parses one of the API's dates.
	 *
	 * @param mixed $date ISO 8601 date, in UTC.
	 * @return Carbon|null In the app's timezone, as core stores dates.
	 */
	private static function date( mixed $date ): ?Carbon {
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
	private static function email( mixed $email ): ?string {
		$email = is_string( $email ) ? Email::sanitizeEmail( $email ) : false;

		return $email ? $email : null;
	}

	/**
	 * Decodes what was stored as JSON.
	 *
	 * @param string|null $json JSON.
	 * @return array|null Null if nothing was stored.
	 */
	private static function decode( ?string $json ): ?array {
		return null === $json ? null : (array) json_decode( $json, true );
	}

	/**
	 * Whether core's conversations table has a column, which newer versions, or modules, add.
	 *
	 * @param string $column Column name.
	 * @return bool
	 */
	private static function has_column( string $column ): bool {
		if ( ! isset( self::$columns[ $column ] ) ) {
			self::$columns[ $column ] = Schema::hasColumn( 'conversations', $column );
		}

		return self::$columns[ $column ];
	}
}

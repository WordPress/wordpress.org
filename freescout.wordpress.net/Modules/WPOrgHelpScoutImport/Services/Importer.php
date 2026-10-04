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
use App\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgHelpScoutImport\Entities\ImportedConversation;
use Modules\WPOrgHelpScoutImport\Entities\ImportedThread;
use Modules\WPOrgHelpScoutImport\Exceptions\ApiError;

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
	 * Left it out: HelpScout names no sender for it.
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
	 * Left it out: retrying it, it's in another HelpScout mailbox now, imported with that one.
	 *
	 * @var string
	 */
	public const SKIPPED_MOVED = 'moved';

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
	 * Most bytes of images in emails' bodies copied per conversation; the rest keep their links.
	 *
	 * @var int
	 */
	private const MAX_IMAGE_BYTES_PER_CONVERSATION = 100 * 1024 * 1024;

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
	 * Custom fields, which conversations' values are kept in.
	 *
	 * @var CustomFields
	 */
	private $custom_fields;

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
	 * @param HelpScout $helpscout HelpScout API client.
	 * @param People    $people    Users and senders.
	 */
	public function __construct( HelpScout $helpscout, People $people ) {
		$this->helpscout     = $helpscout;
		$this->people        = $people;
		$this->custom_fields = new CustomFields( $helpscout );
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
	 * @throws ApiError   If HelpScout didn't give it; nothing of it is written then.
	 * @throws \Throwable If it couldn't be read or written otherwise; nothing of it is written then either.
	 */
	public function import( array $source, Mailbox $mailbox ): string {
		$helpscout_id = (int) ( $source['id'] ?? 0 );
		$imported     = $helpscout_id ? ImportedConversation::query()->where( 'helpscout_id', $helpscout_id )->first() : null;
		$conversation = $imported ? Conversation::find( $imported->conversation_id ) : null;
		$worked_on    = $conversation && $conversation->threads()->where( 'imported', false )->exists();

		// Deleted in FreeScout since: it stays deleted.
		if ( $imported && ! $conversation ) {
			return self::SKIPPED_DELETED;
		}

		if ( 'spam' === ( $source['status'] ?? '' ) ) {
			// Marked spam in HelpScout since it was imported: it's spam here too, unless agents worked on it.
			if ( $conversation && ! $worked_on && ! $conversation->isSpam() ) {
				$conversation->status     = Conversation::STATUS_SPAM;
				$conversation->timestamps = false;
				$conversation->updateFolder();
				$conversation->save();
				$conversation->timestamps = true;
				$conversation->mailbox->updateFoldersCounters();

				return self::UPDATED;
			}

			return self::SKIPPED_SPAM;
		}

		$status = self::STATUSES[ $source['status'] ?? '' ] ?? null;
		if ( ! $helpscout_id || ! $status || 'published' !== ( $source['state'] ?? '' ) ) {
			return self::SKIPPED_UNPUBLISHED;
		}

		$sender = $conversation ? $conversation->customer : $this->people->sender( $source['primaryCustomer'] ?? null );
		if ( ! $sender ) {
			return self::SKIPPED_NO_SENDER;
		}

		// HelpScout moved it to another mailbox since: it moves too. Once agents worked on it, it stays where it is,
		// and only gets HelpScout's new threads.
		$moved_from = $conversation && ! $worked_on && (int) $conversation->mailbox_id !== (int) $mailbox->id ? $conversation->mailbox : null;

		$this->downloads      = array();
		$this->download_bytes = 0;
		$this->attachments    = array();

		try {
			$listed = $this->helpscout->threads( $helpscout_id );
		} catch ( ApiError $e ) {
			// Merged into another, or deleted, since it was listed.
			if ( in_array( $e->status, array( 301, 404 ), true ) ) {
				return self::SKIPPED_GONE;
			}

			throw $e;
		}

		$threads = $this->new_threads( $listed );
		if ( ! $conversation && ! $threads ) {
			return self::SKIPPED_EMPTY;
		}

		/*
		 * Users, senders, and the mailbox's custom fields are found or created before the transaction: they stay, even
		 * if this conversation fails. Before the downloads, too: their requests can hit the rate limit, and the
		 * downloads would be repeated on the next try.
		 */
		$this->meet_people( $source, $threads );
		$this->meet_senders( $source, $sender, $threads );

		// One HelpScout moved, but agents worked on, stays in its mailbox: its values go in that mailbox's fields.
		$fields_mailbox = $conversation && ! $moved_from ? $conversation->mailbox : $mailbox;
		$fields         = CustomFields::available();
		$values         = $fields ? $this->custom_fields->values( $source, $fields_mailbox, (int) $fields_mailbox->id === (int) $mailbox->id ) : array();

		$threads = $this->read_threads( $helpscout_id, $threads );

		try {
			\DB::transaction(
				function () use ( &$conversation, $imported, $source, $mailbox, $sender, $threads, $status, $worked_on, $moved_from, $fields, $values ): void {
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

					$this->update_conversation( $conversation, $source, $status, ! $worked_on );

					$record = array(
						'helpscout_number' => (int) ( $source['number'] ?? 0 ),
						'conversation_id'  => (int) $conversation->id,
						'creator_id'       => 'user' === ( $source['createdBy']['type'] ?? '' ) ? self::person_id( $source['createdBy'] ) : null,
						'assignee_id'      => self::person_id( $source['assignee'] ?? null ),
						'closer_id'        => self::person_id( $source['closedByUser'] ?? null ),
						'tags'             => self::json( array_values( array_filter( array_map( array( Tags::class, 'name' ), (array) ( $source['tags'] ?? array() ) ) ) ) ),
						'custom_fields'    => self::json( (array) ( $source['customFields'] ?? array() ) ),
					);

					// What agents changed stays; what HelpScout changed since the last import comes across.
					if ( Tags::available() ) {
						$written_tags           = Tags::sync( (int) $conversation->id, (array) ( $source['tags'] ?? array() ), self::decode( $imported->written_tags ?? null ) );
						$record['written_tags'] = (string) json_encode( (object) $written_tags, JSON_UNESCAPED_UNICODE );
					}

					if ( $fields ) {
						// Its values in the fields of the mailbox it moved from would be left where nobody sees them.
						if ( $moved_from ) {
							CustomFields::forget( (int) $conversation->id, (int) $moved_from->id );
						}

						CustomFields::write( (int) $conversation->id, $values, self::decode( $imported->written_values ?? null ) );
						$record['written_values'] = (string) json_encode( (object) $values, JSON_UNESCAPED_UNICODE );
					}

					ImportedConversation::query()->updateOrCreate( array( 'helpscout_id' => (int) $source['id'] ), $record );
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

		if ( $moved_from ) {
			$moved_from->updateFoldersCounters();
		}

		return $imported ? self::UPDATED : self::IMPORTED;
	}

	/**
	 * Closes, and so deletes, the temporary files a conversation's attachments and images were downloaded into.
	 *
	 * @param array[] $threads Threads, from new_threads().
	 * @return void
	 */
	private function close_downloads( array $threads ): void {
		$files = array_column( array_filter( $this->downloads ), 'data' );
		foreach ( $threads as $thread ) {
			$files = array_merge( $files, array_column( $thread['files'], 'data' ) );
		}

		foreach ( $files as $file ) {
			if ( is_resource( $file ) ) {
				fclose( $file );
			}
		}

		$this->downloads = array();
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
	 * Finds or creates the senders of a conversation's emails, and gives them their HelpScout profile.
	 *
	 * @param array    $source  HelpScout conversation.
	 * @param Customer $sender  The conversation's sender.
	 * @param array[]  $threads Threads to import, from new_threads().
	 * @return void
	 *
	 * @throws ApiError If HelpScout is unavailable, refuses the app, or is rate limited.
	 */
	private function meet_senders( array $source, Customer $sender, array $threads ): void {
		$this->people->complete( $sender, $source['primaryCustomer'] ?? null );

		foreach ( $threads as $thread ) {
			if ( Thread::TYPE_CUSTOMER !== (int) $thread['fs_type'] ) {
				continue;
			}

			// Whom create_thread() credits it to.
			$person = $thread['customer'] ?? null;
			$author = $this->people->sender( $person, false );
			if ( ! $author ) {
				$person = $thread['createdBy'] ?? null;
				$author = $this->people->sender( $person, false );
			}

			if ( $author ) {
				$this->people->complete( $author, $person );
			}
		}
	}

	/**
	 * Picks the threads to import that weren't imported yet.
	 *
	 * @param array[] $threads The conversation's threads, oldest first.
	 * @return array[] Threads, each with `fs_type` set to FreeScout's.
	 */
	private function new_threads( array $threads ): array {
		$ids  = array_map( 'intval', array_column( $threads, 'id' ) );
		$done = $ids ? ImportedThread::query()->whereIn( 'helpscout_id', $ids )->pluck( 'helpscout_id' )->map( 'intval' )->all() : array();
		$new  = array();

		foreach ( $threads as $thread ) {
			$type = self::thread_type( $thread );
			if ( $type && ! in_array( (int) ( $thread['id'] ?? 0 ), $done, true ) ) {
				$thread['fs_type'] = $type;
				$new[]             = $thread;
			}
		}

		return $new;
	}

	/**
	 * Reads what threads need from HelpScout: their Message-ID, attachments, and images.
	 *
	 * @param int     $conversation_id HelpScout conversation ID.
	 * @param array[] $threads         Threads, from new_threads().
	 * @return array[] Threads, each with `message_id`, `files`, and `images` too.
	 *
	 * @throws \Throwable If HelpScout didn't give what a thread needs, like an attachment it still has; what was
	 *                    downloaded is closed then.
	 */
	private function read_threads( int $conversation_id, array $threads ): array {
		$original = Carbon::now()->subDays( self::ORIGINAL_KEPT_DAYS );
		$read     = array();

		try {
			foreach ( $threads as $thread ) {
				$read[] = $this->read_thread( $conversation_id, $thread, $original );
			}
		} catch ( \Throwable $e ) {
			$this->close_downloads( $read );

			throw $e;
		}

		return $read;
	}

	/**
	 * Reads what a thread needs from HelpScout.
	 *
	 * @param int    $conversation_id HelpScout conversation ID.
	 * @param array  $thread          Thread, from new_threads().
	 * @param Carbon $original        Oldest date HelpScout keeps emails' original source from.
	 * @return array The thread, with `message_id`, `files`, and `images`.
	 *
	 * @throws ApiError   If HelpScout didn't give an attachment it still has; the thread's files are closed then.
	 * @throws \Throwable If anything else failed while reading it; its files are closed then too.
	 */
	private function read_thread( int $conversation_id, array $thread, Carbon $original ): array {
		$type                 = (int) $thread['fs_type'];
		$thread['message_id'] = null;
		$thread['files']      = array();
		$thread['images']     = array();

		try {
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

				try {
					$data = $this->helpscout->attachment( $conversation_id, (int) ( $attachment['id'] ?? 0 ) );
				} catch ( ApiError $e ) {
					// Deleted from HelpScout since: the rest of the thread is imported without it.
					if ( 404 === $e->status ) {
						continue;
					}

					throw $e;
				}

				$thread['files'][] = array(
					'name' => (string) ( $attachment['filename'] ?? '' ),
					'mime' => (string) ( $attachment['mimeType'] ?? 'application/octet-stream' ),
					'data' => $data,
				);
			}

			$thread['images'] = $this->images( (string) ( $thread['body'] ?? '' ) );
		} catch ( \Throwable $e ) {
			$this->close_downloads( array( $thread ) );

			throw $e;
		}

		return $thread;
	}

	/**
	 * Copies the images HelpScout hosts in a saved reply's text, like images pasted into the editor: as embedded
	 * attachments of no thread.
	 *
	 * An image that can't be downloaded keeps its link. One copied already, whose copy is still there, isn't copied
	 * again.
	 *
	 * @param string $text    Saved reply's text.
	 * @param int    $user_id FreeScout user who added them.
	 * @param int[]  $copies  Attachment IDs of copies made before, by the image's `src` in HelpScout's text.
	 * @return array The text, linking to the copies; the new copies, as a collection of attachments, whose files go
	 *               with them if the text isn't kept; and all copies' attachment IDs, by the image's `src`.
	 *
	 * @throws \Throwable If a copy couldn't be saved; the new ones are deleted then.
	 */
	public function copy_images( string $text, int $user_id, array $copies = array() ): array {
		$this->downloads      = array();
		$this->download_bytes = 0;
		$this->attachments    = array();
		$copied               = array();

		try {
			foreach ( $copies as $src => $attachment_id ) {
				$src        = (string) $src;
				$attachment = '' !== $src && str_contains( $text, $src ) ? Attachment::query()->where( 'id', (int) $attachment_id )->whereNull( 'thread_id' )->first() : null;
				if ( $attachment ) {
					$text           = str_replace( $src, $attachment->url(), $text );
					$copied[ $src ] = (int) $attachment->id;
				}
			}

			foreach ( $this->images( $text ) as $src => $image ) {
				$attachment = $this->attach( $image, null, $user_id, true );
				if ( $attachment ) {
					$text           = str_replace( $src, $attachment->url(), $text );
					$copied[ $src ] = (int) $attachment->id;
				}
			}

			return array( $text, collect( $this->attachments ), $copied );
		} catch ( \Throwable $e ) {
			Attachment::deleteForever( collect( $this->attachments ) );

			throw $e;
		} finally {
			$this->attachments = array();
			$this->close_downloads( array() );
		}
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
	 * Downloads an image HelpScout hosts into a temporary file, unless the conversation's images are too large already.
	 *
	 * @param string $url Image URL.
	 * @return array|null With `name`, `mime`, and `data`, the file; null if it isn't an image, or wasn't downloaded.
	 */
	private function download_image( string $url ): ?array {
		if ( $this->download_bytes >= self::MAX_IMAGE_BYTES_PER_CONVERSATION ) {
			return null;
		}

		$file = $this->helpscout->image( $url );
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
	 * @param array    $file      With `name`, `mime`, and `data`, the temporary file.
	 * @param int|null $thread_id FreeScout thread ID, or null for an image in a saved reply.
	 * @param int      $user_id   FreeScout user who added it, or 0 for a sender.
	 * @param bool     $embedded  Whether it's an image in the body.
	 * @return Attachment|null
	 */
	private function attach( array $file, ?int $thread_id, int $user_id, bool $embedded ): ?Attachment {
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
	 * @param resource $file Temporary file.
	 * @return string
	 */
	private static function path( $file ): string {
		return (string) stream_get_meta_data( $file )['uri'];
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
		$conversation->customer_email = self::email( $source['primaryCustomer']['email'] ?? null ) ?? self::email( $sender->getMainEmail() );
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
	 * @return void
	 */
	private function create_thread( Conversation $conversation, array $source, Customer $sender ): void {
		$type        = (int) $source['fs_type'];
		$by_customer = Thread::TYPE_CUSTOMER === $type;
		// Who wrote it, by their email; without one, it's the conversation's sender, who may have none either.
		$author     = $by_customer ? ( $this->people->sender( $source['customer'] ?? null, false ) ?? $this->people->sender( $source['createdBy'] ?? null, false ) ?? $sender ) : null;
		$user       = $by_customer ? null : $this->people->user( $source['createdBy'] ?? null );
		$created_at = self::date( $source['createdAt'] ?? null ) ?? Carbon::now();
		$message_id = $source['message_id'];

		if ( $message_id ) {
			$existing = Thread::query()->where( 'message_id', $message_id )->first( array( 'id', 'conversation_id' ) );
			if ( $existing && (int) $existing->conversation_id === (int) $conversation->id ) {
				// FreeScout's own copy: it isn't credited to anyone from HelpScout.
				$this->remember_thread( array( 'id' => $source['id'] ), (int) $existing->id, true );

				return;
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
			$thread->from                   = $author ? self::email( $author->getMainEmail() ) : null;
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

		$thread_id = (int) Thread::query()->insertGetId( $thread->getAttributes() );

		foreach ( $source['files'] as $file ) {
			$this->attach( $file, $thread_id, (int) $thread->created_by_user_id, false );
		}

		// Like an email's inline images when FreeScout fetches it: embedded, and linked from the body.
		$body = (string) $thread->body;
		foreach ( $source['images'] as $src => $image ) {
			$attachment = $this->attach( $image, $thread_id, (int) $thread->created_by_user_id, true );
			if ( $attachment ) {
				$body = str_replace( $src, $attachment->url(), $body );
			}
		}
		if ( $body !== $thread->body ) {
			Thread::query()->whereKey( $thread_id )->update( array( 'body' => $body ) );
		}

		$this->remember_thread( $source, $thread_id, $by_customer );
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
				'helpscout_id'          => (int) $source['id'],
				'thread_id'             => $thread_id,
				'helpscout_user_id'     => $by_customer ? null : $this->people->remember( $source['createdBy'] ?? null ),
				'helpscout_assignee_id' => self::person_id( $source['assignedTo'] ?? null ),
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

			// Someone it's assigned to can see it.
			if ( $assignee && User::TYPE_USER === (int) $assignee->type ) {
				People::grant( $assignee, $conversation->mailbox );
			}

			$closer                          = $this->people->user( $source['closedByUser'] ?? null );
			$conversation->closed_at         = Conversation::STATUS_CLOSED === $status ? self::date( $source['closedAt'] ?? null ) : null;
			$conversation->closed_by_user_id = Conversation::STATUS_CLOSED === $status && $closer ? $closer->id : null;

			$updated_at                    = self::date( $source['userUpdatedAt'] ?? null ) ?? ( $last ? $last->created_at : $conversation->created_at );
			$conversation->user_updated_at = $updated_at;
			$conversation->updated_at      = $updated_at;
		}

		$conversation->timestamps = false;
		$conversation->updateFolder( $conversation->mailbox );
		$conversation->save();
		$conversation->timestamps = true;
	}

	/**
	 * FreeScout's thread type for a HelpScout thread.
	 *
	 * @param array $thread HelpScout thread.
	 * @return int|null Null to leave it out: line items, drafts, and replies held back for review.
	 */
	private static function thread_type( array $thread ): ?int {
		$type  = (string) ( $thread['type'] ?? '' );
		$state = (string) ( $thread['state'] ?? '' );

		// Drafts and replies held back for review were never sent.
		if ( ! in_array( $state, array( 'published', 'hidden', 'bounced' ), true ) ) {
			return null;
		}

		// A reply hidden from what the sender is sent, or that never reached them, becomes a note: it isn't sent either.
		if ( 'published' !== $state && 'message' === $type ) {
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
	 * A HelpScout user's or team's ID, for crediting what's imported for them to someone else later.
	 *
	 * @param mixed $person HelpScout person object.
	 * @return int|null Null if it isn't a user or team.
	 */
	private static function person_id( $person ): ?int {
		$id = is_array( $person ) && in_array( $person['type'] ?? '', array( 'user', 'system_user', 'team' ), true ) ? (int) ( $person['id'] ?? 0 ) : 0;

		return $id > 0 ? $id : null;
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
	 * Decodes what was stored as JSON.
	 *
	 * @param string|null $json JSON.
	 * @return array|null Null if nothing was stored.
	 */
	private static function decode( ?string $json ): ?array {
		return null === $json ? null : (array) json_decode( $json, true );
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

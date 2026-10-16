<?php
/**
 * WPOrgPluginReview service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Providers;

use App\Conversation;
use App\Mailbox;
use App\Thread;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgPluginReview\Console\IndexReviews;
use Modules\WPOrgPluginReview\Services\FlagReplies;
use Modules\WPOrgPluginReview\Services\OpenOrder;
use Modules\WPOrgPluginReview\Services\Review;
use Modules\WPOrgPluginReview\Services\Reviewers;
use Modules\WPOrgPluginReview\Services\Subject;
use Modules\WPOrgPluginReview\Services\Worklist;

/**
 * Shows a conversation's latest plugin review in its sidebar, and keeps the index of review emails as threads change;
 * marks the plugin directory's subjects; adds the My Reviews and My Cases folders to the plugins team's mailbox, and to its members' profiles how many
 * conversations those open at a time; and to their header, a button that copies the download URLs of the plugins in
 * review they have open.
 */
final class WPOrgPluginReviewServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgpluginreview';

	/**
	 * Priority of the sidebar panel, before the WordPress.org panels.
	 *
	 * @var int
	 */
	private const PANEL_PRIORITY = 15;

	/**
	 * Priority of the worklist folders' hooks: after the Teams and Custom Folders modules', which sort their folders.
	 *
	 * @var int
	 */
	private const FOLDERS_PRIORITY = 30;

	/**
	 * How many of the sender's other conversations get their subjects marked in the sidebar: core shows the latest five.
	 *
	 * @var int
	 */
	private const PREVIOUS_LIMIT = 40;

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		try {
			$this->mergeConfigFrom( __DIR__ . '/../Config/config.php', self::ALIAS );
			$this->loadViewsFrom( __DIR__ . '/../Resources/views', self::ALIAS );
			$this->loadRoutesFrom( __DIR__ . '/../Http/routes.php' );
			$this->loadMigrationsFrom( __DIR__ . '/../Database/Migrations' );
			$this->commands( array( IndexReviews::class ) );

			$this->register_hooks();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not boot: ' . $e->getMessage() );
		}
	}

	/**
	 * Registers the Eventy hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		\Eventy::addFilter(
			'stylesheets',
			static function ( mixed $styles = array() ): mixed {
				if ( is_array( $styles ) ) {
					$styles[] = \Module::getPublicPath( self::ALIAS ) . '/css/review.css';
				}

				return $styles;
			}
		);

		\Eventy::addFilter(
			'javascripts',
			static function ( mixed $javascripts = array() ): mixed {
				if ( is_array( $javascripts ) ) {
					$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/review.js';
					$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/downloads.js';
				}

				return $javascripts;
			}
		);

		/*
		 * Callbacks take what core passes untyped, and check it: a TypeError in a hook isn't caught, and would fail the
		 * request.
		 */
		\Eventy::addAction(
			'thread.created',
			static function ( mixed $thread = null ): void {
				if ( $thread instanceof Thread ) {
					Review::index_thread( $thread );
				}
			}
		);

		/*
		 * Agents' replies are saved as drafts first, and published by an update; merging moves threads into the other
		 * conversation by updating them too. Other updates, like being opened, change nothing.
		 */
		\Eventy::addAction(
			'thread.updated',
			static function ( mixed $thread = null ): void {
				// Core fires it before the model takes its changes in, while they're still dirty.
				if ( $thread instanceof Thread && $thread->isDirty( Review::INDEXED_FIELDS ) ) {
					Review::index_thread( $thread );
				}
			}
		);

		\Eventy::addAction(
			'thread.deleting',
			static function ( mixed $thread = null ): void {
				if ( $thread instanceof Thread ) {
					Review::index_thread( $thread, true );
				}
			}
		);

		// Moving a conversation to another mailbox makes its reviews count, or not, without changing them.
		\Eventy::addAction(
			'conversation.moved',
			static function ( mixed $conversation = null ): void {
				if ( $conversation instanceof Conversation ) {
					Review::reindex( $conversation );
				}
			}
		);

		// Core deletes conversations' threads with a query, which their hooks don't see.
		\Eventy::addAction(
			'conversation.deleting',
			static function ( mixed $conversation = null ): void {
				if ( $conversation instanceof Conversation ) {
					Review::forget( array( (int) $conversation->id ) );
				}
			}
		);

		\Eventy::addAction(
			'conversations.before_delete_forever',
			static function ( mixed $conversation_ids = null ): void {
				if ( is_array( $conversation_ids ) ) {
					Review::forget( $conversation_ids );
				}
			}
		);

		\Eventy::addAction(
			'conversation.after_customer_sidebar',
			static function ( mixed $conversation = null ): void {
				if ( $conversation instanceof Conversation ) {
					self::render_panel( $conversation );
				}
			},
			self::PANEL_PRIORITY
		);

		self::register_subject_hooks();
		self::register_folder_hooks();
		self::register_profile_hooks();

		// The page's script shows it once there are URLs.
		\Eventy::addAction(
			'menu.append',
			static function (): void {
				try {
					if ( app( Reviewers::class )->includes( auth()->user() ) ) {
						echo \View::make( self::ALIAS . '::downloads' )->render();
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not add the download URLs button to the header: ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * Shows the plugin directory's subjects marked, like "🔌 My Plugin", for the same agents as the panel: in the
	 * conversation, its tab's title, the sender's previous conversations, and lists of conversations. The subject itself
	 * shows on hover, in the subject's editor, and when copied.
	 *
	 * @return void
	 */
	private static function register_subject_hooks(): void {
		// Lists ask for each row, and the answer doesn't change within a request.
		$mailbox_ids = null;
		$marks       = static function ( mixed $conversation ) use ( &$mailbox_ids ): bool {
			if ( ! $conversation instanceof Conversation ) {
				return false;
			}

			if ( null === $mailbox_ids ) {
				$mailbox_ids = app( Reviewers::class )->includes( auth()->user() ) ? Review::plugins_mailbox_ids() : array();
			}

			return in_array( (int) $conversation->mailbox_id, $mailbox_ids, true );
		};

		\Eventy::addAction(
			'conversation.after_subject',
			static function ( mixed $conversation = null ) use ( $marks ): void {
				try {
					if ( $marks( $conversation ) ) {
						echo \View::make(
							self::ALIAS . '::subjects',
							array(
								'conversation_id' => (int) $conversation->id,
								'number'          => (int) $conversation->number,
								'subject'         => (string) $conversation->getSubject(),
								'short_subject'   => Subject::short( (string) $conversation->subject ),
								'subjects'        => self::marked_subjects( $conversation ),
							)
						)->render();
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not mark the subjects of conversation ' . ( $conversation->id ?? '' ) . ': ' . $e->getMessage() );
				}
			}
		);

		// The subject goes in between, hidden: it's what the row is read as, and what FreeScout's scripts find there.
		\Eventy::addAction(
			'conversations_table.before_subject',
			static function ( mixed $conversation = null ) use ( $marks ): void {
				try {
					$marked = $marks( $conversation ) ? Subject::marked( (string) $conversation->subject ) : '';
					if ( '' !== $marked ) {
						$subject = e( $conversation->getSubject() );
						echo '<span class="wporg-subject" data-wporg-subject="' . $subject . '" title="' . $subject . '">' . e( $marked ) . '</span><span hidden>';
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not mark the subject of conversation ' . ( $conversation->id ?? '' ) . ': ' . $e->getMessage() );
				}
			}
		);

		\Eventy::addAction(
			'conversations_table.after_subject',
			static function ( mixed $conversation = null ) use ( $marks ): void {
				try {
					if ( $marks( $conversation ) && '' !== Subject::marked( (string) $conversation->subject ) ) {
						echo '</span>';
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not mark the subject of conversation ' . ( $conversation->id ?? '' ) . ': ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * The marked subjects of a conversation and of its sender's latest others in its mailbox, which its sidebar lists.
	 *
	 * @param Conversation $conversation Conversation.
	 * @return array[] Subject, and marked subject, by conversation ID; only those with a mark.
	 */
	private static function marked_subjects( Conversation $conversation ): array {
		$conversations = array( $conversation );
		if ( $conversation->customer_id ) {
			$previous      = Conversation::where( 'mailbox_id', $conversation->mailbox_id )
				->where( 'customer_id', $conversation->customer_id )
				->where( 'id', '<>', $conversation->id )
				->orderBy( 'created_at', 'desc' )
				->limit( self::PREVIOUS_LIMIT )
				->get();
			$conversations = array_merge( $conversations, $previous->all() );
		}

		$subjects = array();
		foreach ( $conversations as $item ) {
			$marked = Subject::marked( (string) $item->subject );
			if ( '' !== $marked ) {
				$subjects[ (int) $item->id ] = array(
					'subject' => (string) $item->getSubject(),
					'marked'  => $marked,
				);
			}
		}

		return $subjects;
	}

	/**
	 * Lets members of the plugins team's teams choose in their profile how many conversations the worklists' Open
	 * buttons open at a time.
	 *
	 * @return void
	 */
	private static function register_profile_hooks(): void {
		\Eventy::addAction(
			'user.edit.before_photo',
			static function ( mixed $user = null ): void {
				try {
					if ( $user instanceof User && app( Reviewers::class )->includes( $user ) ) {
						echo \View::make(
							self::ALIAS . '::profile',
							array(
								'limit' => OpenOrder::limit( $user ),
								'max'   => OpenOrder::MAX_LIMIT,
							)
						)->render();
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not show how many conversations to open in a profile: ' . $e->getMessage() );
				}
			}
		);

		\Eventy::addFilter(
			'user.save_profile',
			static function ( mixed $user = null, mixed $request = null ): mixed {
				try {
					if ( $user instanceof User && $request instanceof Request && $request->has( 'wporgpluginreview_open_limit' ) && app( Reviewers::class )->includes( $user ) ) {
						OpenOrder::set_limit( $user, $request->input( 'wporgpluginreview_open_limit' ) );
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not save how many conversations to open in a profile: ' . $e->getMessage() );
				}

				return $user;
			},
			20,
			2
		);

		\Eventy::addAction(
			'user.deleted',
			static function ( mixed $user = null ): void {
				try {
					if ( $user instanceof User ) {
						OpenOrder::forget_limit( (int) $user->id );
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not forget how many conversations a deleted user opens: ' . $e->getMessage() );
				}
			}
		);
	}

	/**
	 * Adds the My Reviews and My Cases folders to the plugins team's mailbox, for its members.
	 *
	 * They aren't stored: they're added to the mailbox's folders as they're listed, after the Teams and Custom Folders
	 * modules have sorted theirs, and link to their own pages. Their counts are kept in the cache until a change may
	 * affect them.
	 *
	 * @return void
	 */
	private static function register_folder_hooks(): void {
		\Eventy::addFilter(
			'mailbox.folders',
			static function ( mixed $folders = null, mixed $mailbox = null ): mixed {
				if ( ! $folders instanceof Collection || ! $mailbox instanceof Mailbox ) {
					return $folders;
				}

				try {
					$user = auth()->user();
					if ( ! Review::is_plugins_mailbox( $mailbox ) || ! app( Reviewers::class )->includes( $user ) ) {
						return $folders;
					}

					return Worklist::add_folders( $folders, $mailbox, $user );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not add the worklist folders to mailbox ' . $mailbox->id . ': ' . $e->getMessage() );

					return $folders;
				}
			},
			self::FOLDERS_PRIORITY,
			2
		);

		\Eventy::addFilter(
			'folder.type_name',
			static function ( mixed $name = '', mixed $folder = null ): mixed {
				$worklist = Worklist::of_folder( $folder );

				return $worklist ? $worklist->name() : $name;
			},
			self::FOLDERS_PRIORITY,
			2
		);

		\Eventy::addFilter(
			'folder.type_icon',
			static function ( mixed $icon = '', mixed $folder = null ): mixed {
				$worklist = Worklist::of_folder( $folder );

				return $worklist ? $worklist->icon : $icon;
			},
			self::FOLDERS_PRIORITY,
			2
		);

		\Eventy::addFilter(
			'folder.url',
			static function ( mixed $url = '', mixed $mailbox_id = null, mixed $folder = null ): mixed {
				$worklist = Worklist::of_folder( $folder );

				return $worklist && is_numeric( $mailbox_id ) ? $worklist->url( (int) $mailbox_id ) : $url;
			},
			self::FOLDERS_PRIORITY,
			3
		);

		// The folders' counts change with agents' replies; only those published, removed, or moved.
		foreach ( array( 'thread.created', 'thread.updated', 'thread.deleting' ) as $hook ) {
			\Eventy::addAction(
				$hook,
				static function ( mixed $thread = null ) use ( $hook ): void {
					if ( ! $thread instanceof Thread || Thread::TYPE_MESSAGE !== (int) $thread->type || ! $thread->created_by_user_id ) {
						return;
					}

					// Core fires it before the model takes its changes in, while they're still dirty.
					if ( 'thread.updated' === $hook && ! $thread->isDirty( array( 'state', 'type', 'conversation_id' ) ) ) {
						return;
					}

					self::forget_counts( $thread->conversation );
				}
			);
		}

		/*
		 * And with conversations that open, close, change hands, or are deleted, merged, or imported. An author's reply
		 * makes a conversation active without core's status_changed.
		 */
		$hooks = array(
			'conversation.customer_replied',
			'conversation.status_changed',
			'conversation.user_changed',
			'conversation.state_changed',
			'conversation.merged',
			'conversation.deleting',
			'wporghelpscoutimport.conversation_imported',
		);
		foreach ( $hooks as $hook ) {
			\Eventy::addAction(
				$hook,
				static function ( mixed $conversation = null ): void {
					self::forget_counts( $conversation );
				}
			);
		}

		\Eventy::addAction(
			'conversation.moved',
			static function ( mixed $conversation = null, mixed $user = null, mixed $prev_mailbox = null ): void {
				self::forget_counts( $conversation );

				if ( $prev_mailbox instanceof Mailbox ) {
					Worklist::forget_counts( (int) $prev_mailbox->id );
				}
			},
			20,
			3
		);
	}

	/**
	 * Forgets the worklists' counts of a conversation's mailbox.
	 *
	 * @param mixed $conversation Conversation.
	 * @return void
	 */
	private static function forget_counts( mixed $conversation ): void {
		if ( $conversation instanceof Conversation && $conversation->mailbox_id ) {
			Worklist::forget_counts( (int) $conversation->mailbox_id );
		}
	}

	/**
	 * Renders the latest review of a conversation that has one, for the plugins team.
	 *
	 * @param Conversation $conversation Conversation being viewed.
	 * @return void
	 */
	private static function render_panel( Conversation $conversation ): void {
		try {
			// The mailbox is known already; the teams may take a query.
			if ( ! Review::is_plugins_mailbox( $conversation->mailbox ) || ! app( Reviewers::class )->includes( auth()->user() ) ) {
				return;
			}

			$review = Review::latest( $conversation );
			if ( ! $review ) {
				return;
			}

			echo \View::make(
				self::ALIAS . '::panel',
				array(
					'review'          => $review,
					'replies'         => FlagReplies::REPLIES,
					'conversation_id' => (int) $conversation->id,
				)
			)->render();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not render the review of conversation ' . $conversation->id . ': ' . $e->getMessage() );
		}
	}
}

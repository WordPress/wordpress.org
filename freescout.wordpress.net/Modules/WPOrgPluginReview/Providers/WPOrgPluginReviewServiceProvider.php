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
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgPluginReview\Console\IndexReviews;
use Modules\WPOrgPluginReview\Services\FlagReplies;
use Modules\WPOrgPluginReview\Services\MyReviews;
use Modules\WPOrgPluginReview\Services\Review;
use Modules\WPOrgPluginReview\Services\Reviewers;
use Modules\WPOrgPluginReview\Services\Subject;

/**
 * Shows a conversation's latest plugin review in its sidebar, and keeps the index of review emails as threads change;
 * adds the My Reviews folder to the plugins team's mailbox.
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
	 * Priority of the My Reviews folder's hooks: after the Teams and Custom Folders modules', which sort their folders.
	 *
	 * @var int
	 */
	private const FOLDERS_PRIORITY = 30;

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

		self::register_folder_hooks();
	}

	/**
	 * Adds the My Reviews folder to the plugins team's mailbox, for reviewers.
	 *
	 * It isn't stored: it's added to the mailbox's folders as they're listed, after the Teams and Custom Folders
	 * modules have sorted theirs, and links to its own page. Its count is kept in the cache until a change may affect it.
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

					return MyReviews::add_folder( $folders, $mailbox, $user );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgPluginReview] Could not add the My Reviews folder to mailbox ' . $mailbox->id . ': ' . $e->getMessage() );

					return $folders;
				}
			},
			self::FOLDERS_PRIORITY,
			2
		);

		\Eventy::addFilter(
			'folder.type_name',
			static function ( mixed $name = '', mixed $folder = null ): mixed {
				return MyReviews::is_folder( $folder ) ? __( 'My Reviews' ) : $name;
			},
			self::FOLDERS_PRIORITY,
			2
		);

		\Eventy::addFilter(
			'folder.type_icon',
			static function ( mixed $icon = '', mixed $folder = null ): mixed {
				return MyReviews::is_folder( $folder ) ? 'tasks' : $icon;
			},
			self::FOLDERS_PRIORITY,
			2
		);

		\Eventy::addFilter(
			'folder.url',
			static function ( mixed $url = '', mixed $mailbox_id = null, mixed $folder = null ): mixed {
				if ( ! MyReviews::is_folder( $folder ) || ! is_numeric( $mailbox_id ) ) {
					return $url;
				}

				return route( 'wporgpluginreview.my_reviews', array( 'mailbox_id' => (int) $mailbox_id ) );
			},
			self::FOLDERS_PRIORITY,
			3
		);

		// The folder's counts change with agents' replies; only those published, removed, or moved.
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

		// And with conversations that open, close, change hands, or are deleted, merged, or imported.
		$hooks = array(
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
					MyReviews::forget_counts( (int) $prev_mailbox->id );
				}
			},
			20,
			3
		);
	}

	/**
	 * Forgets the My Reviews counts of a conversation's mailbox.
	 *
	 * @param mixed $conversation Conversation.
	 * @return void
	 */
	private static function forget_counts( mixed $conversation ): void {
		if ( $conversation instanceof Conversation && $conversation->mailbox_id ) {
			MyReviews::forget_counts( (int) $conversation->mailbox_id );
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
					'subject'         => (string) $conversation->getSubject(),
					'short_subject'   => Subject::short( (string) $conversation->subject ),
				)
			)->render();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not render the review of conversation ' . $conversation->id . ': ' . $e->getMessage() );
		}
	}
}

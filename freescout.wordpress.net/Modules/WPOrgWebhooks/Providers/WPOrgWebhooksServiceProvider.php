<?php
/**
 * WPOrgWebhooks service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Providers;

use App\Conversation;
use App\Thread;
use App\User;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgWebhooks\Jobs\SendEvent;
use Modules\WPOrgWebhooks\Services\Client;
use Modules\WPOrgWebhooks\Services\EventPayload;

/**
 * Forwards conversation events to WordPress.org, named after the FreeScout hook that fired.
 */
final class WPOrgWebhooksServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgwebhooks';

	/**
	 * How long a counted reply is remembered, in minutes; longer than any queue backlog.
	 *
	 * @var int
	 */
	private const SENT_CACHE_MINUTES = 24 * 60;

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hide_secret_from_debug_pages();
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->mergeConfigFrom( __DIR__ . '/../Config/config.php', self::ALIAS );

		$this->register_hooks();
	}

	/**
	 * Keeps the signing secret off the Whoops error pages that APP_DEBUG shows, which list the environment.
	 *
	 * @return void
	 */
	private function hide_secret_from_debug_pages(): void {
		$blacklist = (array) config( 'app.debug_blacklist', array() );

		foreach ( array( '_ENV', '_SERVER' ) as $key ) {
			$blacklist[ $key ] = (array) ( $blacklist[ $key ] ?? array() );

			if ( ! in_array( 'WPORG_API_SECRET', $blacklist[ $key ], true ) ) {
				$blacklist[ $key ][] = 'WPORG_API_SECRET';
			}
		}

		config( array( 'app.debug_blacklist' => $blacklist ) );
	}

	/**
	 * Registers the Eventy hooks.
	 *
	 * Callbacks take untyped, optional arguments and resolve them inside forward(), so a change in what core passes
	 * can't throw where core doesn't catch it.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		\Eventy::addAction(
			'conversation.created_by_customer',
			static function ( $conversation = null ): void {
				self::forward(
					'conversation.created_by_customer',
					static function () use ( $conversation ): array {
						return array( $conversation, null );
					}
				);
			}
		);

		\Eventy::addAction(
			'conversation.created_by_user',
			static function ( $conversation = null, $thread = null ): void {
				self::forward(
					'conversation.created_by_user',
					static function () use ( $conversation, $thread ): ?array {
						return self::sent( $thread ) ? array( $conversation, $thread->created_by_user ) : null;
					}
				);
			},
			20,
			2
		);

		\Eventy::addAction(
			'conversation.customer_replied',
			static function ( $conversation = null ): void {
				self::forward(
					'conversation.customer_replied',
					static function () use ( $conversation ): array {
						return array( $conversation, null );
					}
				);
			}
		);

		\Eventy::addAction(
			'conversation.user_replied',
			static function ( $conversation = null, $thread = null ): void {
				self::forward(
					'conversation.user_replied',
					static function () use ( $conversation, $thread ): ?array {
						return self::sent( $thread ) ? array( $conversation, $thread->created_by_user ) : null;
					}
				);
			},
			20,
			2
		);

		foreach ( array( 'conversation.user_changed', 'conversation.status_changed', 'conversation.moved' ) as $event ) {
			\Eventy::addAction(
				$event,
				static function ( $conversation = null, $user = null ) use ( $event ): void {
					self::forward(
						$event,
						static function () use ( $conversation, $user ): array {
							return array( $conversation, $user );
						}
					);
				},
				20,
				2
			);
		}

		\Eventy::addAction(
			'conversation.merged',
			static function ( $conversation = null, $second = null, $user = null ): void {
				self::forward(
					'conversation.merged',
					static function () use ( $conversation, $user ): array {
						return array( $conversation, $user );
					}
				);
			},
			20,
			3
		);

		// Moving to the trash, which is how agents delete; deleting forever doesn't fire it.
		\Eventy::addAction(
			'conversation.deleted',
			static function ( $conversation = null, $user = null ): void {
				self::forward(
					'conversation.deleted',
					static function () use ( $conversation, $user ): ?array {
						return self::merged_away( $conversation ) ? null : array( $conversation, $user );
					}
				);
			},
			20,
			2
		);
	}

	/**
	 * Whether a reply was sent and hasn't been counted yet.
	 *
	 * Core fires the reply hooks after the undo window whether or not the reply was undone; undoing turns it back
	 * into a draft. Undoing and sending it again within the window fires them twice for the same, published reply.
	 *
	 * @param Thread $thread Reply, as it was sent: core queues a copy, not a reference.
	 * @return bool
	 */
	private static function sent( Thread $thread ): bool {
		$thread = $thread->fresh();

		return $thread
			&& Thread::STATE_PUBLISHED === (int) $thread->state
			&& \Cache::add( 'wporgwebhooks.sent.' . $thread->id, true, self::SENT_CACHE_MINUTES );
	}

	/**
	 * Whether a conversation was deleted because it was merged into another, which is counted as the merge.
	 *
	 * @param Conversation $conversation Deleted conversation.
	 * @return bool
	 */
	private static function merged_away( Conversation $conversation ): bool {
		// Merging moves the threads away, then adds a merge note and a deletion note.
		$before_deletion = $conversation->threads()->orderBy( 'id', 'desc' )->skip( 1 )->first();

		return $before_deletion
			&& Thread::ACTION_TYPE_MERGED === (int) $before_deletion->action_type
			&& $before_deletion->getMeta( Thread::META_MERGED_INTO_CONV );
	}

	/**
	 * Queues an event for delivery.
	 *
	 * Never throws: a failure here must not interrupt the core action that fired the event.
	 *
	 * @param string   $event   Event name.
	 * @param callable $resolve Returns the conversation and the agent who caused the event (or null), or null to skip it.
	 * @return void
	 */
	private static function forward( string $event, callable $resolve ): void {
		try {
			if ( ! Client::from_config()->is_configured() ) {
				return;
			}

			$args = $resolve();
			if ( ! $args ) {
				return;
			}

			list( $conversation, $agent ) = $args;
			SendEvent::dispatch( EventPayload::build( $event, $conversation, $agent instanceof User ? $agent : null ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgWebhooks] Could not queue ' . $event . ': ' . $e->getMessage() );
		}
	}
}

<?php
/**
 * Sidebar panel controller.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Http\Controllers;

use App\Conversation;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\WPOrgSidebar\Jobs\SyncSenderAvatar;
use Modules\WPOrgSidebar\Services\Client;
use Modules\WPOrgSidebar\Services\ConversationPayload;
use Modules\WPOrgSidebar\Services\Panels;

/**
 * Proxies sidebar panel requests to api.wordpress.org.
 */
final class PanelController extends Controller {

	/**
	 * Timeout for panel requests, in seconds; the agent is waiting on them.
	 *
	 * @var int
	 */
	private const TIMEOUT = 5;

	/**
	 * How often a sender's avatar is saved again, in minutes, in case it changed on WordPress.org.
	 *
	 * @var int
	 */
	private const AVATAR_MINUTES = 24 * 60;

	/**
	 * How long a panel's content is reused, in minutes.
	 *
	 * @var int
	 */
	private const CACHE_MINUTES = 1;

	/**
	 * How many panels an agent may load a minute; each holds a worker while api.wordpress.org answers.
	 *
	 * @var int
	 */
	public const MAX_PER_MINUTE = 120;

	/**
	 * Returns the content of one panel, as blocks sidebar.js builds it from.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $panel           Panel ID.
	 * @return JsonResponse
	 */
	public function show( int $conversation_id, string $panel ): JsonResponse {
		self::throttle();

		$panels = Panels::all();
		if ( empty( $panels[ $panel ]['endpoint'] ) ) {
			abort( 404 );
		}

		$conversation = self::conversation( $conversation_id );

		// A panel switched off for the mailbox isn't sent its conversations.
		if ( ! Panels::shows( $panel, (int) $conversation->mailbox_id ) ) {
			abort( 404 );
		}

		// The account a bounce or Slack notification names, instead of the sender's, once the agent asks for it.
		$related = (bool) request()->query( 'related' );

		// A new message changes updated_at, and so the key.
		$key = implode( '.', array( 'wporgsidebar.panel', $conversation->id, $panel, (int) $related, strtotime( (string) $conversation->updated_at ) ) );

		try {
			$response = \Cache::remember(
				$key,
				self::CACHE_MINUTES,
				static function () use ( $conversation, $panels, $panel, $related ): array {
					$payload = ConversationPayload::build(
						$conversation,
						notes: ! empty( $panels[ $panel ]['notes'] ),
						attachments: $related || ! empty( $panels[ $panel ]['attachments'] )
					);
					if ( $related ) {
						$payload['related'] = true;
					}

					return Client::from_config( self::TIMEOUT )->post( (string) $panels[ $panel ]['endpoint'], $payload );
				}
			);
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not load panel ' . $panel . ': ' . $e->getMessage() );

			return response()->json( array( 'blocks' => array() ), 502 );
		}

		$body = array( 'blocks' => array_values( (array) ( $response['blocks'] ?? array() ) ) );
		if ( self::sync_sender_avatar( $conversation, (string) ( $response['avatar_url'] ?? '' ) ) ) {
			// The page was drawn without a photo: it asks here until the queue has saved one.
			$body['sender_photo'] = route( 'wporgsidebar.sender_photo', array( 'conversation_id' => $conversation->id ) );
		}

		return response()->json( $body );
	}

	/**
	 * Returns the sender's photo, once the queue has saved their WordPress.org avatar.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return JsonResponse The photo's URL, null until there is one; whether the job is still on its way; and the
	 *                      sender's page, which their messages link to.
	 */
	public function sender_photo( int $conversation_id ): JsonResponse {
		self::throttle( 'photos' );

		$sender = self::conversation( $conversation_id )->customer;
		if ( ! $sender ) {
			abort( 404 );
		}

		return response()->json(
			array(
				'url'     => $sender->photo_url ? $sender->getPhotoUrl() : null,
				// False once the job ran without saving one, like for an account without an avatar.
				'pending' => \Cache::has( SyncSenderAvatar::pending_key( (int) $sender->id ) ),
				'sender'  => $sender->url(),
			)
		);
	}

	/**
	 * Counts a request against the agent's limit, and refuses it once they're over.
	 *
	 * Not the throttle middleware: in this Laravel, it shares a counter with core's upload limit.
	 *
	 * @param string $counter Counter, so checking for photos doesn't use up the limit on panels.
	 * @return void
	 */
	private static function throttle( string $counter = 'panels' ): void {
		$limiter = app( RateLimiter::class );
		$key     = 'wporgsidebar.' . $counter . '.' . auth()->id();
		if ( $limiter->tooManyAttempts( $key, self::MAX_PER_MINUTE ) ) {
			abort( 429 );
		}
		$limiter->hit( $key );
	}

	/**
	 * Finds a conversation the agent may read.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return Conversation
	 */
	private static function conversation( int $conversation_id ): Conversation {
		$conversation = Conversation::findOrFail( $conversation_id );
		if ( ! auth()->user()->can( 'view', $conversation ) ) {
			abort( 403 );
		}

		return $conversation;
	}

	/**
	 * Queues saving the avatar of the sender's WordPress.org account as their photo.
	 *
	 * Never throws: the panel is what the agent is waiting for.
	 *
	 * @param Conversation $conversation Conversation the panel is for.
	 * @param string       $avatar_url   Avatar URL the profile panel sent, if any.
	 * @return bool Whether a sender without a photo has one coming: queued now, or by an earlier request.
	 */
	private static function sync_sender_avatar( Conversation $conversation, string $avatar_url ): bool {
		try {
			// The URL first: only the profile panel sends one, so the other panels don't load the sender.
			if ( ! SyncSenderAvatar::is_avatar_url( $avatar_url ) || ! $conversation->customer ) {
				return false;
			}

			// Before the job: a queue that runs jobs right away would already have saved it.
			$had_photo = (bool) $conversation->customer->photo_url;

			$queued = \Cache::add( 'wporgsidebar.avatar.' . $conversation->customer_id, true, self::AVATAR_MINUTES );
			if ( $queued ) {
				\Cache::put( SyncSenderAvatar::pending_key( (int) $conversation->customer_id ), true, SyncSenderAvatar::PENDING_MINUTES );
				SyncSenderAvatar::dispatch( (int) $conversation->customer_id, $avatar_url );
			}

			return ! $had_photo && ( $queued || \Cache::has( SyncSenderAvatar::pending_key( (int) $conversation->customer_id ) ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not queue the avatar of sender ' . $conversation->customer_id . ': ' . $e->getMessage() );

			return false;
		}
	}
}

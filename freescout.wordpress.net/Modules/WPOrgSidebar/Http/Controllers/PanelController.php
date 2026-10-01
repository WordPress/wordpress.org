<?php
/**
 * Sidebar panel controller.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Http\Controllers;

use App\Conversation;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\WPOrgSidebar\Jobs\SyncSenderAvatar;
use Modules\WPOrgSidebar\Services\Client;
use Modules\WPOrgSidebar\Services\ConversationPayload;

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
	 * Returns the content of one panel, as blocks sidebar.js builds it from.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $panel           Panel ID.
	 * @return JsonResponse
	 */
	public function show( int $conversation_id, string $panel ): JsonResponse {
		$panels = (array) config( 'wporgsidebar.panels' );
		if ( empty( $panels[ $panel ]['endpoint'] ) ) {
			abort( 404 );
		}

		$conversation = Conversation::findOrFail( $conversation_id );
		if ( ! auth()->user()->can( 'view', $conversation ) ) {
			abort( 403 );
		}

		$payload = ConversationPayload::build( $conversation );

		// The account a bounce or Slack notification names, instead of the sender's, once the agent asks for it.
		if ( request()->query( 'related' ) ) {
			$payload['related'] = true;
		}

		try {
			$response = Client::from_config( self::TIMEOUT )->post( (string) $panels[ $panel ]['endpoint'], $payload );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not load panel ' . $panel . ': ' . $e->getMessage() );

			return response()->json( array( 'blocks' => array() ), 502 );
		}

		self::sync_sender_avatar( $conversation, (string) ( $response['avatar_url'] ?? '' ) );

		return response()->json( array( 'blocks' => array_values( (array) ( $response['blocks'] ?? array() ) ) ) );
	}

	/**
	 * Queues saving the avatar of the sender's WordPress.org account as their photo.
	 *
	 * Never throws: the panel is what the agent is waiting for.
	 *
	 * @param Conversation $conversation Conversation the panel is for.
	 * @param string       $avatar_url   Avatar URL the profile panel sent, if any.
	 * @return void
	 */
	private static function sync_sender_avatar( Conversation $conversation, string $avatar_url ): void {
		try {
			if (
				! $conversation->customer_id ||
				! SyncSenderAvatar::is_avatar_url( $avatar_url ) ||
				! \Cache::add( 'wporgsidebar.avatar.' . $conversation->customer_id, true, self::AVATAR_MINUTES )
			) {
				return;
			}

			SyncSenderAvatar::dispatch( (int) $conversation->customer_id, $avatar_url );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not queue the avatar of sender ' . $conversation->customer_id . ': ' . $e->getMessage() );
		}
	}
}

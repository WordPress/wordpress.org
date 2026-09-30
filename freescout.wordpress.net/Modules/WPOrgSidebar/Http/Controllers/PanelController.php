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
	 * Returns the HTML for one panel.
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

		try {
			$response = Client::from_config( self::TIMEOUT )->post(
				(string) $panels[ $panel ]['endpoint'],
				ConversationPayload::build( $conversation )
			);
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not load panel ' . $panel . ': ' . $e->getMessage() );

			return response()->json( array( 'html' => '' ), 502 );
		}

		return response()->json( array( 'html' => (string) ( $response['html'] ?? '' ) ) );
	}
}

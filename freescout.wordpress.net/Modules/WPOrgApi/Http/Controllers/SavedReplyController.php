<?php
/**
 * Hands WordPress.org a mailbox's saved reply.
 *
 * @package WordPressdotorg\FreeScout\WPOrgApi
 */

declare( strict_types = 1 );

namespace Modules\WPOrgApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Schema;
use Modules\WPOrgApi\Services\SignedRequest;

/**
 * A mailbox's saved reply, by name, from the Saved Replies module, so WordPress.org's scripts write what agents keep
 * up to date there.
 */
final class SavedReplyController extends Controller {

	/**
	 * Alias of the Saved Replies module.
	 *
	 * @var string
	 */
	private const MODULE = 'savedreplies';

	/**
	 * The module's table.
	 *
	 * @var string
	 */
	private const TABLE = 'saved_replies';

	/**
	 * Responds with the saved reply named in the request, in the mailbox it names.
	 *
	 * @param Request $request Request, with `mailbox_id` and `name`.
	 * @return JsonResponse `name` and `text`, the reply's HTML.
	 */
	public function show( Request $request ): JsonResponse {
		$payload = SignedRequest::payload( $request, 'saved-reply' );
		if ( null === $payload ) {
			return response()->json( array( 'error' => 'invalid_signature' ), 403 );
		}

		try {
			if ( ! \App\Module::isActive( self::MODULE ) || ! Schema::hasTable( self::TABLE ) ) {
				return response()->json( array( 'error' => 'saved_replies_unavailable' ), 503 );
			}

			$reply = \DB::table( self::TABLE )
				->where( 'mailbox_id', (int) ( $payload['mailbox_id'] ?? 0 ) )
				->where( 'name', (string) ( $payload['name'] ?? '' ) )
				->first( array( 'name', 'text' ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgApi] Could not read a saved reply: ' . $e->getMessage() );

			return response()->json( array( 'error' => 'saved_replies_unavailable' ), 503 );
		}

		if ( ! $reply ) {
			return response()->json( array( 'error' => 'not_found' ), 404 );
		}

		return response()->json(
			array(
				'name' => (string) $reply->name,
				'text' => (string) $reply->text,
			)
		);
	}
}

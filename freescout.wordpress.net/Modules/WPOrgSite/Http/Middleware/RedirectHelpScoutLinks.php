<?php
/**
 * Redirects HelpScout's conversation IDs in core's conversation links.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Http\Request;
use Modules\WPOrgSite\Services\HelpScoutLinks;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects `/conversation/{HelpScout ID}`, which core answers with a 404, to the conversation it was imported as.
 *
 * It acts only on core's 404, so viewing a FreeScout conversation costs nothing extra.
 */
final class RedirectHelpScoutLinks {

	/**
	 * Handles a request.
	 *
	 * @param Request $request Request.
	 * @param Closure $next    Next handler.
	 * @return Response
	 */
	public function handle( Request $request, Closure $next ): Response {
		$response = $next( $request );
		$route    = $request->route();
		$user     = $request->user();

		if ( 404 !== $response->getStatusCode() || ! $route || 'conversations.view' !== $route->getName() || ! $user instanceof User ) {
			return $response;
		}

		try {
			$id           = (int) $route->parameter( 'id' );
			$conversation = $id ? HelpScoutLinks::conversation( $id, $user ) : null;
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSite] Could not look up a HelpScout conversation: ' . $e->getMessage() );
			$conversation = null;
		}

		return $conversation ? redirect()->away( $conversation->url() ) : $response;
	}
}

<?php
/**
 * Opens HelpScout's conversation IDs in FreeScout's conversation links.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Http\Middleware;

use App\Conversation;
use App\User;
use Closure;
use Illuminate\Http\Request;
use Modules\WPOrgSite\Services\HelpScoutLinks;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects `/conversation/{HelpScout ID}`, which core would answer with a 404, to the conversation it was imported as.
 *
 * HelpScout's IDs are 9 or 10 digits, far beyond FreeScout's, so they don't stand for a FreeScout conversation too.
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
		$route = $request->route();
		$user  = $request->user();

		// Guests are left to the route's auth middleware, which brings them back here after logging in.
		if ( ! $route || 'conversations.view' !== $route->getName() || ! $user instanceof User ) {
			return $next( $request );
		}

		try {
			$id           = (int) $route->parameter( 'id' );
			$conversation = $id && ! Conversation::whereKey( $id )->exists() ? HelpScoutLinks::conversation( $id, $user ) : null;
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSite] Could not look up a HelpScout conversation: ' . $e->getMessage() );
			$conversation = null;
		}

		return $conversation ? redirect()->away( $conversation->url() ) : $next( $request );
	}
}

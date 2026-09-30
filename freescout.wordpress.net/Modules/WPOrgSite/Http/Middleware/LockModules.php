<?php
/**
 * Keeps modules as deploys left them.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses to update or delete modules from the Modules page.
 *
 * Modules' files are managed outside FreeScout, starting with ours, which deploys check out. Switching modules on
 * and off, and installing premium ones, stay.
 */
final class LockModules {

	/**
	 * Modules page AJAX actions that change a module's files.
	 *
	 * @var string[]
	 */
	private const ACTIONS = array( 'delete', 'update', 'update_all' );

	/**
	 * Handles a request.
	 *
	 * @param Request $request Request.
	 * @param Closure $next    Next handler.
	 * @return Response
	 */
	public function handle( Request $request, Closure $next ): Response {
		$action = $request->route() ? (string) $request->route()->getActionName() : '';

		if ( 'App\Http\Controllers\ModulesController@ajax' === $action && in_array( $request->input( 'action' ), self::ACTIONS, true ) ) {
			// Successful, like core's own errors; the page only shows the message of those.
			return response()->json(
				array(
					'status' => 'error',
					'msg'    => __( 'Modules are updated and removed outside FreeScout.' ),
				)
			);
		}

		return $next( $request );
	}
}

<?php
/**
 * Opens HelpScout's links to conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Http\Controllers;

use App\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\WPOrgSite\Services\HelpScoutLinks;

/**
 * Redirects `/conversation/{HelpScout ID}/{number}`, a HelpScout link with FreeScout's address, to the conversation.
 */
final class HelpScoutLinksController extends Controller {

	/**
	 * Redirects to the conversation the HelpScout conversation was imported as.
	 *
	 * @param Request $request      Request.
	 * @param string  $helpscout_id HelpScout's conversation ID.
	 * @return RedirectResponse
	 */
	public function show( Request $request, string $helpscout_id ): RedirectResponse {
		$user         = $request->user();
		$conversation = $user instanceof User ? HelpScoutLinks::conversation( (int) $helpscout_id, $user ) : null;
		if ( ! $conversation ) {
			abort( 404 );
		}

		return redirect()->away( $conversation->url() );
	}
}

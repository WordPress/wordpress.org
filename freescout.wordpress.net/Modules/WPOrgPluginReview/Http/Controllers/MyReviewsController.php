<?php
/**
 * My Reviews controller.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Http\Controllers;

use App\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\WPOrgPluginReview\Services\MyReviews;
use Modules\WPOrgPluginReview\Services\Review;
use Modules\WPOrgPluginReview\Services\Reviewers;

/**
 * Shows the plugins team's mailbox's My Reviews folder: the Plugin Reviews team's open and pending conversations that
 * a reviewer replied to, that they're mentioned in, and that need a reviewer. It's the agent's own reviews unless they
 * choose another reviewer.
 */
final class MyReviewsController extends Controller {

	/**
	 * Shows a reviewer's conversations.
	 *
	 * @param Request $request    Request; `user` chooses the reviewer.
	 * @param int     $mailbox_id Mailbox ID.
	 * @return View
	 */
	public function show( Request $request, int $mailbox_id ): View {
		$viewer = auth()->user();

		// With the agent's settings for the mailbox, which its folder list shows.
		$mailbox = Mailbox::findOrFailWithSettings( $mailbox_id, $viewer->id );
		if ( ! Review::is_plugins_mailbox( $mailbox ) ) {
			abort( 404 );
		}

		if ( ! $viewer->can( 'viewCached', $mailbox ) || ! app( Reviewers::class )->includes( $viewer ) ) {
			abort( 403 );
		}

		$reviewers = MyReviews::reviewers( $mailbox );
		$user      = $reviewers->firstWhere( 'id', (int) $request->query( 'user', $viewer->id ) ) ?? $viewer;
		$sections  = MyReviews::sections( $mailbox, $user, $viewer );
		$folders   = $mailbox->getAssesibleFolders();
		$folder    = $folders->first( array( MyReviews::class, 'is_folder' ) ) ?? MyReviews::folder( $mailbox );

		// The folder list counts the agent's own active reviews, which may have changed since they were counted.
		if ( (int) $user->id === (int) $viewer->id ) {
			$folder->active_count = count(
				array_filter(
					$sections['participating'],
					static function ( $conversation ): bool {
						return $conversation->isActive();
					}
				)
			);
		}

		return view(
			'wporgpluginreview::my-reviews',
			array(
				'mailbox'   => $mailbox,
				'folders'   => $folders,
				'folder'    => $folder,
				'user'      => $user,
				'is_viewer' => (int) $user->id === (int) $viewer->id,
				'reviewers' => $reviewers->contains( 'id', $viewer->id ) ? $reviewers : $reviewers->prepend( $viewer ),
				'sections'  => $sections,
			)
		);
	}
}

<?php
/**
 * Worklist controller.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Http\Controllers;

use App\Conversation;
use App\Mailbox;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Modules\WPOrgPluginReview\Services\OpenOrder;
use Modules\WPOrgPluginReview\Services\Review;
use Modules\WPOrgPluginReview\Services\Reviewers;
use Modules\WPOrgPluginReview\Services\Worklist;

/**
 * Shows the plugins team's mailbox's My Reviews and My Cases folders: a team's open and pending conversations that a
 * member replied to, that they're mentioned in, and that need someone. They're the agent's own unless they choose
 * another member; My Cases can also be filtered by tag. The active ones they're participating in can be opened in new
 * tabs, the longest waiting first or by progress.
 */
final class WorklistController extends Controller {

	/**
	 * Shows a member's conversations.
	 *
	 * @param Request $request    Request; `user` chooses the member, and `tag` a tag to filter by.
	 * @param int     $mailbox_id Mailbox ID.
	 * @param string  $worklist   The worklist's part of the URL.
	 * @return View
	 */
	public function show( Request $request, int $mailbox_id, string $worklist ): View {
		$list = Worklist::by_slug( $worklist );
		if ( ! $list ) {
			abort( 404 );
		}

		$viewer = auth()->user();

		// With the agent's settings for the mailbox, which its folder list shows.
		$mailbox = Mailbox::findOrFailWithSettings( $mailbox_id, $viewer->id );
		if ( ! Review::is_plugins_mailbox( $mailbox ) ) {
			abort( 404 );
		}

		if ( ! $viewer->can( 'viewCached', $mailbox ) || ! app( Reviewers::class )->includes( $viewer ) ) {
			abort( 403 );
		}

		$reviewers = Worklist::reviewers( $mailbox );
		$user      = $reviewers->firstWhere( 'id', (int) $request->query( 'user', $viewer->id ) ) ?? $viewer;
		$ids       = $list->section_ids( $mailbox, $user, $viewer, $reviewers );

		$tags = array();
		$tag  = 0;
		if ( $list->tag_filter ) {
			$requested = (int) $request->query( 'tag', 0 );
			$tags      = Worklist::tags( $ids, $requested );
			if ( isset( $tags[ $requested ] ) ) {
				$tag = $requested;
				$ids = Worklist::filter_by_tag( $ids, $tag );
			}
		}

		$sections = Worklist::load( $ids );
		$limit    = OpenOrder::limit( $viewer );
		$open     = array(
			'oldest'   => OpenOrder::oldest_first( $sections['participating'], $limit ),
			'progress' => OpenOrder::progress_based( $sections['participating'], $limit ),
		);
		$folders  = $mailbox->getAssesibleFolders();
		$folder   = $folders->first( array( $list, 'is_folder' ) ) ?? $list->folder( $mailbox );

		// The folder list counts the agent's own active conversations, which may have changed since they were counted.
		if ( (int) $user->id === (int) $viewer->id && ! $tag ) {
			$folder->active_count = count(
				array_filter(
					$sections['participating'],
					static function ( Conversation $conversation ): bool {
						return $conversation->isActive();
					}
				)
			);
		}

		return view(
			'wporgpluginreview::worklist',
			array(
				'list'      => $list,
				'mailbox'   => $mailbox,
				'folders'   => $folders,
				'folder'    => $folder,
				'user'      => $user,
				'is_viewer' => (int) $user->id === (int) $viewer->id,
				'reviewers' => $reviewers->contains( 'id', $viewer->id ) ? $reviewers : $reviewers->prepend( $viewer ),
				'sections'  => $sections,
				'tags'      => $tags,
				'tag'       => $tag,
				'open'      => $open,
				'limit'     => $limit,
			)
		);
	}
}

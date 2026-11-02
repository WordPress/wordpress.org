<?php
/**
 * Limits how many people an email goes to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Http\Middleware;

use App\Thread;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuses to send a reply, forward, or new conversation to more than 100 people in To, Cc, and Bcc together.
 *
 * Gmail refuses more than 100 in To and Cc over SMTP, and counts every recipient towards the mailbox's daily limits.
 *
 * @see https://support.google.com/a/answer/166852
 */
final class LimitRecipients {

	/**
	 * The most people an email can go to.
	 *
	 * @var int
	 */
	public const MAX = 100;

	/**
	 * Handles a request.
	 *
	 * @param Request $request Request.
	 * @param Closure $next    Next handler.
	 * @return Response
	 */
	public function handle( Request $request, Closure $next ): Response {
		$action = $request->route() ? (string) $request->route()->getActionName() : '';

		// Notes aren't emailed, and anyone logged out is left to FreeScout.
		if ( $request->user() && 'App\Http\Controllers\ConversationsController@ajax' === $action && 'send_reply' === $request->input( 'action' ) && ! $request->input( 'is_note' ) && self::count( $request ) > self::MAX ) {
			// Successful, like core's own errors; the form only shows the message of those.
			return response()->json(
				array(
					'status' => 'error',
					'msg'    => __( 'An email can go to up to :max people, in To, Cc, and Bcc together. Gmail counts each of them towards the mailbox’s daily limit, and a long list uses it up, so the mailbox can’t send anything else. Send announcements from a mailing list instead.', array( 'max' => self::MAX ) ),
				)
			);
		}

		return $next( $request );
	}

	/**
	 * Counts the people an email goes to, as core reads its fields.
	 *
	 * @param Request $request Request.
	 * @return int
	 */
	private static function count( Request $request ): int {
		// Forwards use `to_email`; a reply without `to` goes to the sender.
		$to = self::emails( Thread::SUBTYPE_FORWARD === (int) $request->input( 'subtype' ) ? $request->input( 'to_email' ) : $request->input( 'to' ) );
		$to = $to ? $to : array( '' );

		return count( array_unique( array_merge( $to, self::emails( $request->input( 'cc' ) ), self::emails( $request->input( 'bcc' ) ) ) ) );
	}

	/**
	 * Reads a recipient field's addresses.
	 *
	 * @param mixed $field The field's value, an address or a list of them.
	 * @return string[]
	 */
	private static function emails( mixed $field ): array {
		return \MailHelper::sanitizeEmails( array_filter( (array) $field, 'is_string' ) );
	}
}

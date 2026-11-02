<?php
/**
 * Keeps emails from going to more people than Gmail lets the mailbox send to.
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
 * Gmail refuses an email to more than 100 people over SMTP outright, which FreeScout then retries for a week.
 * FreeScout's mailer also sends each Bcc address an email of its own, and Gmail counts each of them towards the
 * mailbox's daily sending limit. Once that's reached partway through, the whole email counts as failed, and it's sent
 * again every hour, from the top of the list, so the first people get it again and again, and the mailbox can't send
 * anything else.
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
		// Forwards go to `to_email`; replies go to the conversation's sender when `to` is empty.
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

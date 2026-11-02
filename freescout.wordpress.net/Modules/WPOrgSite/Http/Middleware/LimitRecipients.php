<?php
/**
 * Limits how many people an email goes to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Http\Middleware;

use App\Mailbox;
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
		if ( ! $request->user() || 'App\Http\Controllers\ConversationsController@ajax' !== $action || 'send_reply' !== $request->input( 'action' ) || $request->input( 'is_note' ) ) {
			return $next( $request );
		}

		try {
			$people = self::people( $request );
			$total  = count( array_unique( array_merge( $people, self::auto_bcc( $request ) ) ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSite] Could not count an email’s recipients: ' . $e->getMessage() );

			return $next( $request );
		}

		if ( $total <= self::MAX ) {
			return $next( $request );
		}

		$limit = count( $people ) > self::MAX
			? __( 'An email can go to up to :max people, in To, Cc, and Bcc together.', array( 'max' => self::MAX ) )
			: __(
				'An email can go to up to :max people, in To, Cc, and Bcc together, and this mailbox’s Auto Bcc adds :auto_bcc, which makes :total.',
				array(
					'max'      => self::MAX,
					'auto_bcc' => $total - count( $people ),
					'total'    => $total,
				)
			);

		// Successful, like core's own errors; the form only shows the message of those.
		return response()->json(
			array(
				'status' => 'error',
				'msg'    => $limit . ' ' . __( 'Gmail counts each of them towards the mailbox’s daily limit, and a long list uses it up, so the mailbox can’t send anything else. Send announcements from a mailing list instead.' ),
			)
		);
	}

	/**
	 * The people in the email's To, Cc, and Bcc, as core reads its fields.
	 *
	 * @param Request $request Request.
	 * @return string[]
	 */
	private static function people( Request $request ): array {
		// Forwards use `to_email`; a reply without `to` goes to the sender.
		$to = self::emails( Thread::SUBTYPE_FORWARD === (int) $request->input( 'subtype' ) ? $request->input( 'to_email' ) : $request->input( 'to' ) );
		$to = $to ? $to : array( '' );

		return array_values( array_unique( array_merge( $to, self::emails( $request->input( 'cc' ) ), self::emails( $request->input( 'bcc' ) ) ) ) );
	}

	/**
	 * The mailbox's Auto Bcc, which core adds to every email it sends.
	 *
	 * @param Request $request Request.
	 * @return string[]
	 */
	private static function auto_bcc( Request $request ): array {
		$mailbox = Mailbox::find( (int) $request->input( 'mailbox_id' ) );

		return $mailbox ? \MailHelper::sanitizeEmails( (string) $mailbox->auto_bcc ) : array();
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

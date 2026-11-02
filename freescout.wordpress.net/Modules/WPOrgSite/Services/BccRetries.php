<?php
/**
 * Keeps FreeScout from sending an email with Bcc again after Gmail stopped it partway through.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

use App\Mailbox;
use App\Thread;
use Illuminate\Support\Collection;

/**
 * Doesn't retry an email with Bcc that reached Gmail's daily sending limit.
 *
 * FreeScout's mailer sends To and Cc one email, then each Bcc address one of its own. When Gmail's daily limit is
 * reached partway through, FreeScout retries the whole email every hour for a week, so those it already reached get it
 * again each time. Such an email fails instead, and shows as not sent, for an agent to decide what to do. Emails without
 * Bcc go out in one piece or not at all, so they're still retried.
 */
final class BccRetries {

	/**
	 * Gmail's error for an account that reached its daily sending limit.
	 *
	 * @var string
	 */
	public const ERROR = 'Daily user sending limit exceeded';

	/**
	 * Makes the reply job, which reads core's errors not to retry just before sending, leave out those for this email.
	 *
	 * @return void
	 */
	public static function register(): void {
		$errors = (string) config( 'app.no_retry_mail_errors' );

		\Eventy::addFilter(
			'email.reply_to_customer.threads',
			static function ( Collection $threads, mixed $conversation = null, mixed $mailbox = null ) use ( $errors ): Collection {
				// The worker runs one job after another, so each sets it anew.
				config( array( 'app.no_retry_mail_errors' => self::has_bcc( $threads->first(), $mailbox ) ? $errors . '|' . self::ERROR : $errors ) );

				return $threads;
			},
			20,
			3
		);
	}

	/**
	 * Whether an email goes to anyone in Bcc, its own or the mailbox's Auto Bcc.
	 *
	 * @param mixed $thread  The thread being sent.
	 * @param mixed $mailbox Its mailbox.
	 * @return bool
	 */
	private static function has_bcc( mixed $thread, mixed $mailbox ): bool {
		return ( $thread instanceof Thread && $thread->getBccArray() ) || ( $mailbox instanceof Mailbox && \MailHelper::sanitizeEmails( (string) $mailbox->auto_bcc ) );
	}
}

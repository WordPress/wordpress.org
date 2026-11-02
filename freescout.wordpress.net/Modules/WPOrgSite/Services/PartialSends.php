<?php
/**
 * Keeps FreeScout from sending an email again after Gmail stopped it partway through.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

/**
 * Doesn't retry a reply that reached Gmail's daily sending limit after Gmail accepted part of it.
 *
 * FreeScout's mailer sends To and Cc one email, then each Bcc address one of its own. When Gmail's daily limit is
 * reached partway through, FreeScout retries the whole reply every hour for a week, so those it already reached get it
 * again each time. Once Gmail accepted any of its emails, such a reply fails instead, and shows as not sent, for an
 * agent to decide what to do. One that Gmail refused from the start, like any reply while the mailbox is over its limit,
 * is still retried.
 */
final class PartialSends implements \Swift_Events_CommandListener, \Swift_Events_ResponseListener {

	/**
	 * Gmail's error for an account that reached its daily sending limit.
	 *
	 * @var string
	 */
	public const ERROR = 'Daily user sending limit exceeded';

	/**
	 * SMTP's end of an email's data, which the server answers with 250 once it accepted the email.
	 *
	 * @var string
	 */
	private const END_OF_DATA = "\r\n.\r\n";

	/**
	 * Core's errors not to retry.
	 *
	 * @var string
	 */
	private string $errors;

	/**
	 * Whether the last command was the end of an email's data.
	 *
	 * @var bool
	 */
	private bool $ended = false;

	/**
	 * Remembers core's errors not to retry.
	 *
	 * @param string $errors Core's errors not to retry, a regular expression.
	 */
	private function __construct( string $errors ) {
		$this->errors = $errors;
	}

	/**
	 * Watches each reply as it's sent, from the filter the reply job runs just before sending.
	 *
	 * @return void
	 */
	public static function register(): void {
		$listener = new self( (string) config( 'app.no_retry_mail_errors' ) );

		\Eventy::addFilter(
			'email.reply_to_customer.threads',
			static function ( mixed $threads ) use ( $listener ): mixed {
				$listener->start();

				return $threads;
			}
		);
	}

	/**
	 * Starts watching a reply, on the mailer core just set up for its mailbox.
	 *
	 * The worker runs one job after another, so each starts with core's errors. Swift ignores a listener it already has.
	 *
	 * @return void
	 */
	private function start(): void {
		config( array( 'app.no_retry_mail_errors' => $this->errors ) );
		$this->ended = false;

		\Mail::getSwiftMailer()->registerPlugin( $this );
	}

	/**
	 * Notes whether a command ended an email's data.
	 *
	 * @param \Swift_Events_CommandEvent $evt Command event.
	 * @return void
	 */
	public function commandSent( \Swift_Events_CommandEvent $evt ): void {
		$this->ended = self::END_OF_DATA === $evt->getCommand();
	}

	/**
	 * Once Gmail accepted one of the reply's emails, makes its daily limit an error not to retry.
	 *
	 * @param \Swift_Events_ResponseEvent $evt Response event.
	 * @return void
	 */
	public function responseReceived( \Swift_Events_ResponseEvent $evt ): void {
		if ( $this->ended && $evt->isValid() ) {
			config( array( 'app.no_retry_mail_errors' => $this->errors . '|' . self::ERROR ) );
		}

		$this->ended = false;
	}
}

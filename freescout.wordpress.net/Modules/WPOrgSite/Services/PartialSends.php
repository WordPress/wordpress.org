<?php
/**
 * Keeps FreeScout from sending an email again after Gmail stopped it partway through.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

use App\Thread;

/**
 * Doesn't retry a reply that hit Gmail's daily limit after Gmail accepted part of it, and notes who didn't get it.
 *
 * FreeScout sends To and Cc one email, then each Bcc address its own, and retries the whole reply hourly for a week,
 * so those already reached would get it again each time. A reply Gmail refused from the start is still retried.
 */
final class PartialSends implements \Swift_Events_CommandListener, \Swift_Events_ResponseListener, \Swift_Events_SendListener {

	/**
	 * Gmail's error for an account that reached its daily sending limit.
	 *
	 * @var string
	 */
	public const ERROR = 'Daily user sending limit exceeded';

	/**
	 * Ends an email's data in SMTP; a 250 to it means the server accepted the email.
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
	 * The reply being sent.
	 *
	 * @var Thread|null
	 */
	private ?Thread $reply = null;

	/**
	 * Everyone the reply goes to.
	 *
	 * @var string[]
	 */
	private array $recipients = array();

	/**
	 * Those Gmail accepted the reply for.
	 *
	 * @var string[]
	 */
	private array $reached = array();

	/**
	 * Recipients Gmail took in the email being sent, which it hasn't accepted yet.
	 *
	 * @var string[]
	 */
	private array $pending = array();

	/**
	 * Commands Gmail hasn't answered yet, oldest first; Swift sends the sender and recipients before reading the answers.
	 *
	 * @var string[]
	 */
	private array $commands = array();

	/**
	 * Whether the next email Swift sends is the reply.
	 *
	 * @var bool
	 */
	private bool $armed = false;

	/**
	 * Whether the email being sent is the reply; the mailer also sends others, like notifications.
	 *
	 * @var bool
	 */
	private bool $active = false;

	/**
	 * Remembers core's errors not to retry.
	 *
	 * @param string $errors Core's errors not to retry, a regular expression.
	 */
	public function __construct( string $errors ) {
		$this->errors = $errors;
	}

	/**
	 * Starts watching a reply, on the mailer core just set up for it, from the filter the reply job runs before sending.
	 *
	 * Each reply starts from core's errors, as the worker runs one job after another. Swift ignores a listener it has.
	 *
	 * @param mixed $reply The reply, the newest of the threads the job sends.
	 * @return void
	 */
	public function start( mixed $reply ): void {
		config( array( 'app.no_retry_mail_errors' => $this->errors ) );
		$this->reply      = $reply instanceof Thread ? $reply : null;
		$this->recipients = array();
		$this->reached    = array();
		$this->pending    = array();
		$this->commands   = array();
		$this->armed      = true;
		$this->active     = false;

		\Mail::getSwiftMailer()->registerPlugin( $this );
	}

	/**
	 * Starts watching the email the reply filter armed for, noting everyone it goes to before Swift sets Bcc aside.
	 *
	 * @param \Swift_Events_SendEvent $evt Send event.
	 * @return void
	 */
	public function beforeSendPerformed( \Swift_Events_SendEvent $evt ): void {
		$this->active = $this->armed;
		$this->armed  = false;
		if ( ! $this->active ) {
			return;
		}

		try {
			$message = $evt->getMessage();
			$emails  = array_merge( array_keys( (array) $message->getTo() ), array_keys( (array) $message->getCc() ), array_keys( (array) $message->getBcc() ) );

			$this->recipients = array_values( array_unique( array_map( 'strtolower', $emails ) ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSite] Could not read a reply’s recipients: ' . $e->getMessage() );
		}
	}

	/**
	 * Stops watching once the reply was sent, and gives core's errors back, as there's no error to keep from a retry.
	 *
	 * @param \Swift_Events_SendEvent $evt Send event.
	 * @return void
	 */
	public function sendPerformed( \Swift_Events_SendEvent $evt ): void {
		if ( $this->active ) {
			$this->active = false;
			config( array( 'app.no_retry_mail_errors' => $this->errors ) );
		}
	}

	/**
	 * Queues a command for its answer.
	 *
	 * @param \Swift_Events_CommandEvent $evt Command event.
	 * @return void
	 */
	public function commandSent( \Swift_Events_CommandEvent $evt ): void {
		if ( $this->active ) {
			$this->commands[] = $evt->getCommand();
		}
	}

	/**
	 * Notes whom Gmail accepted the reply for, and what to do when its daily limit stops the reply partway through.
	 *
	 * Once Gmail accepted one of the reply's emails, its daily limit is an error not to retry, and stopping the reply
	 * adds a note with everyone who didn't get it.
	 *
	 * @param \Swift_Events_ResponseEvent $evt Response event.
	 * @return void
	 */
	public function responseReceived( \Swift_Events_ResponseEvent $evt ): void {
		if ( ! $this->active ) {
			return;
		}

		$command = (string) array_shift( $this->commands );

		if ( $evt->isValid() && str_starts_with( $command, 'MAIL FROM:' ) ) {
			$this->pending = array();
		} elseif ( $evt->isValid() && preg_match( '/^RCPT TO:<([^>]+)>/', $command, $m ) ) {
			$this->pending[] = strtolower( $m[1] );
		} elseif ( $evt->isValid() && self::END_OF_DATA === $command ) {
			$this->reached = array_merge( $this->reached, $this->pending );
			config( array( 'app.no_retry_mail_errors' => $this->errors . '|' . self::ERROR ) );
		} elseif ( ! $evt->isValid() && $this->reached && false !== stripos( $evt->getResponse(), self::ERROR ) ) {
			// Swift is sending; failing here would fail the email.
			try {
				$this->note();
			} catch ( \Throwable $e ) {
				\Log::error( '[WPOrgSite] Could not note who didn’t get a reply: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Adds a note to the reply's conversation, with everyone who didn't get it, ready to paste into Bcc.
	 *
	 * @return void
	 */
	private function note(): void {
		$missed = array_values( array_diff( $this->recipients, $this->reached ) );
		if ( ! $this->reply || ! $missed ) {
			return;
		}

		$body = '<p>' . e( __( 'Gmail stopped this reply at the mailbox’s daily sending limit. Don’t retry it, as everyone who got it would get it again. To send it to everyone who didn’t get it, paste this list into Bcc once the mailbox can send again:' ) ) . '</p>'
			. '<p>' . e( implode( ', ', $missed ) ) . '</p>';

		Thread::create(
			$this->reply->conversation,
			Thread::TYPE_NOTE,
			$body,
			array(
				'created_by_user_id' => $this->reply->created_by_user_id,
				'source_via'         => Thread::PERSON_USER,
				'source_type'        => Thread::SOURCE_TYPE_WEB,
			)
		);

		// Gmail answers nothing more, but a later failure mustn't note it twice.
		$this->reached = array();
	}
}

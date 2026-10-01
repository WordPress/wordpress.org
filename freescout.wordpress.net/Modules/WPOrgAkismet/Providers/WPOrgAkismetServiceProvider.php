<?php
/**
 * WPOrgAkismet service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Providers;

use App\Conversation;
use App\Thread;
use App\User;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgAkismet\Console\Report;
use Modules\WPOrgAkismet\Jobs\ReportToAkismet;
use Modules\WPOrgAkismet\Services\Akismet;
use Modules\WPOrgAkismet\Services\Message;

/**
 * Checks conversations senders start with Akismet, and reports the ones agents mark differently.
 */
final class WPOrgAkismetServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgakismet';

	/**
	 * Conversation meta key for Akismet's verdict and what was reported back.
	 *
	 * @var string
	 */
	public const META = 'wporgakismet';

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hide_key_from_debug_pages();

		$this->app->bind(
			Akismet::class,
			static function (): Akismet {
				return Akismet::from_config();
			}
		);
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->mergeConfigFrom( __DIR__ . '/../Config/config.php', self::ALIAS );
		$this->commands( array( Report::class ) );

		$this->register_hooks();
	}

	/**
	 * Keeps the API key off the Whoops error pages that APP_DEBUG shows, which list the environment.
	 *
	 * @return void
	 */
	private function hide_key_from_debug_pages(): void {
		$blacklist = (array) config( 'app.debug_blacklist', array() );

		foreach ( array( '_ENV', '_SERVER' ) as $key ) {
			$blacklist[ $key ] = (array) ( $blacklist[ $key ] ?? array() );

			if ( ! in_array( 'WPORG_AKISMET_KEY', $blacklist[ $key ], true ) ) {
				$blacklist[ $key ][] = 'WPORG_AKISMET_KEY';
			}
		}

		config( array( 'app.debug_blacklist' => $blacklist ) );
	}

	/**
	 * Registers the Eventy hooks.
	 *
	 * Callbacks take untyped, optional arguments and check them, so a change in what core passes can't throw where
	 * core doesn't catch it.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		// Runs while the conversation is created, before auto-replies and notifications, which skip spam.
		\Eventy::addFilter(
			'conversation.created_by_customer',
			static function ( $conversation = null, $thread = null ) {
				self::check( $conversation, $thread );

				return $conversation;
			},
			20,
			2
		);

		\Eventy::addAction(
			'conversation.status_changed',
			static function ( $conversation = null, $user = null, $changed_on_reply = null, $prev_status = null ): void {
				self::learn( $conversation, $user, $prev_status );
			},
			20,
			4
		);
	}

	/**
	 * Asks Akismet about a new conversation, records the verdict, and marks spam as such.
	 *
	 * Never throws: mail fetching must go on whatever happens here. Without a verdict, the conversation comes in
	 * as usual.
	 *
	 * @param mixed $conversation Conversation; core saves it again after the filter.
	 * @param mixed $thread       The sender's email that started it.
	 * @return void
	 */
	private static function check( $conversation, $thread ): void {
		try {
			if ( ! $conversation instanceof Conversation || ! $thread instanceof Thread ) {
				return;
			}

			// Imported conversations were judged when they first came in, elsewhere.
			if ( $conversation->imported || $thread->imported ) {
				return;
			}

			if ( Conversation::TYPE_EMAIL !== (int) $conversation->type || $conversation->isSpam() ) {
				return;
			}

			$akismet = app( Akismet::class );
			if ( ! $akismet->is_configured() ) {
				return;
			}

			$fields = Message::fields( $conversation, $thread );
			if ( ! $fields ) {
				return;
			}

			$verdict = $akismet->check( $fields );
			$conversation->setMeta( self::META, array( 'verdict' => $verdict ) );

			if ( Akismet::SPAM === $verdict ) {
				$conversation->setStatus( Conversation::STATUS_SPAM );
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgAkismet] Could not check a new conversation: ' . $e->getMessage() );
		}
	}

	/**
	 * Reports a checked conversation to Akismet when an agent marks it differently from what Akismet knows.
	 *
	 * @param mixed $conversation Conversation.
	 * @param mixed $user         Agent who changed the status; core passes an empty user when there's none.
	 * @param mixed $prev_status  Status before the change.
	 * @return void
	 */
	private static function learn( $conversation, $user, $prev_status ): void {
		try {
			if ( ! $conversation instanceof Conversation || ! $user instanceof User || ! $user->id ) {
				return;
			}

			$is_spam  = $conversation->isSpam();
			$was_spam = Conversation::STATUS_SPAM === Conversation::toMainStatus( $prev_status );
			if ( $is_spam === $was_spam ) {
				return;
			}

			$result = $conversation->getMeta( self::META );
			if ( ! is_array( $result ) || empty( $result['verdict'] ) || ! app( Akismet::class )->is_configured() ) {
				return;
			}

			$marked = $is_spam ? Akismet::SPAM : Akismet::HAM;
			$known  = $result['reported'] ?? $result['verdict'];
			if ( $marked !== $known ) {
				ReportToAkismet::dispatch( (int) $conversation->id, $marked );
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgAkismet] Could not report a conversation: ' . $e->getMessage() );
		}
	}
}

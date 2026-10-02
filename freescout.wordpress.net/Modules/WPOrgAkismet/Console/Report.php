<?php
/**
 * Compares Akismet's verdicts with what agents marked.
 *
 * @package WordPressdotorg\FreeScout\WPOrgAkismet
 */

declare( strict_types = 1 );

namespace Modules\WPOrgAkismet\Console;

use App\Conversation;
use Illuminate\Console\Command;
use Modules\WPOrgAkismet\Providers\WPOrgAkismetServiceProvider;
use Modules\WPOrgAkismet\Services\Akismet;

/**
 * Shows how often agents corrected Akismet, to keep an eye on how well it does on email.
 */
final class Report extends Command {

	/**
	 * Command signature.
	 *
	 * @var string
	 */
	protected $signature = 'wporgakismet:report {--days=14 : How many days of new conversations to look at}';

	/**
	 * Command description.
	 *
	 * @var string
	 */
	protected $description = 'Compares Akismet\'s verdicts on new conversations with whether agents marked them as spam.';

	/**
	 * Runs the command.
	 *
	 * @return int Exit code.
	 */
	public function handle(): int {
		$days  = max( 1, (int) $this->option( 'days' ) );
		$count = array(
			Akismet::SPAM => array(
				'spam' => 0,
				'not'  => 0,
			),
			Akismet::HAM  => array(
				'spam' => 0,
				'not'  => 0,
			),
		);

		$conversations = Conversation::query()
			->where( 'created_at', '>=', \Carbon\Carbon::now()->subDays( $days ) )
			->where( 'meta', 'like', '%"' . WPOrgAkismetServiceProvider::META . '"%' )
			->cursor();

		foreach ( $conversations as $conversation ) {
			$verdict = $conversation->getMeta( WPOrgAkismetServiceProvider::META )['verdict'] ?? '';
			if ( isset( $count[ $verdict ] ) ) {
				++$count[ $verdict ][ $conversation->isSpam() ? 'spam' : 'not' ];
			}
		}

		$this->table(
			array( 'Akismet said', 'Now marked spam', 'Now not spam' ),
			array(
				array( 'Spam', $count[ Akismet::SPAM ]['spam'], $count[ Akismet::SPAM ]['not'] ),
				array( 'Not spam', $count[ Akismet::HAM ]['spam'], $count[ Akismet::HAM ]['not'] ),
			)
		);
		$this->line( 'New conversations checked in the last ' . $days . ' days. Akismet was wrong where the row and column disagree.' );

		return 0;
	}
}

<?php
/**
 * Writes the Message-IDs of the other FreeScout's emails, which its API doesn't give, for the import page.
 *
 * Run it on the other FreeScout's server, as a user who can read its files, and upload what it writes:
 *
 *     php export-message-ids.php /var/www/html/scout.wc-us.org | gzip > message-ids.csv.gz
 *
 * It writes a line per email, its thread ID and its Message-ID. Replies its agents sent have a Message-ID FreeScout
 * makes up from the thread ID and its APP_KEY, so it's worked out there, with that FreeScout's own code: the key
 * never leaves the server.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

if ( 'cli' !== PHP_SAPI ) {
	exit( 1 );
}

$freescout_root = rtrim( (string) ( $argv[1] ?? '' ), '/' );
if ( '' === $freescout_root || ! is_file( $freescout_root . '/artisan' ) ) {
	fwrite( STDERR, "Usage: php export-message-ids.php /path/to/freescout > message-ids.csv\n" );
	exit( 1 );
}

require $freescout_root . '/vendor/autoload.php';
$freescout_app = require $freescout_root . '/bootstrap/app.php';
$freescout_app->make( Illuminate\Contracts\Console\Kernel::class )->bootstrap();

$output = fopen( 'php://stdout', 'w' );
$count  = 0;

App\Thread::query()
	->with( 'conversation.mailbox' )
	->whereIn( 'type', array( App\Thread::TYPE_CUSTOMER, App\Thread::TYPE_MESSAGE ) )
	->where( 'state', App\Thread::STATE_PUBLISHED )
	->chunkById(
		1000,
		static function ( \Illuminate\Support\Collection $threads ) use ( $output, &$count ): void {
			foreach ( $threads as $thread ) {
				$mailbox    = $thread->conversation ? $thread->conversation->mailbox : null;
				$message_id = $mailbox ? (string) $thread->getMessageId( $mailbox ) : '';

				if ( '' !== $message_id ) {
					fputcsv( $output, array( $thread->id, trim( $message_id, '<>' ) ), ',', '"', '' );
					++$count;
				}
			}
		}
	);

fwrite( STDERR, $count . " Message-IDs written.\n" );

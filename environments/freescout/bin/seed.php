<?php
/**
 * Seeds the local FreeScout with an admin and mailboxes wired to GreenMail and Mailpit.
 *
 * Idempotent; run by setup.sh.
 *
 * @package WordPressdotorg\FreeScout\Environment
 */

declare( strict_types = 1 );

namespace WordPressdotorg\FreeScout\Environment;

use App\Mailbox;
use App\User;
use Illuminate\Contracts\Console\Kernel;

require '/var/www/html/vendor/autoload.php';

$app = require '/var/www/html/bootstrap/app.php';
$app->make( Kernel::class )->bootstrap();

/**
 * Creates the admin account, if missing.
 *
 * @return void
 */
function seed_admin(): void {
	if ( User::where( 'email', 'admin@wordpress.test' )->exists() ) {
		return;
	}

	$user             = new User();
	$user->first_name = 'Admin';
	$user->last_name  = 'User';
	$user->email      = 'admin@wordpress.test';
	$user->password   = \Hash::make( 'password' );
	$user->role       = User::ROLE_ADMIN;
	$user->save();

	echo "Created admin@wordpress.test\n";
}

/**
 * Creates a mailbox fetching from GreenMail and sending through Mailpit, if missing.
 *
 * Mailbox names matter: api.wordpress.org derives the mailbox slug from them, e.g. "Plugins" => plugins.
 *
 * @param string $name  Mailbox name.
 * @param string $email Mailbox address.
 * @return void
 */
function seed_mailbox( string $name, string $email ): void {
	if ( Mailbox::where( 'email', $email )->exists() ) {
		return;
	}

	$mailbox = new Mailbox();
	$mailbox->fill(
		array(
			'name'             => $name,
			'email'            => $email,
			'in_server'        => 'greenmail',
			'in_port'          => 3143,
			'in_username'      => $email,
			'in_password'      => 'password',
			'in_protocol'      => Mailbox::IN_PROTOCOL_IMAP,
			'in_encryption'    => Mailbox::IN_ENCRYPTION_NONE,
			'in_validate_cert' => false,
			'out_method'       => Mailbox::OUT_METHOD_SMTP,
			'out_server'       => 'mailpit',
			'out_port'         => 1025,
			'out_encryption'   => Mailbox::OUT_ENCRYPTION_NONE,
		)
	);
	$mailbox->save();

	echo "Created mailbox {$name} <{$email}>\n";
}

seed_admin();
seed_mailbox( 'Plugins', 'plugins@wordpress.test' );
seed_mailbox( 'Themes', 'themes@wordpress.test' );

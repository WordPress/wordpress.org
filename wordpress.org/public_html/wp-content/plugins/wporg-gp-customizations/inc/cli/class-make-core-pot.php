<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

class Make_Core_Pot extends WP_CLI_Command {

	const PACKAGE_NAME = 'WordPress';

	/**
	 * Creates the POT files for WordPress core.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : WordPress directory to scan for string extraction.
	 *
	 * <destination>
	 * : Directory to store resulting POT files.
	 *
	 * [--dry-run]
	 * : Run without creating POT files.
	 */
	public function __invoke( $args, $assoc_args ) {
		$source      = trailingslashit( realpath( $args[0] ) );
		$destination = trailingslashit( realpath( $args[1] ) );

		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run', false );

		$headers = wp_json_encode( [
			'Report-Msgid-Bugs-To' => 'https://core.trac.wordpress.org/',
			'PO-Revision-Date'     => 'YEAR-MO-DA HO:MI+ZONE',
		], JSON_UNESCAPED_SLASHES );

		$file_comment = sprintf(
			"Copyright (C) %s by the contributors\nThis file is distributed under the same license as the WordPress package.",
			date( 'Y' )
		);

		// Continents and cities.
		$command  = 'i18n make-pot ' . escapeshellarg( $source );
		$command .= ' ' . escapeshellarg( $destination . 'wordpress-continents-cities.pot' );
		$command .= ' --include="wp-admin/includes/continents-cities.php"';
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command/*, [ 'launch' => false ]*/ );

		// Front end.
		$command  = 'i18n make-pot ' . escapeshellarg( $source );
		$command .= ' ' . escapeshellarg( $destination . 'wordpress.pot' );
		$command .= ' --exclude="wp-admin/*,wp-content/themes/*,wp-includes/class-pop3.php,wp-content/plugins/akismet/"';
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command/*, [ 'launch' => false ]*/ );

		// Hello Dolly, included in admin.
		$hello_dolly_pot = wp_tempnam( 'hello-dolly.pot' );

		$command  = 'i18n make-pot ' . escapeshellarg( $source . 'wp-content/plugins' );
		$command .= ' ' . escapeshellarg( $hello_dolly_pot );
		$command .= ' --exclude="akismet/*"';
		$command .= ' --include="hello.php"';
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command/*, [ 'launch' => false ]*/ );

		# Admin.
		$command  = 'i18n make-pot ' . escapeshellarg( $source );
		$command .= ' ' . escapeshellarg( $destination . 'wordpress-admin.pot' );
		$command .= ' --exclude="wp-admin/network/*,wp-admin/network.php,wp-admin/includes/class-wp-ms*,wp-admin/includes/network.php,wp-admin/includes/continents-cities.php"';
		$command .= ' --include="wp-admin/*"';
		$command .= ' --merge=' . escapeshellarg( $hello_dolly_pot );
		$command .= ' --subtract=' . escapeshellarg( $destination . 'wordpress.pot' );
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command/*, [ 'launch' => false ]*/ );

		unlink( $hello_dolly_pot );

		# Admin Network.
		$command  = 'i18n make-pot ' . escapeshellarg( $source );
		$command .= ' ' . escapeshellarg( $destination . 'wordpress-admin-network.pot' );
		$command .= ' --include="wp-admin/network/*,wp-admin/network.php,wp-admin/includes/class-wp-ms*,wp-admin/includes/network.php"';
		$command .= ' --subtract=' . escapeshellarg( sprintf( '%1$swordpress.pot,%1$swordpress-admin.pot', $destination ) );
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command/*, [ 'launch' => false ]*/ );
	}
}

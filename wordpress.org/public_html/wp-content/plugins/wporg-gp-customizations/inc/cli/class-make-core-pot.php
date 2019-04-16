<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;

class Make_Core_Pot extends WP_CLI_Command {

	const PACKAGE_NAME = 'WordPress';

	/**
	 * WordPress directory to scan for string extraction.
	 *
	 * @var string
	 */
	protected $source;

	/**
	 * Directory to store resulting POT files.
	 *
	 * @var string
	 */
	protected $destination;

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
		$this->source      = realpath( $args[0] );
		$this->destination = realpath( $args[1] );

		$wp_version = $this->get_wp_version();
		if ( ! $wp_version ) {
			WP_CLI::error( 'WordPress version not found.' );
		}

		if ( version_compare( $wp_version, '3.7-beta', '<' ) ) {
			WP_CLI::error( 'Unsupported WordPress version. Use makepot.php.' );
		}

		$dry_run = Utils\get_flag_value( $assoc_args, 'dry-run', false );

		$headers = wp_json_encode( [
			'Report-Msgid-Bugs-To' => 'https://core.trac.wordpress.org/',
		], JSON_UNESCAPED_SLASHES );

		$file_comment = sprintf(
			'Copyright (C) %s by the contributors\nThis file is distributed under the same license as the WordPress package.',
			date( 'Y' )
		);

		// Continents and cities.
		$command  = 'i18n make-pot ' . escapeshellarg( $this->source );
		$command .= ' ' . escapeshellarg( $this->destination . '/wordpress-continents-cities.pot' );
		$command .= ' --include="wp-admin/includes/continents-cities.php"';
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js';
		$command .= ' --skip-audit';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command );

		// Front end.
		$front_end_exclude = [
			'wp-admin/*',
			'wp-content/themes/*',
			'wp-includes/class-pop3.php',
			'wp-content/plugins/akismet/*',
			// External JavaScript libaries.
			'wp-includes/js/codemirror/*',
			'wp-includes/js/crop/*',
			'wp-includes/js/imgareaselect/*',
			'wp-includes/js/jcrop/*',
			'wp-includes/js/jquery/*',
			'wp-includes/js/mediaelement/*',
			'wp-includes/js/plupload/*',
			'wp-includes/js/swfupload/*',
			'wp-includes/js/thickbox/*',
			'wp-includes/js/tw-sack.js',
			'*.js.map', // TODO: Currently not parsable, https://wordpress.slack.com/archives/C02RP4T41/p1541003227208000.
		];

		// Support https://build.trac.wordpress.org/browser/branches/4.2/wp-includes/js/tinymce/wp-mce-help.php for pre-4.3.
		if ( version_compare( $wp_version, '4.3-beta', '>=' ) ) {
			$front_end_exclude[] = 'wp-includes/js/tinymce/*';
		}

		$command  = 'i18n make-pot ' . escapeshellarg( $this->source );
		$command .= ' ' . escapeshellarg( $this->destination . '/wordpress.pot' );
		$command .= ' --exclude=' . escapeshellarg( implode( ',', $front_end_exclude ) );
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-audit';
		$command .= ' --ignore-domain';

		if ( version_compare( $wp_version, '5.0-beta', '<' ) ) {
			$command .= ' --skip-js';
		}

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command );

		// Hello Dolly, included in admin.
		$hello_dolly_pot = wp_tempnam( 'hello-dolly.pot' );

		$command  = 'i18n make-pot ' . escapeshellarg( $this->source . '/wp-content/plugins' );
		$command .= ' ' . escapeshellarg( $hello_dolly_pot );
		$command .= ' --exclude="akismet/*"';
		$command .= ' --include="hello.php"';
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js';
		$command .= ' --skip-audit';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command );

		// Admin.
		$admin_network_files = [
			'wp-admin/network/*',
			'wp-admin/network.php',
		];

		// See https://core.trac.wordpress.org/ticket/34910.
		if ( version_compare( $wp_version, '4.5-beta', '>=' ) ) {
			$admin_network_files = array_merge( $admin_network_files, [
				'wp-admin/includes/class-wp-ms*',
				'wp-admin/includes/network.php',
			] );
		}

		$command  = 'i18n make-pot ' . escapeshellarg( $this->source );
		$command .= ' ' . escapeshellarg( $this->destination . '/wordpress-admin.pot' );
		$command .= ' --exclude=' . escapeshellarg( implode( ',', array_merge( [ 'wp-admin/includes/continents-cities.php' ], $admin_network_files ) ) );
		$command .= ' --include="wp-admin/*"';
		$command .= ' --merge=' . escapeshellarg( $hello_dolly_pot );
		$command .= ' --subtract=' . escapeshellarg( $this->destination . '/wordpress.pot' );
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-audit';
		$command .= ' --ignore-domain';

		if ( version_compare( $wp_version, '5.2-beta', '<' ) ) {
			$command .= ' --skip-js';
		}

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command );

		unlink( $hello_dolly_pot );

		// Admin Network.
		$command  = 'i18n make-pot ' . escapeshellarg( $this->source );
		$command .= ' ' . escapeshellarg( $this->destination . '/wordpress-admin-network.pot' );
		$command .= ' --include=' . escapeshellarg( implode( ',', $admin_network_files ) );
		$command .= ' --subtract=' . escapeshellarg( sprintf( '%1$s/wordpress.pot,%1$s/wordpress-admin.pot', $this->destination ) );
		$command .= ' --package-name=' . escapeshellarg( self::PACKAGE_NAME );
		$command .= ' --headers=' . escapeshellarg( $headers );
		$command .= ' --file-comment=' . escapeshellarg( $file_comment );
		$command .= ' --skip-js'; // TODO: No use of wp.i18n, yet.
		$command .= ' --skip-audit';
		$command .= ' --ignore-domain';

		WP_CLI::line( $command );
		! $dry_run && WP_CLI::runcommand( $command );
	}

	/**
	 * Extracts the WordPress version number from wp-includes/version.php.
	 *
	 * @return string|false Version number on success, false otherwise.
	 */
	private function get_wp_version() {
		$version_php = $this->source . '/wp-includes/version.php';
		if ( ! file_exists( $version_php ) || ! is_readable( $version_php ) ) {
			return false;
		}

		return preg_match( '/\$wp_version\s*=\s*\'(.*?)\';/', file_get_contents( $version_php ), $matches ) ? $matches[1] : false;
	}
}

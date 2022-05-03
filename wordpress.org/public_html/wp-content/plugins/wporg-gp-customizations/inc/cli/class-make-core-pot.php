<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use Gettext\Extractors\Po;
use Gettext\Translations;
use GP;
use WP_CLI;
use WP_CLI\Utils;
use WP_CLI_Command;
use WP_CLI\I18n\PotGenerator;

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
			"Copyright (C) %s by the contributors\nThis file is distributed under the same license as the WordPress package.",
			date( 'Y' )
		);
		PotGenerator::setCommentBeforeHeaders( $file_comment );

		$command_args = [
			'i18n',
			'make-pot',
			$this->source,
			$this->destination . '/wordpress-continents-cities.pot',
		];

		$command_assoc_args = [
			'exclude'       => 'patterns,styles', // Workaround for https://github.com/wp-cli/i18n-command/pull/320.
			'include'       => 'wp-admin/includes/continents-cities.php',
			'package-name'  => self::PACKAGE_NAME,
			'headers'       => $headers,
			'file-comment'  => '',
			'skip-js'       => true,
			'skip-audit'    => true,
			'ignore-domain' => true,
		];

		WP_CLI::run_command( $command_args, $command_assoc_args );

		// Front end.
		$front_end_exclude = [
			'wp-admin/*',
			'wp-content/themes/*',
			'wp-content/plugins/akismet/*',
			'*.js.map', // TODO: Currently not parsable, https://wordpress.slack.com/archives/C02RP4T41/p1541003227208000.
			// External libraries.
			'wp-includes/ID3/*',
			'wp-includes/IXR/*',
			'wp-includes/pomo/*',
			'wp-includes/random_compat/*',
			'wp-includes/Requests/*',
			'wp-includes/SimplePie/*',
			'wp-includes/Text/*',
			'wp-includes/sodium_compat/*',
			'wp-includes/atomlib.php',
			'wp-includes/class-IXR.php',
			'wp-includes/class-json.php',
			'wp-includes/class-phpass.php',
			'wp-includes/class-phpmailer.php',
			'wp-includes/class-pop3.php',
			'wp-includes/class-requests.php',
			'wp-includes/class-simplepie.php',
			'wp-includes/class-smtp.php',
			'wp-includes/class-snoopy.php',
			'wp-includes/rss.php',
			'wp-includes/js/codemirror/*',
			'wp-includes/js/crop/*',
			'wp-includes/js/dist/vendor/*',
			'wp-includes/js/imgareaselect/*',
			'wp-includes/js/jcrop/*',
			'wp-includes/js/jquery/*',
			'wp-includes/js/mediaelement/*',
			'wp-includes/js/plupload/*',
			'wp-includes/js/swfupload/*',
			'wp-includes/js/thickbox/*',
			'wp-includes/js/clipboard.js',
			'wp-includes/js/colorpicker.js',
			'wp-includes/js/hoverIntent.js',
			'wp-includes/js/json2.js',
			'wp-includes/js/swfobject.js',
			'wp-includes/js/tw-sack.js',
			'wp-includes/js/twemoji.js',
			'wp-includes/js/underscore.js',
		];

		// Support https://build.trac.wordpress.org/browser/branches/4.2/wp-includes/js/tinymce/wp-mce-help.php for pre-4.3.
		if ( version_compare( $wp_version, '4.3-beta', '>=' ) ) {
			$front_end_exclude[] = 'wp-includes/js/tinymce/*';
		}

		$command_args = [
			'i18n',
			'make-pot',
			$this->source,
			$this->destination . '/wordpress.pot',
		];

		$command_assoc_args = [
			'exclude'       => implode( ',', $front_end_exclude ),
			'package-name'  => self::PACKAGE_NAME,
			'headers'       => $headers,
			'file-comment'  => '',
			'skip-audit'    => true,
			'ignore-domain' => true,
		];

		if ( version_compare( $wp_version, '5.0-beta', '<' ) ) {
			$command_assoc_args['skip-js'] = true;
		}

		WP_CLI::run_command( $command_args, $command_assoc_args );

		// Hello Dolly, included in admin.
		$hello_dolly_pot = wp_tempnam( 'hello-dolly.pot' );

		$command_args = [
			'i18n',
			'make-pot',
			$this->source . '/wp-content/plugins',
			$hello_dolly_pot,
		];

		$command_assoc_args = [
			'exclude'       => 'akismet/*',
			'include'       => 'hello.php',
			'package-name'  => self::PACKAGE_NAME,
			'headers'       => $headers,
			'file-comment'  => '',
			'skip-js'       => true,
			'skip-audit'    => true,
			'ignore-domain' => true,
		];

		WP_CLI::run_command( $command_args, $command_assoc_args );

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

		$admin_exclude = [
			'patterns', 'styles', // Workaround for https://github.com/wp-cli/i18n-command/pull/320.
			'wp-admin/includes/continents-cities.php',
			// External libraries.
			'wp-admin/includes/class-ftp*',
			'wp-admin/includes/class-pclzip.php',
		];

		// Explicitly exclude minified JavaScript files as they may
		// cause memory leaks: https://github.com/wp-cli/i18n-command/issues/185.
		$admin_min_js  = array_map( 'basename', glob( $this->source . '/wp-admin/js/*.min.js' ) );
		$admin_exclude = array_merge( $admin_exclude, $admin_min_js );

		$admin_exclude = array_merge( $admin_exclude, $admin_network_files );

		$command_args = [
			'i18n',
			'make-pot',
			$this->source,
			$this->destination . '/wordpress-admin.pot',
		];

		$command_assoc_args = [
			'exclude'            => implode( ',', $admin_exclude ),
			'include'            => 'wp-admin/*',
			'merge'              => $hello_dolly_pot,
			'subtract'           => $this->destination . '/wordpress.pot',
			'subtract-and-merge' => true,
			'package-name'       => self::PACKAGE_NAME,
			'headers'            => $headers,
			'file-comment'       => '',
			'skip-audit'         => true,
			'ignore-domain'      => true,
		];

		if ( version_compare( $wp_version, '5.2-beta', '<' ) ) {
			$command_assoc_args['skip-js'] = true;
		}

		WP_CLI::run_command( $command_args, $command_assoc_args );

		unlink( $hello_dolly_pot );

		// Admin Network.
		$command_args = [
			'i18n',
			'make-pot',
			$this->source,
			$this->destination . '/wordpress-admin-network.pot',
		];

		$command_assoc_args = [
			'exclude'            => 'patterns,styles', // Workaround for https://github.com/wp-cli/i18n-command/pull/320.
			'include'            => implode( ',', $admin_network_files ),
			'subtract'           => sprintf( '%1$s/wordpress.pot,%1$s/wordpress-admin.pot', $this->destination ),
			'subtract-and-merge' => true,
			'package-name'       => self::PACKAGE_NAME,
			'headers'            => $headers,
			'file-comment'       => '',
			'skip-js'            => true, // TODO: No use of wp.i18n, yet.
			'skip-audit'         => true,
			'ignore-domain'      => true,
		];

		WP_CLI::run_command( $command_args, $command_assoc_args );
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

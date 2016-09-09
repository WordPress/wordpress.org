<?php

namespace WordPressdotorg\GlotPress\Theme_Directory;

use WP_CLI;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Parent project for themes.
	 */
	const GP_MASTER_PROJECT = 'wp-themes';

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		add_action( 'wporg_translate_import_or_update_theme', array( $this, 'import_or_update_theme_on_status_change' ) );

		$language_pack_build_trigger = new Language_Pack\Build_Trigger();
		$language_pack_build_trigger->register_events();

		$language_pack_build_listener = new Language_Pack\Build_Listener(
			Language_Pack\Build_Trigger::HOOK
		);
		$language_pack_build_listener->register_events();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			$this->register_cli_commands();
		}
	}

	/**
	 * Starts the import of a theme.
	 *
	 * Gets triggered by the cron API and the hook `wporg_translate_import_or_update_theme`.
	 *
	 * @param array $args Arguments from the job. Should include the slug and the version
	 *                    of the theme.
	 * @return bool False on failure, true on success.
	 */
	public function import_or_update_theme_on_status_change( $args ) {
		$time = date( 'r' );
		$message = "_Time: {$time}_\nImport of theme {$args['theme']} {$args['version']} in process...\n";

		// Import in a separate process.
		$cmd = WPORGTRANSLATE_WPCLI . ' wporg-translate set-theme-project ' . escapeshellarg( $args['theme'] ) . ' ' . escapeshellarg( $args['version'] );
		$output = '';
		$return_var = 0;
		exec( $cmd, $output, $return_var );
		if ( $return_var ) {
			$message .= "\tFailure: " . implode( "\n\t", $output ) . "\n";
		} else {
			$message .= "\t" . implode( "\n\t", $output ) . "\n";
			$message .= 'Import was successfully processed.';
		}

		$attachment = [
			'title'      => "Import of {$args['theme']}",
			'title_link' => "https://translate.wordpress.org/projects/wp-themes/{$args['theme']}",
			'text'       => $message,
			'fallback'   => "Theme {$args['theme']} was imported.",
			'color'      => '#82878c',
			'mrkdwn_in'  => [ 'text' ],
		];
		$this->slack( $attachment );

		return true;
	}

	/**
	 * Registers CLI commands if WP-CLI is loaded.
	 */
	public function register_cli_commands() {
		WP_CLI::add_command( 'wporg-translate set-theme-project', __NAMESPACE__ . '\CLI\Set_Theme_Project' );
	}

	/**
	 * Returns whether a project path belongs to the themes project.
	 *
	 * @param string $path Path of a project.
	 *
	 * @return bool True if it's a theme, false if not.
	 */
	public static function project_is_theme( $path ) {
		if ( empty( $path ) ) {
			return false;
		}

		$path = '/' . trim( $path, '/' ) . '/';
		if ( false === strpos( $path, '/' . self::GP_MASTER_PROJECT . '/' ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sends a notifcation to the the Slack channel.
	 *
	 * @param array $attachment The attachment of a notification.
	 */
	private function slack( $attachment ) {
		if ( ! defined( 'GLOTPRESS_SLACK_WEBHOOK' ) ) {
			return;
		}

		require_once API_WPORGPATH . 'includes/slack-config.php';
		$send = new \Dotorg\Slack\Send( GLOTPRESS_SLACK_WEBHOOK );
		$send->add_attachment( $attachment );
		$send->set_username( 'Theme Imports' );
		$send->send( '#meta-language-packs' );
	}
}

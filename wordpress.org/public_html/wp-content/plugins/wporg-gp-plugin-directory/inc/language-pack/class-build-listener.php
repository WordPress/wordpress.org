<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\Language_Pack;

class Build_Listener {

	/**
	 * The name of the scheduled action.
	 *
	 * @var string
	 */
	private $hook;

	/**
	 * Constructor.
	 *
	 * @param string $hook The name of the scheduled action.
	 */
	public function __construct( $hook ) {
		$this->hook = $hook;
	}

	/**
	 * Registers actions and filters.
	 */
	public function register_events() {
		add_action( $this->hook, [ $this, 'run_build' ] );
	}

	/**
	 * Runs the WP-CLI command to generate a plugin language pack.
	 *
	 * @param array $args Arguments from the job. Should include the slug of a plugin.
	 * @return bool False on failure, true on success.
	 */
	public function run_build( $args ) {
		if ( ! defined( 'WPORGTRANSLATE_WPCLI' ) ) {
			return false;
		}

		if ( ! isset( $args['plugin'] ) ) {
			return false;
		}

		$timestamp = time();
		$message   = '';

		// Build in a separate process.
		$cmd        = WPORGTRANSLATE_WPCLI . ' wporg-translate language-pack generate plugin ' . escapeshellarg( $args['plugin'] ) . ' 2>&1';
		$output     = [];
		$return_var = 0;
		exec( $cmd, $output, $return_var );
		$output = array_filter( $output, fn( $line ) => ! str_ends_with( $line, ', no translations.' ) );
		if ( $return_var ) {
			$message .= 'Failure: ' . implode( "\n", $output ) . "\n";
		} else {
			$message .= implode( "\n", $output ) . "\n";
		}

		$message = trim( $message );
		if ( ! $message ) {
			$message = "No language packs for {$args['plugin']} generated.";
		}

		$attachment = [
			'title'      => "Language packs for {$args['plugin']}",
			'title_link' => "https://translate.wordpress.org/projects/wp-plugins/{$args['plugin']}",
			'text'       => $message,
			'fallback'   => "Language packs for {$args['plugin']} were processed.",
			'color'      => '#c32283',
			'mrkdwn_in'  => [ 'text' ],
			'ts'         => $timestamp,
		];
		$this->slack( $attachment );

		return true;
	}

	/**
	 * Sends a notifcation to the Slack channel.
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
		$send->set_username( 'Plugin Language Packs' );
		$send->set_icon( ':package:' );
		$send->send( '#meta-language-packs' );
	}
}

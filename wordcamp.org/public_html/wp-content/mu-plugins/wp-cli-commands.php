<?php

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

$wp_cli_commands = glob( __DIR__ . '/wp-cli-commands/*.php' );

if ( is_array( $wp_cli_commands ) ) {
	foreach ( $wp_cli_commands as $command ) {
		require_once( $command );
	}
}

WP_CLI::add_command( 'wc-misc',    'WordCamp_CLI_Miscellaneous' );
WP_CLI::add_command( 'wc-rewrite', 'WordCamp_CLI_Rewrite_Rules' );
WP_CLI::add_command( 'wc-rest',    'WordCamp_CLI_REST_API'      );
WP_CLI::add_command( 'wc-users',   'WordCamp_CLI_Users'         );

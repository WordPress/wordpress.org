<?php
namespace WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Clients\GitHub;

/**
 * Runs Block Directory end-to-end tests.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Block_e2e {
	const GITHUB_BLOCK_E2E_REPO = 'WordPress/block-directory-e2e';

	public static function run( $plugin ) {
		$plugin = Plugin_Directory::get_plugin_post( $plugin );
		if ( ! $plugin ) {
			return false;
		}

		$api = GitHub::api(
			'/repos/' . self::GITHUB_BLOCK_E2E_REPO . '/dispatches',
			json_encode([
				'event_type'     => $plugin->post_title,
				'client_payload' => [
					'slug'       => $plugin->post_name,
				],
			])
		);

		// Upon failure a message is returned, success returns nothing.
		return empty( $api );
	}

}
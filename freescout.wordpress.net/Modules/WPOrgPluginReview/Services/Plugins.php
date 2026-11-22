<?php
/**
 * Plugins as they are now on WordPress.org.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

use App\Conversation;
use Modules\WPOrgSidebar\Services\Client;

/**
 * Asks api.wordpress.org's plugin-review.php for a plugin by its ID, or its slug for reviews that don't carry the ID: its
 * current name, slug, and status, its ZIP, and who submitted it.
 *
 * Signs requests with WPOrgSidebar's client, like the sidebar's panels.
 */
final class Plugins {

	/**
	 * Endpoint, relative to the API's base URL.
	 *
	 * @var string
	 */
	private const ENDPOINT = 'plugin-review.php';

	/**
	 * Timeout, in seconds; an agent is waiting.
	 *
	 * @var int
	 */
	private const TIMEOUT = 5;

	/**
	 * How long an answer is reused, in minutes.
	 *
	 * @var int
	 */
	private const CACHE_MINUTES = 1;

	/**
	 * A plugin of a conversation's reviews.
	 *
	 * @param int          $plugin_id    The plugin's ID on WordPress.org, 0 if the review doesn't say.
	 * @param string       $slug         The plugin's slug at the time of the review, for when there's no ID.
	 * @param Conversation $conversation The conversation; the endpoint only answers for the plugins team's mailbox.
	 * @return array|null Null if WordPress.org has no such plugin, or doesn't answer for the mailbox.
	 *
	 * @throws \RuntimeException If the request fails.
	 */
	public static function get( int $plugin_id, string $slug, Conversation $conversation ): ?array {
		$mailbox = $conversation->mailbox;
		$payload = array(
			'plugin_id' => $plugin_id,
			'slug'      => $plugin_id ? '' : $slug,
			'mailbox'   => array(
				'name'  => (string) ( $mailbox->name ?? '' ),
				'email' => (string) ( $mailbox->email ?? '' ),
			),
		);

		$response = \Cache::remember(
			'wporgpluginreview.plugin.' . md5( $plugin_id . '|' . $payload['slug'] . '|' . $payload['mailbox']['email'] ),
			self::CACHE_MINUTES,
			static function () use ( $payload ): array {
				return self::client()->post( self::ENDPOINT, $payload );
			}
		);

		return is_array( $response['plugin'] ?? null ) ? $response['plugin'] : null;
	}

	/**
	 * Whether requests can be signed.
	 *
	 * @return bool
	 */
	public static function is_configured(): bool {
		return class_exists( Client::class ) && self::client()->is_configured();
	}

	/**
	 * The client: the one tests bound, or one from the module's configuration.
	 *
	 * @return Client
	 */
	private static function client(): Client {
		if ( app()->bound( Client::class ) ) {
			return app( Client::class );
		}

		return new Client( (string) config( 'wporgpluginreview.api_url' ), (string) config( 'wporgpluginreview.secret' ), self::TIMEOUT );
	}
}

<?php
/**
 * Looks up a plugin's review conversations through the API.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Services;

/**
 * Adds a `reviewPluginId` parameter to the API & Webhooks module's conversation list, from the index of review emails.
 */
final class PluginConversations {

	/**
	 * The parameter.
	 *
	 * @var string
	 */
	public const PARAMETER = 'reviewPluginId';

	/**
	 * Limits the conversation list query to the conversations with a review email about the plugin.
	 *
	 * @param mixed $query   Query for the conversations, with the module's other parameters applied.
	 * @param mixed $request Request.
	 * @return mixed
	 */
	public static function filter( mixed $query, mixed $request ): mixed {
		if ( ! is_object( $query ) || ! method_exists( $query, 'getQuery' ) || ! is_object( $request ) || ! method_exists( $request, 'input' ) ) {
			return $query;
		}

		$plugin_id = $request->input( self::PARAMETER );

		if ( null === $plugin_id || '' === $plugin_id ) {
			return $query;
		}

		// Ignoring it would list every conversation as the plugin's.
		if ( ! is_scalar( $plugin_id ) || ! ctype_digit( (string) $plugin_id ) || (int) $plugin_id <= 0 ) {
			abort( 400, 'The `' . self::PARAMETER . '` parameter takes a plugin ID, a positive number.' );
		}

		$query->whereIn(
			'conversations.id',
			static function ( $reviews ) use ( $plugin_id ): void {
				$reviews->select( 'conversation_id' )->from( Review::TABLE )->where( 'plugin_id', (int) $plugin_id );
			}
		);

		return $query;
	}
}

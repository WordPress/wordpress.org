<?php
/**
 * Guideline labels for published plugins.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Plugin_Directory;

use WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API;

/**
 * Marks a published plugin as not meeting specific Plugin Directory guidelines, without closing it.
 *
 * Every label is stored twice: as a `plugin_guideline_label` term, for search and
 * filtering, and as an entry in the `_guideline_labels` post meta, for its details.
 * Only this class writes either, so the two stay in sync.
 *
 * @package WordPressdotorg\Plugin_Directory
 */
class Guideline_Labels {

	/**
	 * The taxonomy holding one term per label reason.
	 *
	 * @var string
	 */
	const TAXONOMY = 'plugin_guideline_label';

	/**
	 * The post meta key holding the label details, keyed by reason.
	 *
	 * @var string
	 */
	const META_KEY = '_guideline_labels';

	/**
	 * The detailed plugin guidelines.
	 *
	 * @var string
	 */
	const GUIDELINES_URL = 'https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/';

	/**
	 * Returns the reasons a plugin can be labeled for, keyed by slug.
	 *
	 * @return array {
	 *     @type int    $guideline The guideline number.
	 *     @type string $url       The guideline's URL.
	 *     @type string $message   The public sentence describing the issue.
	 *     @type string $detail    Optional. A public sentence explaining the consequences.
	 * }
	 */
	public static function get_reasons(): array {
		return array(
			'trialware'                => array(
				'guideline' => 5,
				'url'       => self::GUIDELINES_URL . '#5-trialware-is-not-permitted',
				'message'   => __( 'Locks built-in features behind a payment.', 'wporg-plugins' ),
				'detail'    => '',
			),
			'insubstantial-service'    => array(
				'guideline' => 6,
				'url'       => self::GUIDELINES_URL . '#6-software-as-a-service-is-permitted',
				'message'   => __( 'Relies on a service that doesn’t provide substantial functionality of its own.', 'wporg-plugins' ),
				'detail'    => '',
			),
			'tracking-without-consent' => array(
				'guideline' => 7,
				'url'       => self::GUIDELINES_URL . '#7-plugins-may-not-track-users-without-their-consent',
				'message'   => __( 'Contacts external servers or collects data without asking first.', 'wporg-plugins' ),
				'detail'    => '',
			),
			'external-installs'        => array(
				'guideline' => 8,
				'url'       => self::GUIDELINES_URL . '#8-plugins-may-not-send-executable-code-via-third-party-systems',
				'message'   => __( 'Installs or updates software from servers other than WordPress.org.', 'wporg-plugins' ),
				'detail'    => __( 'Software installed this way isn’t reviewed by the WordPress.org Plugins Team.', 'wporg-plugins' ),
			),
			'unrequested-credits'      => array(
				'guideline' => 10,
				'url'       => self::GUIDELINES_URL . '#10-plugins-may-not-embed-external-links-or-credits-on-the-public-site-without-explicitly-asking-the-users-permission',
				'message'   => __( 'Adds links or credits to your site’s public pages without asking.', 'wporg-plugins' ),
				'detail'    => '',
			),
			'admin-hijacking'          => array(
				'guideline' => 11,
				'url'       => self::GUIDELINES_URL . '#11-plugins-should-not-hijack-the-admin-dashboard',
				'message'   => __( 'Shows dashboard notices or ads beyond what the guidelines allow.', 'wporg-plugins' ),
				'detail'    => '',
			),
			'bundled-core-libraries'   => array(
				'guideline' => 13,
				'url'       => self::GUIDELINES_URL . '#13-plugins-must-use-wordpress-default-libraries',
				'message'   => __( 'Bundles its own copies of libraries WordPress already includes.', 'wporg-plugins' ),
				'detail'    => '',
			),
		);
	}

	/**
	 * Labels a plugin, or updates the details of an existing label.
	 *
	 * Updating a label keeps when and by whom it was first added.
	 *
	 * @param \WP_Post $post   The plugin post.
	 * @param string   $reason The reason slug, from get_reasons().
	 * @param array    $args   {
	 *     Optional. The label details.
	 *
	 *     @type string $since_version The first version the label applies to. Defaults to the current version.
	 *     @type string $note          An internal note. Never displayed publicly.
	 * }
	 * @return bool Whether the plugin carries the label.
	 */
	public static function set( \WP_Post $post, string $reason, array $args = array() ): bool {
		if ( 'plugin' !== $post->post_type || ! isset( self::get_reasons()[ $reason ] ) ) {
			return false;
		}

		$labels   = self::get_meta( $post );
		$existing = $labels[ $reason ] ?? null;
		$entry    = array(
			'since_version' => sanitize_text_field( (string) ( $args['since_version'] ?? $existing['since_version'] ?? '' ) ),
			'labeled_at'    => $existing['labeled_at'] ?? time(),
			'labeled_by'    => $existing['labeled_by'] ?? get_current_user_id(),
			'note'          => sanitize_textarea_field( (string) ( $args['note'] ?? $existing['note'] ?? '' ) ),
		);

		if ( '' === $entry['since_version'] ) {
			$entry['since_version'] = (string) get_post_meta( $post->ID, 'version', true );
		}

		$terms = wp_set_object_terms( $post->ID, $reason, self::TAXONOMY, true );
		if ( is_wp_error( $terms ) ) {
			return false;
		}

		if ( $existing === $entry ) {
			return true;
		}

		$labels[ $reason ] = $entry;
		update_post_meta( $post->ID, self::META_KEY, $labels );

		$log = sprintf(
			'Guideline label %1$s: %2$s (Guideline %3$d), since version %4$s.',
			$existing ? 'updated' : 'added',
			$reason,
			self::get_reasons()[ $reason ]['guideline'],
			$entry['since_version'] ?: 'unknown'
		);
		if ( '' !== $entry['note'] ) {
			$log .= "\n\nNote: " . $entry['note'];
		}

		Tools::audit_log( $log, $post );

		Plugins_Info_API::flush_plugin_information_cache( $post->post_name );

		return true;
	}

	/**
	 * Removes a label from a plugin.
	 *
	 * Accepts reasons no longer in the registry, so stale labels can be cleared.
	 *
	 * @param \WP_Post $post   The plugin post.
	 * @param string   $reason The reason slug.
	 * @return bool Whether a label was removed.
	 */
	public static function remove( \WP_Post $post, string $reason ): bool {
		$labels = self::get_meta( $post );

		if ( ! isset( $labels[ $reason ] ) && ! has_term( $reason, self::TAXONOMY, $post ) ) {
			return false;
		}

		$removed = wp_remove_object_terms( $post->ID, $reason, self::TAXONOMY );
		if ( is_wp_error( $removed ) ) {
			return false;
		}

		unset( $labels[ $reason ] );
		if ( $labels ) {
			update_post_meta( $post->ID, self::META_KEY, $labels );
		} else {
			delete_post_meta( $post->ID, self::META_KEY );
		}

		Tools::audit_log( sprintf( 'Guideline label removed: %s.', $reason ), $post );

		Plugins_Info_API::flush_plugin_information_cache( $post->post_name );

		return true;
	}

	/**
	 * Returns a plugin's labels merged with their registry data, in registry order.
	 *
	 * Internal notes are left out, so the result is safe for display and the API.
	 *
	 * @param \WP_Post $post The plugin post.
	 * @return array {
	 *     The labels, keyed by reason. Each one has the get_reasons() fields, plus:
	 *
	 *     @type string $reason        The reason slug.
	 *     @type string $since_version The first version the label applies to.
	 *     @type int    $labeled_at    Unix timestamp of when the label was added.
	 *     @type int    $labeled_by    The ID of the user who added the label.
	 * }
	 */
	public static function get( \WP_Post $post ): array {
		$meta   = self::get_meta( $post );
		$labels = array();

		foreach ( self::get_reasons() as $reason => $data ) {
			if ( ! isset( $meta[ $reason ] ) ) {
				continue;
			}

			$labels[ $reason ] = array_merge(
				$data,
				array(
					'reason'        => $reason,
					'since_version' => (string) $meta[ $reason ]['since_version'],
					'labeled_at'    => (int) $meta[ $reason ]['labeled_at'],
					'labeled_by'    => (int) $meta[ $reason ]['labeled_by'],
				)
			);
		}

		return $labels;
	}

	/**
	 * Returns a plugin's internal label notes, keyed by reason.
	 *
	 * @param \WP_Post $post The plugin post.
	 * @return array The notes.
	 */
	public static function get_notes( \WP_Post $post ): array {
		return array_map(
			static function ( array $entry ): string {
				return (string) ( $entry['note'] ?? '' );
			},
			self::get_meta( $post )
		);
	}

	/**
	 * Returns the stored label entries, keyed by reason.
	 *
	 * @param \WP_Post $post The plugin post.
	 * @return array The entries.
	 */
	private static function get_meta( \WP_Post $post ): array {
		$meta = get_post_meta( $post->ID, self::META_KEY, true );

		return is_array( $meta ) ? $meta : array();
	}
}

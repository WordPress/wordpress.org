<?php
/**
 * Lets a locale's editors mark editor requests for that locale as done, and
 * shows on each request who marked it done.
 *
 * @package WordPressdotorg\I18nTeams
 */

namespace WordPressdotorg\I18nTeams\Editor_Requests;

use GP_Locales;
use function WordPressdotorg\I18nTeams\get_locales;
use function WordPressdotorg\I18nTeams\Locales\get_locale_site_id;

/**
 * Roles on a locale site that decide editor requests for that locale.
 */
const EDITOR_ROLES = [ 'general_translation_editor', 'locale_manager' ];

/**
 * Inits the hooks.
 */
function bootstrap(): void {
	// o2 asks 'edit_post' when it adds the "Mark as done" control (priority 15).
	add_filter( 'o2_filter_post_actions', __NAMESPACE__ . '\allow_locale_editors', 14 );
	add_filter( 'o2_filter_post_actions', __NAMESPACE__ . '\disallow_locale_editors', 16 );

	// And twice when it saves a new state: in its write API, then in the resolved posts callback.
	add_action( 'o2_write_api', __NAMESPACE__ . '\allow_locale_editors_to_save_resolution' );

	// o2 adds its log of state changes to the post at priority 10.
	add_filter( 'o2_post_fragment', __NAMESPACE__ . '\add_resolution_line', 11, 2 );
}

/**
 * Answers o2's 'edit_post' checks for the post in a request to change its resolution.
 *
 * The request ends after o2's callback, so this lasts for the rest of it.
 */
function allow_locale_editors_to_save_resolution(): void {
	$post_id = get_resolution_request_post_id();
	if ( ! $post_id ) {
		return;
	}

	add_filter(
		'map_meta_cap',
		function ( $caps, $cap, $user_id, $args ) use ( $post_id ) {
			$post = get_checked_post( $cap, $args );
			if ( ! $post || $post->ID !== $post_id ) {
				return $caps;
			}

			return map_edit_post_for_locale_editors( $caps, $cap, $user_id, $args );
		},
		10,
		4
	);
}

/**
 * Gets the post in an o2 write API request to change a post's resolution.
 *
 * The request's nonce is checked by o2, before it acts on the message.
 *
 * @return int Post ID, or 0 if the request is something else.
 */
function get_resolution_request_post_id(): int {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Only matched against below; o2 verifies the nonce.
	$method  = isset( $_REQUEST['method'] ) && is_string( $_REQUEST['method'] ) ? strtolower( wp_unslash( $_REQUEST['method'] ) ) : '';
	$message = isset( $_REQUEST['message'] ) && is_string( $_REQUEST['message'] ) ? json_decode( wp_unslash( $_REQUEST['message'] ) ) : null;
	// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

	if ( 'patch' !== $method || ! is_object( $message ) || 'post' !== ( $message->type ?? '' ) ) {
		return 0;
	}

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- o2's message properties.
	if ( 'o2_resolved_posts' !== ( $message->pluginData->callback ?? '' ) ) {
		return 0;
	}

	// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- o2's message properties.
	return (int) ( $message->postID ?? 0 );
}

/**
 * Starts answering o2's 'edit_post' check for locale editors.
 *
 * @param mixed $value Filter value, returned unchanged.
 * @return mixed
 */
function allow_locale_editors( $value = null ) {
	add_filter( 'map_meta_cap', __NAMESPACE__ . '\map_edit_post_for_locale_editors', 10, 4 );

	return $value;
}

/**
 * Stops answering o2's 'edit_post' check for locale editors.
 *
 * @param mixed $value Filter value, returned unchanged.
 * @return mixed
 */
function disallow_locale_editors( $value = null ) {
	remove_filter( 'map_meta_cap', __NAMESPACE__ . '\map_edit_post_for_locale_editors', 10 );

	return $value;
}

/**
 * Lets a locale's editors pass 'edit_post' for a request for their locale.
 *
 * @param string[] $caps    Primitive capabilities required.
 * @param string   $cap     Capability being checked.
 * @param int      $user_id User ID.
 * @param array    $args    Context, the post or its ID first.
 * @return string[]
 */
function map_edit_post_for_locale_editors( $caps, $cap, $user_id, $args ) {
	$post = get_checked_post( $cap, $args );
	if ( $user_id && $post && is_locale_editor_for_request( (int) $user_id, $post->ID ) ) {
		return [ 'exist' ];
	}

	return $caps;
}

/**
 * Gets the post an 'edit_post' check is for.
 *
 * @param string $cap  Capability being checked.
 * @param array  $args Context, the post or its ID first.
 * @return \WP_Post|null The post, or null if the check is for something else.
 */
function get_checked_post( $cap, $args ) {
	if ( 'edit_post' !== $cap || empty( $args[0] ) ) {
		return null;
	}

	return get_post( $args[0] );
}

/**
 * Whether a user is a GTE or locale manager of the one locale a request is for.
 *
 * A request for several locales, or for a cross-locale PTE, stays with the
 * requester and the site's editors.
 *
 * @param int $user_id User ID.
 * @param int $post_id Post ID.
 * @return bool
 */
function is_locale_editor_for_request( int $user_id, int $post_id ): bool {
	if ( ! is_polyglots_site() || ! has_tag( 'editor-requests', $post_id ) ) {
		return false;
	}

	// Cross-locale PTEs are appointed by the Global Mentors, not by a locale's editors.
	if ( has_tag( 'clpte', $post_id ) ) {
		return false;
	}

	$locales = get_request_locales( $post_id );
	if ( 1 !== count( $locales ) ) {
		return false;
	}

	$site_id = get_locale_site_id( $locales[0] );
	if ( ! $site_id ) {
		return false;
	}

	global $wpdb;
	$roles = get_user_meta( $user_id, $wpdb->get_blog_prefix( $site_id ) . 'capabilities', true );

	return is_array( $roles ) && array_intersect_key( array_filter( $roles ), array_flip( EDITOR_ROLES ) );
}

/**
 * Whether the current site is the Polyglots site, where editor requests are posted.
 *
 * @return bool
 */
function is_polyglots_site(): bool {
	$site = get_site();

	return $site && 'make.wordpress.org' === $site->domain && '/polyglots/' === $site->path;
}

/**
 * Gets the locales a request is tagged with.
 *
 * A variant counts as its parent locale, so a request tagged with a locale and
 * its variant is for one locale.
 *
 * @param int $post_id Post ID.
 * @return \GP_Locale[]
 */
function get_request_locales( int $post_id ): array {
	static $locales_by_wp_locale;

	if ( ! isset( $locales_by_wp_locale ) ) {
		$locales_by_wp_locale = array_column( get_locales(), null, 'wp_locale' );
	}

	$locales = [];
	foreach ( get_the_tags( $post_id ) ?: [] as $tag ) {
		$locale = $locales_by_wp_locale[ $tag->name ] ?? $locales_by_wp_locale[ $tag->slug ] ?? null;
		if ( ! $locale ) {
			continue;
		}

		// Editors are only assigned to the parent locale, and their role covers its variants.
		if ( ! empty( $locale->root_slug ) ) {
			$locale = GP_Locales::by_slug( $locale->root_slug ) ?: $locale;
		}

		$locales[ $locale->wp_locale ] = $locale;
	}

	return array_values( $locales );
}

/**
 * Shows on a request who marked it as done, and when.
 *
 * Only o2's menu that opens on hover lists it, so it can't be seen on a phone.
 * The line repeats the latest entry of o2's log, as o2 formats it.
 *
 * @param array $fragment o2's data for the post.
 * @param int   $post_id  Post ID.
 * @return array
 */
function add_resolution_line( $fragment, $post_id ) {
	if ( ! is_polyglots_site() || ! has_tag( 'editor-requests', $post_id ) ) {
		return $fragment;
	}

	$post_meta = $fragment['postMeta'] ?? [];
	if ( 'resolved' !== ( $post_meta['resolvedPostsPostState'] ?? '' ) || empty( $post_meta['resolvedPostsAuditLogs'] ) || ! is_array( $post_meta['resolvedPostsAuditLogs'] ) ) {
		return $fragment;
	}

	$entry = end( $post_meta['resolvedPostsAuditLogs'] );
	if ( ! is_array( $entry ) || empty( $entry['log'] ) || empty( $entry['timestamp'] ) ) {
		return $fragment;
	}

	// Small and muted, with the theme's own classes, like the post's other meta.
	$line = sprintf(
		'<p class="wporg-editor-request-resolution has-small-font-size has-charcoal-4-color"><span aria-hidden="true">&#10003;</span> %s</p>',
		sprintf(
			/* translators: 1: An entry of the post's log, like "Jane marked this done". 2: Date. */
			esc_html__( '%1$s on %2$s', 'wporg' ),
			wp_kses_post( $entry['log'] ),
			esc_html( wp_date( get_option( 'date_format' ), (int) $entry['timestamp'] ) )
		)
	);

	// Above o2's "Mark as done" control, which o2 adds to the end of the content, after anything the post itself has.
	$content = $fragment['contentFiltered'] ?? '';
	if ( preg_match_all( '/<nav\b[^>]*\bo2-post-footer-actions\b/', $content, $matches, PREG_OFFSET_CAPTURE ) ) {
		$fragment['contentFiltered'] = substr_replace( $content, $line, end( $matches[0] )[1], 0 );
	} else {
		$fragment['contentFiltered'] = $content . $line;
	}

	return $fragment;
}

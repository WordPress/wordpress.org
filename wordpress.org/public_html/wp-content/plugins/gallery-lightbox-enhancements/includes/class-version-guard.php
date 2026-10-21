<?php
/**
 * Determines whether the polyfill should run on the current install.
 *
 * @package GalleryLightboxEnhancements
 */

namespace GalleryLightboxEnhancements;

defined( 'ABSPATH' ) || exit;

/**
 * Self-disable guard: returns false once the upstream Gutenberg PRs ship.
 */
class Version_Guard {

	/**
	 * Lowest Gutenberg plugin version known to ship the upstream changes.
	 *
	 * Empty string means "no Gutenberg release has shipped the features
	 * yet". Set this to the Gutenberg release tag (e.g. `'21.5.0'`)
	 * that lands the upstream PRs, and the polyfill steps aside on
	 * installs running that version of the Gutenberg plugin or newer.
	 *
	 * @var string
	 */
	const CORE_VERSION_WITH_FEATURES = '';

	/**
	 * Lowest WordPress core version that ships the upstream changes.
	 *
	 * Empty string means "no core release has shipped the features
	 * yet". Set this to the WP version tag (e.g. `'7.0'`) that bundles
	 * the merged PRs, and the polyfill steps aside on every install
	 * running that core version or newer — including sites that don't
	 * run the Gutenberg plugin at all.
	 *
	 * @var string
	 */
	const WP_VERSION_WITH_FEATURES = '';

	/**
	 * Whether the polyfill should be loaded for the current request.
	 *
	 * The guard checks both core and Gutenberg because the upstream
	 * fixes can land in either timeline: on wordpress.org we run trunk
	 * + the latest Gutenberg release, so Gutenberg ships first; on
	 * vanilla installs WordPress core ships eventually and Gutenberg
	 * may not be active at all. A self-disable that only watched
	 * `GUTENBERG_VERSION` would keep the polyfill running forever on
	 * core-only sites.
	 *
	 * @return bool
	 */
	public static function should_load() {
		global $wp_version;

		// Core already ships the features — step aside regardless of Gutenberg.
		if ( '' !== self::WP_VERSION_WITH_FEATURES
			&& isset( $wp_version )
			&& version_compare( $wp_version, self::WP_VERSION_WITH_FEATURES, '>=' )
		) {
			return false;
		}

		// Gutenberg plugin running and already ships the features — step aside.
		if ( '' !== self::CORE_VERSION_WITH_FEATURES
			&& defined( 'GUTENBERG_VERSION' )
			&& version_compare( GUTENBERG_VERSION, self::CORE_VERSION_WITH_FEATURES, '>=' )
		) {
			return false;
		}

		return true;
	}
}

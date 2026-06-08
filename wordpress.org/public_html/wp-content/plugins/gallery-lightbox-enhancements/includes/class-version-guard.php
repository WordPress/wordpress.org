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
	 * Empty string means "no version has shipped the features yet" — the
	 * polyfill stays active on every install. When #77477 (and eventually
	 * #77615) land in core, replace this with the Gutenberg release tag
	 * that contains them (e.g. `'21.5.0'`) and the plugin self-disables
	 * starting with that version.
	 *
	 * @var string
	 */
	const CORE_VERSION_WITH_FEATURES = '';

	/**
	 * Whether the polyfill should be loaded for the current request.
	 *
	 * @return bool
	 */
	public static function should_load() {
		// Vanilla WordPress core: plugin is the only source of these features.
		if ( ! defined( 'GUTENBERG_VERSION' ) ) {
			return true;
		}

		// Sentinel: no upstream release tracked yet, polyfill stays on.
		if ( '' === self::CORE_VERSION_WITH_FEATURES ) {
			return true;
		}

		return version_compare(
			GUTENBERG_VERSION,
			self::CORE_VERSION_WITH_FEATURES,
			'<'
		);
	}
}

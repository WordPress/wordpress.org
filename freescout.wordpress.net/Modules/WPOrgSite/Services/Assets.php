<?php
/**
 * Scripts and stylesheets that core bundles.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

/**
 * Leaves files that aren't there out of core's bundles.
 *
 * Core's Minify throws on a file that doesn't exist, and core then leaves the whole bundle out of the page: a module's
 * public directory that's out of date leaves every page without jQuery.
 */
final class Assets {

	/**
	 * The files that exist, of the paths in a bundle; only modules' files are checked, so core still regenerates a
	 * missing vars.js when Minify throws on it.
	 *
	 * @param array<int, mixed> $files Paths under public/, or URLs.
	 * @return array<int, mixed> The files, without modules' files that don't exist.
	 */
	public static function existing( array $files ): array {
		$existing = array();
		foreach ( $files as $file ) {
			if ( ! is_string( $file ) || ! str_starts_with( $file, '/modules/' ) || file_exists( public_path() . $file ) ) {
				$existing[] = $file;
				continue;
			}

			\Log::error( '[WPOrgSite] Left a file that doesn’t exist out of the page: ' . $file );
		}

		return $existing;
	}
}

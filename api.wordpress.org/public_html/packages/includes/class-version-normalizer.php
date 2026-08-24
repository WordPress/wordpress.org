<?php
/**
 * Version normalizer for WordPress to Composer version mapping.
 *
 * @package WordPressdotorg\API\Composer
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Composer;

/**
 * Normalizes WordPress plugin/theme version strings into Composer-compatible versions.
 *
 * Handles the common patterns found in WordPress.org version strings and rejects
 * strings that cannot be represented as valid Composer versions.
 */
class Version_Normalizer {

	/**
	 * Regex matching valid version patterns.
	 *
	 * Accepts 1-4 numeric segments with optional pre-release suffixes:
	 * alpha, beta, a, b, rc, p, patch, dev, pl (case-insensitive).
	 */
	const VERSION_PATTERN = '/^v?(\d{1,5})(?:\.(\d+))?(?:\.(\d+))?(?:\.(\d+))?(?:[.-]?(alpha|beta|a|b|rc|p|patch|dev|pl)\.?(\d*))?$/i';

	/**
	 * Normalize a WordPress version string into a Composer-compatible version.
	 *
	 * @param string $version The raw version string.
	 * @return string|false The normalized version, or false if invalid.
	 */
	public static function normalize( string $version ): string|false {
		$version = trim( $version );

		if ( '' === $version ) {
			return false;
		}

		// Map trunk to dev-trunk.
		if ( 'trunk' === strtolower( $version ) ) {
			return 'dev-trunk';
		}

		if ( ! preg_match( self::VERSION_PATTERN, $version, $matches ) ) {
			return false;
		}

		// Build the numeric part.
		$major = $matches[1];
		// Test for an unmatched group rather than a falsy one, or a "0" segment would be dropped.
		$minor = ( isset( $matches[2] ) && '' !== $matches[2] ) ? $matches[2] : '0';
		$patch = ( isset( $matches[3] ) && '' !== $matches[3] ) ? $matches[3] : null;
		$extra = ( isset( $matches[4] ) && '' !== $matches[4] ) ? $matches[4] : null;

		$normalized = $major . '.' . $minor;
		if ( null !== $patch ) {
			$normalized .= '.' . $patch;
		}
		if ( null !== $extra ) {
			$normalized .= '.' . $extra;
		}

		// Append pre-release suffix if present.
		if ( ! empty( $matches[5] ) ) {
			$suffix      = self::normalize_suffix( $matches[5] );
			$num         = $matches[6] ?? '';
			$normalized .= '-' . $suffix . $num;
		}

		return $normalized;
	}

	/**
	 * Map various pre-release suffix names to canonical Composer names.
	 *
	 * @param string $suffix The raw suffix.
	 * @return string The canonical suffix.
	 */
	private static function normalize_suffix( string $suffix ): string {
		$suffix = strtolower( $suffix );

		$map = array(
			'a'  => 'alpha',
			'b'  => 'beta',
			'p'  => 'patch',
			'pl' => 'patch',
		);

		return $map[ $suffix ] ?? $suffix;
	}
}

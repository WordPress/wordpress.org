<?php
/**
 * Settings FreeScout keeps in `.env`.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

use App\Http\Controllers\SettingsController;

/**
 * Finds the settings that FreeScout saves to `.env` rather than to its options.
 *
 * The server's `.env` is read-only, so saving one fails the whole section. They're shown read-only, and not saved.
 */
final class EnvSettings {

	/**
	 * The settings of a Manage » Settings section that FreeScout saves to `.env`, core's and modules'.
	 *
	 * @param string $section Section, like `general`.
	 * @return string[] Their names, like `timezone`.
	 */
	public static function keys( string $section ): array {
		$params = app( SettingsController::class )->getSectionParams( $section, 'settings' );
		if ( ! is_array( $params ) ) {
			return array();
		}

		$keys = array();
		foreach ( $params as $key => $param ) {
			if ( is_array( $param ) && ! empty( $param['env'] ) ) {
				$keys[] = (string) $key;
			}
		}

		return $keys;
	}
}

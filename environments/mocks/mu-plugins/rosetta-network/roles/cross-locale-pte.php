<?php
/**
 * Stub of the production rosetta-network Cross_Locale_PTE class.
 *
 * The wporg-gp-rosetta-roles plugin requires this on load. On translate.wordpress.org
 * it lives in the rosetta-network mu-plugin, which isn't checked into this repo. This
 * no-op stub lets the plugin load locally — cross-locale PTE checks effectively
 * become inert (no user is ever a cross-locale PTE in the dev env).
 */

if ( ! class_exists( 'Cross_Locale_PTE' ) ) {
	class Cross_Locale_PTE {
		public static function is_cross_locale_pte( $user_id = 0, $locale_slug = '' ) {
			return false;
		}

		public static function get_cross_locale_pte_locales( $user_id = 0 ) {
			return array();
		}

		/**
		 * Filter callback for `gp_translation_set_import_status`. Production's
		 * Cross_Locale_PTE mutates the status based on the importing user's
		 * cross-locale permissions; with no cross-locale users here we pass the
		 * status through unchanged so the import doesn't clobber it with null.
		 */
		public static function gp_translation_set_import_status( $status, $entry = null, $old = null ) {
			return $status;
		}

		public function __call( $name, $args ) {
			return $args[0] ?? null;
		}

		public static function __callStatic( $name, $args ) {
			return $args[0] ?? null;
		}
	}
}

<?php
/**
 * Plugin name: GlotPress: Client-side Translation Validation
 * Description: Provides client-side JavaScript translation validation warning warnings for translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

/**
 *
 * Not all of the warnings match exactly what is in GlotPress core, some are more specific or vague.
 * Some of the GlotPress warnings are not duplicated into JS (rarely hit, or harder to reproduce).
 *
 * @TODO:
 * - Translate the warning strings
 * - Match warning error strings between GlotPress & this plugin?
 */
class WPorg_GP_JS_Translation_Warnings {

	function __construct() {
		add_action( 'wp_default_scripts', array( $this, 'replace_editor_with_our_own' ), 11 );
	}

	/**
	 * Replace the GlotPress editor script with our own which depends on the wporg-gp-editor GlotPress variant.
	 *
	 * This allows us to be output whenever `gp-editor` is.
	 *
	 * @param WP_Scripts $scripts WP_Scripts object.
	 */
	function replace_editor_with_our_own( $scripts ) {
		$query = $scripts->query( 'gp-editor', 'registered' );
		if ( ! $query ) {
			return;
		}

		$scripts->add( 'wporg-gp-editor', $query->src, $query->deps, $query->ver );
		if ( isset( $query->extra['l10n'] ) ) {
			$scripts->localize( 'wporg-gp-editor', $query->extra['l10n'][0], $query->extra['l10n'][1] );
		}

		$scripts->remove( 'gp-editor' );
		$scripts->add( 'gp-editor', plugins_url( '/wporg-gp-js-warnings.js', __FILE__ ), array( 'wporg-gp-editor', 'jquery' ), '2015-11-14' );
	}

}

function wporg_gp_js_translation_warnings() {
	global $wporg_gp_js_translation_warnings;

	if ( ! isset( $wporg_gp_js_translation_warnings ) ) {
		$wporg_gp_js_translation_warnings = new WPorg_GP_JS_Translation_Warnings();
	}

	return $wporg_gp_js_translation_warnings;
}
add_action( 'plugins_loaded', 'wporg_gp_js_translation_warnings' );

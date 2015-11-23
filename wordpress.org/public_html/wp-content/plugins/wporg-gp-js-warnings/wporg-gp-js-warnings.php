<?php
/**
 * Plugin name: GlotPress: Client-side JS translation validation
 * Plugin author: dd32
 */

/**
 * Adds client-side JS translation validation warning warnings.
 *
 * Not all of the warnings match exactly what is in GlotPress core, some are more specific or vague.
 * Some of the GlotPress warnings are not duplicated into JS (rarely hit, or harder to reproduce).
 *
 * @TODO:
 * - Translate the warning strings
 * - Match warning error strings between GlotPress & this plugin?
 *
 * @author dd32
 */
class WPorg_GP_JS_Translation_Warnings {

	function __construct() {
		add_action( 'wp_print_scripts', array( $this, 'replace_editor_with_our_own' ), 100 );
	}

	/**
	 * Replace the GlotPress editor script with our own which depends on the wporg-gp-editor GlotPress variant.
	 *
	 * This allows us to be output whenever `gp-editor` is.
	 */
	function replace_editor_with_our_own() {
		$query = wp_scripts()->query( 'gp-editor', 'registered' );
		if ( ! $query ) {
			return;
		}

		wp_register_script( 'wporg-gp-editor', $query->src, $query->deps, $query->ver );
		if ( isset( $query->extra['l10n'] ) ) {
			wp_localize_script( 'wporg-gp-editor', $query->extra['l10n'][0], $query->extra['l10n'][1] );
		}

		wp_deregister_script( 'gp-editor' );
		wp_register_script( 'gp-editor', plugins_url( '/wporg-gp-js-warnings.js', __FILE__ ), array( 'wporg-gp-editor', 'jquery' ), '2015-11-14' );
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

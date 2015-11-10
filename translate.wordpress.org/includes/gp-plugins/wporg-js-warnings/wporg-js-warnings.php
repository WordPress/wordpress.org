<?php
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
class WPORG_JS_Translation_Warnings {
	function __construct() {
		add_action( 'wp_print_scripts', array( $this, 'replace_editor_with_our_own' ), 100 );
	}

	// Replace the GlotPress editor script with our own (which depends on the editor-core GlotPress variant) this allows us to be output whenever `editor` is.
	function replace_editor_with_our_own( $scripts ) {
		global $wp_scripts;

		$query = $wp_scripts->query( 'editor', 'registered' );
		if ( ! $query ) {
			return;
		}

		wp_register_script( 'editor-core', $query->src, $query->deps, $query->ver );
		if ( isset( $query->extra['l10n'] ) ) {
			wp_localize_script( 'editor-core', $query->extra['l10n'][0], $query->extra['l10n'][1] );
		}

		wp_deregister_script( 'editor' );
		wp_register_script( 'editor', gp_url_public_root() . 'gp-plugins/wporg-js-warnings/wporg-js-warnings.js', array( 'editor-core', 'jquery' ), '2015-11-10' );

	}

}
new WPORG_JS_Translation_Warnings();


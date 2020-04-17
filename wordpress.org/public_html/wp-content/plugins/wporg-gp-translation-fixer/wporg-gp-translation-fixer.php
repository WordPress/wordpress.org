<?php
namespace WordPressDotOrg\GlotPress\Translation_Fixer;
use GP, GP_Locales;

/**
 * Plugin name: GlotPress: Translation Fixer
 * Description: Corrects common translation errors to avoid needless warnings.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

 class Plugin {

	public function instance() {
		static $instance;
		if ( ! $instance ) {
			$class = __CLASS__;
			$instance = new $class;
		}
		return $instance;
	}

	protected function __construct() {
		// Fires prior to save, and update.
		add_filter( 'gp_translation_prepare_for_save', [ $this, 'attempt_fixes' ], 10, 2 );
	}

	public function attempt_fixes( $args, $gp_translation ) {
		// If existing translation, let it go.
		if ( isset( $args['id'] ) ) {
			return $args;
		}

		// If no warnings, they're fine.
		if ( ! $args['warnings'] ) {
			return $args;
		}

		// Store a copy so we can compare later.
		$original_args   = $args;
		// Various things we need to run a warnings check.
		$original        = GP::$original->get( $args['original_id'] );
		$translation_set = GP::$translation_set->get( $args['translation_set_id'] );
		$locale          = GP_Locales::by_slug( $translation_set->locale );

		foreach ( $args['warnings'] as $i => $warnings ) {
			$translation =& $args[ 'translation_' . $i ];

			foreach ( $warnings as $code => $text ) {
				switch ( $code ) {
					case 'should_begin_on_newline':
						$translation = "\n" . ltrim( $translation );
						break;
					case 'should_end_on_newline':
						$translation = rtrim( $translation ) . "\n";
						break;
					case 'should_not_begin_on_newline':
						$translation = ltrim( $translation, "\n" );
						break;
					case 'should_not_end_on_newline':
						$translation = rtrim( $translation, "\n" );
						break;
					case 'placeholders':
						// Try replacing unicode percent signs with a ascii percent sign.
						$translation = preg_replace( '!(﹪|％)((\d+\$(?:\d+)?)?[bcdefgosuxEFGX])!', '%$2', $translation );
						break;
				}
			}
		}

		// Re-check the warnings, don't just trust we fixed it.
		if ( $original_args !== $args ) {
			$translations = [];
			foreach ( range( 0, GP::$translation->get_static( 'number_of_plural_translations' ) - 1 ) as $i ) {
				if ( isset( $args[ "translation_$i" ] ) ) {
					$translations[ $i ] = $args[ "translation_$i" ];
				}
			}

			$args['warnings'] = GP::$translation_warnings->check( $original->singular, $original->plural, $translations, $locale );

			// They're fixed!
			if ( ! $args['warnings'] ) {
				return $args;
			}
		}

		// Return the original args, either the translation has multiple problems, or we didn't fix it properly.
		return $original_args;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Plugin', 'instance' ] );

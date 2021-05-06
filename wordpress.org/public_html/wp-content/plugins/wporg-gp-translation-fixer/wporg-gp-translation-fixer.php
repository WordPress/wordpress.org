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

	public static function instance() {
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
		if ( empty( $args['warnings'] ) || ! is_array( $args['warnings'] ) ) {
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
						$translation = preg_replace( '!(﹪|％)((\d+\$(?:\d+)?)?[bcdefgosux])!i', '%$2', $translation );

						// Try replacing spaced translation type with no spaces `% 1 $ s` (Machine translated text)
						$translation = preg_replace_callback(
							'!%\s?(\d+\s?\$(?:\d+)?)?\s?[bcdefgosux]\b!i',
							function( $m ) {
								return str_replace( ' ', '', $m[0] );
							},
							$translation
						);

						// Check case of format specifer. EFGX can be both upper or lower case.
						$translation = preg_replace_callback(
							'!%(\d+\$(?:\d+)?)?([bcdosu])!i',
							function( $m ) {
								return '%' . $m[1] . strtolower( $m[2] );
							},
							$translation
						);

						break;
					case 'tags':
						// Try replacing curly quotes.
						$translation = str_replace(
							[
								'“', // &#8220; - Left double quotation mark
								'”', // &#8221; - Right double quotation mark
								'″', // &#8243; - Double Prime
							],
							'"',
							$translation
						);
						$translation = str_replace(
							[
								'‘', // &#8216; - Opening curly single quote
								'’', // &#8217; - Closing curly single quote
								'′', // &#8242; - Prime
							],
							"'",
							$translation
						);

						// Try correcting HTML tags containing extra spaces, eg </ p>, <a href="#" >, < / p>, relies upon the original having well-formed HTML.
						$translation = preg_replace(
							// Opening HTML element, Tag Name, Attributes (zero or more), Closing HTML element
							'!<\s*(/)?\s*' . '([a-z]+)\s*' . '(\s+[a-z]+=["\'][^>]+["\'])*' . '\s*>!i',
							'<$1$2$3>', // 1: Closing slash, 2: Tag, 3: Attributes
							$translation
						);

						break;
					case 'unexpected_sprintf_token': // Custom dotorg warning
						// This is reliant upon that there is another warning that requires
						// the same count of sprintf-like tokens. `Missing %s placeholder in translation.`

						$translation = preg_replace(
							// Escape any % not already escaped (preceeded by %) and followed by a non-printf-char
							// % is included to not affect a escaped placeholder
							// \d+-'# is to not affect %1$s, fixed width, and precision
							'/(?<!%)%(?![bcdefgosux%\d+\-\'#])/i',
							'%%',
							$translation
						);

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

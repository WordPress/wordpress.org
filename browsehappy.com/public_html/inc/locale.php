<?php

class Browse_Happy_Locale {

	static protected $locale;

	static protected $active_locales;

	static public $guessed = false;

	static function initialize() {
		self::hooks();

		self::$active_locales = (array) glob( get_template_directory() . '/languages/*.mo' );
		foreach ( self::$active_locales as &$mo_file )
			$mo_file = basename( $mo_file, '.mo' );
		unset( $mo_file );
		self::$active_locales[] = 'en_US';

		if ( isset( $_GET['locale'] ) )
			$get_locale = preg_replace( '/[^A-Z_-]/i', '', $_GET['locale'] );

		if ( isset( $get_locale ) && $maybe = self::check_variants( $get_locale ) )
			self::$locale = $maybe;
		elseif ( self::$locale = self::guess_locale() )
			self::$guessed = true;
		else
			self::$locale = 'en_US';
	}

	static function locale() {
		return self::$locale;
	}

	static function hooks() {
		add_action( 'locale', array( __CLASS__, 'locale' ) );
	}

	static function guess_locale() {
		if ( ! isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return;
		}

		$locales = array();
		$variant = '';

		$langs = explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

		foreach ( $langs as $lang ) {
			$lang   = str_replace( 'q=', '', $lang );
			$parts  = explode( ';', trim( $lang ) );
			$locale = $parts[0];
			$weight = empty( $parts[1] ) ? '1.0' : $parts[1];

			$locales[ $locale ] = $weight;
		}

		// Sort locales by browser-provided weighting.
		arsort( $locales, SORT_NUMERIC );

		// Find the best locale variant.
		foreach ( array_keys( $locales ) as $locale_pref ) {
			// Preference for 'en' or 'en-US' forces default language.
			if ( in_array( $locale_pref, array( 'en', 'en-US' ) ) ) {
				break;
			}

			// Check for the closest language variant.
			if ( $maybe = self::check_variants( $locale_pref ) ) {
				$variant = $maybe;
			}

			// For English, only use an exact variant, otherwise fall back to default.
			if ( 0 === strpos( $locale_pref, 'en' ) ) {
				if ( $variant && $variant != str_replace( '-', '_', $locale_pref ) ) {
					$variant = '';
				}
				break;
			}

			// Stop searching if a valid variant has been found.
			if ( $variant ) {
				break;
			}
		}

		return $variant;
	}

	static function check_variants( $locale ) {
		$locale = str_replace( '-', '_', $locale );
		$locale = explode( '_', $locale, 2 );
		if ( count( $locale ) == 1 ) {
			$lang = strtolower( $locale[0] );
			$variants = array( $lang . '_' . strtoupper( $lang ), $lang );
			$fallback = $lang . '_';
		} else {
			list( $lang, $region ) = $locale;
			$lang = strtolower( $lang );
			$region = strtoupper( $region );
			$variants = array( $lang . '_' . $region, $lang . '_' . strtoupper( $lang ), $lang );
			$fallback = $lang . '_';
		}

		foreach ( $variants as $variant ) {
			if ( in_array( $variant, self::$active_locales ) )
				return $variant;
		}

		foreach ( self::$active_locales as $active_locale ) {
			if ( 0 === strpos( $active_locale, $fallback ) )
				return $active_locale;
		}

		return false;
	}
}

Browse_Happy_Locale::initialize();

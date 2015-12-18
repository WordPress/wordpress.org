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
		if ( ! isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) )
			return;

		if ( ! preg_match_all( '/([a-z]{2,}(\-[a-z]{2,})?)/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $matches ) )
			return;

		$locales = $matches[0];

		foreach ( $locales as $locale ) {
			if ( strpos( $locale, 'en' ) === 0 )
				continue;

			if ( $maybe = self::check_variants( $locale ) )
				return $maybe;
		}
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

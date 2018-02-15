<?php
/**
 * Class Detector
 *
 * @package WordPressdotorg\LocaleDetection
 */

namespace WordPressdotorg\LocaleDetection;

/**
 * Class Detector
 */
class Detector {

	/**
	 * Whether the locale was guessed or not.
	 *
	 * @var bool
	 */
	public $guessed = false;

	/**
	 * Detected locale.
	 *
	 * @access protected
	 * @var string
	 */
	protected $locale = 'en_US';

	/**
	 * Available locales.
	 *
	 * @access protected
	 * @var array
	 */
	protected $active_locales = [
		'en_US',
	];

	/**
	 * Detects locale.
	 */
	public function __construct() {
		$this->active_locales = array_merge(
			$this->active_locales,
			(array) glob( get_template_directory() . '/languages/*.mo' ),
			(array) glob( get_stylesheet_directory() . '/languages/*.mo' )
		);
		foreach ( $this->active_locales as &$mo_file ) {
			$mo_file = basename( $mo_file, '.mo' );
		}
		unset( $mo_file );

		if ( isset( $_GET['locale'] ) ) {
			$get_locale = preg_replace( '/[^A-Z_-]/i', '', $_GET['locale'] );

			$this->locale = $this->check_variants( $get_locale ) ?: $this->locale;
		} else {
			$this->locale = $this->guess_locale() ?: $this->locale;
		}
	}

	/**
	 * Returns the locale.
	 *
	 * @return string
	 */
	public function get_locale() {
		return $this->locale;
	}

	/**
	 * Guesses the locale.
	 *
	 * @return string
	 */
	public function guess_locale() {
		$variant = '';

		if ( ! isset( $_SERVER['HTTP_ACCEPT_LANGUAGE'] ) ) {
			return $variant;
		}

		$locales   = [];
		$languages = explode( ',', $_SERVER['HTTP_ACCEPT_LANGUAGE'] );

		foreach ( $languages as $lang ) {
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
			if ( in_array( $locale_pref, [ 'en', 'en-US' ], true ) ) {
				break;
			}

			// Check for the closest language variant.
			$variant = $this->check_variants( $locale_pref );

			// For English, only use an exact variant, otherwise fall back to default.
			if ( 0 === strpos( $locale_pref, 'en' ) ) {
				if ( str_replace( '-', '_', $locale_pref ) !== $variant ) {
					$variant = '';
				}
				break;
			}

			// Stop searching if a valid variant has been found.
			if ( $variant ) {
				break;
			}
		}

		$this->guessed = !! $variant; // phpcs:ignore WordPress.WhiteSpace.OperatorSpacing

		return $variant;
	}

	/**
	 * Checks variants.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public function check_variants( $locale ) {
		$locale   = str_replace( '-', '_', $locale );
		$locale   = explode( '_', $locale, 2 );
		$variants = [];

		if ( 1 === count( $locale ) ) {
			$lang = strtolower( $locale[0] );
		} else {
			list( $lang, $region ) = $locale;

			$lang       = strtolower( $lang );
			$variants[] = $lang . '_' . strtoupper( $region );
		}

		$fallback   = $lang . '_';
		$variants[] = $lang . '_' . strtoupper( $lang );
		$variants[] = $lang;

		foreach ( $variants as $variant ) {
			if ( in_array( $variant, $this->active_locales, true ) ) {
				return $variant;
			}
		}

		foreach ( $this->active_locales as $active_locale ) {
			if ( 0 === strpos( $active_locale, $fallback ) ) {
				return $active_locale;
			}
		}

		return '';
	}
}

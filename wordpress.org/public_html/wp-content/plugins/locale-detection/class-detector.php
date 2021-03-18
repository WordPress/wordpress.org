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
	 * Name of the cookie for the locale.
	 */
	const COOKIE_NAME = 'wporg_locale';

	/**
	 * Name of the GET parameter for the locale.
	 */
	const GET_NAME = 'locale';

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
		$this->active_locales = array_merge( $this->active_locales, $this->get_active_locales() );

		$this->set_locale();
	}

	/**
	 * Sets the locale property and cookie based on the following parameters:
	 *  1. $_GET['locale']
	 *  2. $_COOKIE['wporg_locale']
	 *  3. $_SERVER['HTTP_ACCEPT_LANGUAGE']
	 */
	private function set_locale() {
		if ( ! empty( $_GET[ self::GET_NAME ] ) ) {
			$get_locale = $this->sanitize_locale( $_GET[ self::GET_NAME ] );

			$this->locale = $this->check_variants( $get_locale ) ?: $this->locale;
		} elseif ( ! empty( $_COOKIE[ self::COOKIE_NAME ] ) ) {
			$locale = $this->sanitize_locale( $_COOKIE[ self::COOKIE_NAME ] );

			if ( in_array( $locale, $this->active_locales, true ) ) {
				$this->locale = $locale;
			}
		} else {
			$this->locale = $this->guess_locale() ?: $this->locale;
		}

		if ( empty( $_COOKIE[ self::COOKIE_NAME ] ) || $this->locale !== $_COOKIE[ self::COOKIE_NAME ] ) {
			setcookie( self::COOKIE_NAME, $this->locale, time() + YEAR_IN_SECONDS, SITECOOKIEPATH, COOKIE_DOMAIN, is_ssl() );
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
	 * Returns the list of available locales.
	 *
	 * @return array
	 */
	public function get_active_locales() {
		wp_cache_add_global_groups( [ 'locale-associations' ] );

		$locales = wp_cache_get( 'locale-list', 'locale-associations' );
		if ( false === $locales ) {
			$locales = (array) $GLOBALS['wpdb']->get_col( 'SELECT locale FROM wporg_locales' );
			wp_cache_set( 'locale-list', $locales, 'locale-associations' );
		}

		return $locales;
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
		$locale   = explode( '_', $locale, 3 );
		$variants = [];

		if ( 1 === count( $locale ) ) {
			$lang = strtolower( $locale[0] );
		} elseif ( 2 === count( $locale ) ) {
			list( $lang, $region ) = $locale;

			$lang       = strtolower( $lang );
			$variants[] = $lang . '_' . strtolower( $region );
			$variants[] = $lang . '_' . strtoupper( $region );
		} else {
			list( $lang, $region, $variant ) = $locale;

			$lang       = strtolower( $lang );
			$variant    = strtolower( $variant );
			$variants[] = $lang . '_' . strtolower( $region ) . '_' . $variant;
			$variants[] = $lang . '_' . strtoupper( $region ) . '_' . $variant;
			$variants[] = $lang . '_' . strtolower( $region );
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

	/**
	 * Returns a valid locale string.
	 *
	 * @param string $locale Locale string to be sanitized.
	 * @return string
	 */
	protected function sanitize_locale( $locale ) {
		if ( ! is_string( $locale ) ) {
			return '';
		}

		return preg_replace( '/[^a-zA-Z0-9_]/', '', $locale );
	}
}

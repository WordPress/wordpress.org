<?php
/**
 * Plugin name: GlotPress: Custom Translation Warnings
 * Description: Provides custom translation warnings like mismatching URLs for translate.wordpress.org.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

class WPorg_GP_Custom_Translation_Warnings {

	/**
	 * Mapping for allowed domain changes.
	 *
	 * @var array
	 */
	private $allowed_domain_changes = array(
		// Allow links to WordPress.org to be changed to a subdomain
		'wordpress.org' => '[^.]+\.wordpress\.org',
		// Allow links to WordPress.com to be changed to a subdomain
		'wordpress.com' => '[^.]+\.wordpress\.com',
		// Allow links to Gravatar.org to be changed to a subdomain
		'en.gravatar.com' => '[^.]+\.gravatar\.com'
	);

	/**
	 * Adds a warning for changing plain-text URLs.
	 *
	 * This allows for the scheme to change, and for WordPress.org URL's to change to a subdomain.
	 *
	 * @param string $original    The orignal string.
	 * @param string $translation The translated string.
	 */
	public function warning_mismatching_urls( $original, $translation ) {
		// Any http/https/schemeless URLs which are not encased in quotation marks nor contain whitespace
		$urls_regex = '@(?<![\'"])((https?://|(?<![:\w])//)[^\s]+)(?![\'"])@i';

		preg_match_all( $urls_regex, $original, $original_urls );
		$original_urls = array_unique( $original_urls[0] );

		preg_match_all( $urls_regex, $translation, $translation_urls );
		$translation_urls = array_unique( $translation_urls[0] );

		$missing_urls = array_diff( $original_urls, $translation_urls );
		$added_urls = array_diff( $translation_urls, $original_urls );
		if ( ! $missing_urls && ! $added_urls ) {
			return true;
		}

		// Check to see if only the scheme was changed (https <=> http), discard if so.
		foreach ( $missing_urls as $key => $missing_url ) {
			$scheme = parse_url( $missing_url, PHP_URL_SCHEME );
			$alternate_scheme = ( 'http' == $scheme ? 'https' : 'http' );
			$alternate_scheme_url = preg_replace( "@^$scheme(?=:)@", $alternate_scheme, $missing_url );

			if ( false !== ( $alternate_index = array_search( $alternate_scheme_url, $added_urls ) ) ) {
				unset( $missing_urls[ $key ], $added_urls[ $alternate_index ] );
			}
		}

		// Check if just the domain was changed, and if so, if it's to a whitelisted domain
		foreach ( $missing_urls as $key => $missing_url ) {
			$host = parse_url( $missing_url, PHP_URL_HOST );
			if ( ! isset( $this->allowed_domain_changes[ $host ] ) ) {
				continue;
			}
			$allowed_host_regex = $this->allowed_domain_changes[ $host ];

			list( , $missing_url_path ) = explode( $host, $missing_url, 2 );

			$alternate_host_regex = '!^https?://' . $allowed_host_regex . preg_quote( $missing_url_path, '!' ) . '$!i';
			foreach ( $added_urls as $added_index => $added_url ) {
				if ( preg_match( $alternate_host_regex, $added_url, $match ) ) {
					unset( $missing_urls[ $key ], $added_urls[ $added_index ] );
				}
			}

		}

		if ( ! $missing_urls && ! $added_urls ) {
			return true;
		}

		// Error.
		$error = '';
		if ( $missing_urls ) {
			$error .= "The translation appears to be missing the following URLs: " . implode( ', ', $missing_urls ) . "\n";
		}
		if ( $added_urls ) {
			$error .= "The translation contains the following unexpected URLs: " . implode( ', ', $added_urls );
		}

		return trim( $error );
	}

	/**
	 * Registers all methods starting with warning_ with GlotPress.
	 */
	public function __construct() {
		$warnings = array_filter( get_class_methods( get_class( $this ) ), function( $key ) {
			return gp_startswith( $key, 'warning_' );
		} );

		foreach ( $warnings as $warning ) {
			GP::$translation_warnings->add( str_replace( 'warning_', '', $warning ), array( $this, $warning ) );
		}
	}

}

function wporg_gp_custom_translation_warnings() {
	global $wporg_gp_custom_translation_warnings;

	if ( ! isset( $wporg_gp_custom_translation_warnings ) ) {
		$wporg_gp_custom_translation_warnings = new WPorg_GP_Custom_Translation_Warnings();
	}

	return $wporg_gp_custom_translation_warnings;
}
add_action( 'plugins_loaded', 'wporg_gp_custom_translation_warnings' );

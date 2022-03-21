<?php
/**
 * Page head customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Head {

	/**
	 * Initializes component.
	 */
	public static function init() {
		add_filter( 'document_title_separator', [ __CLASS__, 'document_title_separator' ] );
		add_filter( 'wp_resource_hints',        [ __CLASS__, 'wp_resource_hints' ], 10, 2 );
	}

	/**
	 * Returns the CDN domain for photo images.
	 *
	 * @param bool $with_proto Include HTTP protocol? Default true.
	 * @return string
	 */
	public static function get_photos_cdn_domain( $with_proto = true ) {
		/**
		 * Filters the CDN domain for photo images.
		 *
		 * If no protocol is defined, 'https://' will be assumed.
		 *
		 * @param string $domain The CDN domain, with protocol. Default ''.
		 */
		$cdn_domain = apply_filters( 'wporg_photos_cdn_domain', '' );

		if ( $with_proto ) {
			// Assume https if one wasn't explicitly specified.
			if ( $cdn_domain && 0 !== strpos( $cdn_domain, 'https://' ) && 0 !== strpos( $cdn_domain, 'http://' ) ) {
				$cdn_domain = 'https://' . $cdn_domain;
			}
		} else {
			$cdn_domain = str_replace( [ 'https://', 'http://' ], '', $cdn_domain );
		}

		return $cdn_domain;
	}

	/**
	 * Customizes the document title separator.
	 *
	 * @param string $separator Current document title separator.
	 * @return string
	 */
	public static function document_title_separator( $separator ) {
		return '|';
	}

	/**
	 * Adds a dns-prefetch for the cloud storage domain, if one is defined.
	 */
	public static function wp_resource_hints( $uris, $type ) {
		$cdn_domain = self::get_photos_cdn_domain( false );

		if ( $cdn_domain && 'dns-prefetch' === $type ) {
			$uris[] = '//' . $cdn_domain;
		}

		return $uris;
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Head', 'init' ] );

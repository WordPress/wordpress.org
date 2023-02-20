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
		add_action( 'wp_head',                  [ __CLASS__, 'json_ld_schema' ], 1 );
		add_action( 'wp_head',                  [ __CLASS__, 'disable_hreflang' ], 1 );
	}

	/**
	 * Disable the hreflang tags from the parent theme.
	 */
	public static function disable_hreflang() {
		remove_action( 'wp_head', 'WordPressdotorg\Theme\hreflang_link_attributes' );
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

	/**
	 * Outputs structured data.
	 */
	public static function json_ld_schema() {
		$schema = false;

		// Schema for individual photo pages.
		if ( is_singular( Registrations::get_post_type() ) && 'publish' === get_post_status( get_queried_object_id() ) ) {
			$contributor_name = get_the_author_meta( 'display_name', get_queried_object()->post_author );
			$schema = [
				"@context"           => 'https://schema.org',
				"@type"              => 'ImageObject',
				'contentUrl'         => wp_get_attachment_image_src( get_post_thumbnail_id(), 'full' )[0] ?? '',
				'license'            => 'https://creativecommons.org/share-your-work/public-domain/cc0/',
				'acquireLicensePage' => "https://wordpress.org/photos/license/",
				'creditText'         => $contributor_name,
				'creator' => [
					'@type'          => 'Person',
					'name'           => $contributor_name,
				],
				'copyrightNotice'    => $contributor_name,
			];
		}

		// Print the schema.
		if ( $schema ) {
			echo PHP_EOL, '<script type="application/ld+json">', PHP_EOL;
			// Output URLs without escaping the slashes, and print it human readable.
			echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT );
			echo PHP_EOL, '</script>', PHP_EOL;
		}
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Head', 'init' ] );

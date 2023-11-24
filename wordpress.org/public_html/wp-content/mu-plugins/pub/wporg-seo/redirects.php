<?php
namespace WordPressdotorg\SEO\Redirects;
use function WordPressdotorg\SEO\Canonical\get_canonical_url;

/**
 * Custom Canonical redirect for Facebook and Twitter referrers.
 */
function facebook_twitter_referers() {
	// Only run on pages with canonical enabled.
	if ( ! has_action( 'template_redirect', 'redirect_canonical' ) ) {
		return;
	}

	$url = false;
	if ( isset( $_GET['fbclid'] ) ) {
		$url = remove_query_arg( 'fbclid' ) . '#utm_medium=referral&utm_source=facebook.com&utm_content=social';
	} elseif ( isset( $_GET['__twitter_impression'] ) ) {
		$url = remove_query_arg( '__twitter_impression' ) . '#utm_medium=referral&utm_source=twitter.com&utm_content=social';
	}

	if ( $url ) {
		wp_safe_redirect( $url, 301 );
		exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\facebook_twitter_referers', 9 ); // Before redirect_canonical();

/**
 * Redirect pages to the canonical case sensitive URL.
 *
 * Eg. https://wordpress.org/Blocks
 */
function case_sensitivity_canonical_urls() {
	// Only run on pages with canonical enabled.
	if ( ! has_action( 'template_redirect', 'redirect_canonical' ) ) {
		return;
	}

	if ( ! preg_match( '/[A-Z]/', $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$requested_url = set_url_scheme( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
	$canonical_url = get_canonical_url();

	if (
		$requested_url !== $canonical_url &&
		0 === strcasecmp( $requested_url, $canonical_url )
	) {
		wp_safe_redirect( $canonical_url, 301 );
		exit;
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\case_sensitivity_canonical_urls' );

/**
 * Redirect pages to the canonical URL when the query parameter contained a data point that was sanitized to -.
 *
 * Eg. https://wordpress.org/download/releases/6.3/ or https://wordpress.org/documentation/wordpress-version/version-6.3/
 */
function sanitized_url_param_canonical_urls() {
	global $wp_query;
	// Only run on pages with canonical enabled.
	if ( ! has_action( 'template_redirect', 'redirect_canonical' ) ) {
		return;
	}

	if ( ! preg_match( '/[^a-z0-9]/i', $_SERVER['REQUEST_URI'] ) ) {
		return;
	}

	$requested_url = set_url_scheme( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );
	$canonical_url = get_canonical_url();

	foreach ( [ 'name', 'pagename' ] as $qv_field ) {
		if ( ! isset( $wp_query->query[ $qv_field ] ) ) {
			continue;
		}

		$sanitized  = $wp_query->query_vars[ $qv_field ];
		$raw        = $wp_query->query[ $qv_field ];
		if ( 'pagename' === $qv_field ) {
			$raw = basename( $raw );
		}

		if (
			$sanitized === $raw ||
			sanitize_title_for_query( $raw ) != $sanitized
		) {
			continue;
		}

		$canonical_with_raw = str_replace( $sanitized, $raw, $canonical_url );

		if (
			$canonical_with_raw !== $canonical_url &&
			0 === strcasecmp( $requested_url, $canonical_with_raw )
		) {
			wp_safe_redirect( $canonical_url, 301 );
			exit;
		}
	}
}
add_action( 'template_redirect', __NAMESPACE__ . '\sanitized_url_param_canonical_urls' );

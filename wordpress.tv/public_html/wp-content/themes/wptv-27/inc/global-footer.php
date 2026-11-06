<?php
/**
 * The WordPress.org global footer, from wordpress.org's footer endpoint.
 *
 * WordPress.com doesn't run wporg-mu-plugins, so the footer block itself isn't
 * available here; the endpoint serves its markup to sites outside the network.
 *
 * @package WordPressTV
 */

declare( strict_types = 1 );

namespace WordPressTV\GlobalFooter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Endpoint that renders the global footer.
 */
const ENDPOINT = 'https://wordpress.org/wp-json/global-header-footer/v1/footer';

/**
 * Stylesheet the global header and footer share.
 */
const STYLESHEET = 'https://s.w.org/wp-content/mu-plugins/pub-sync/blocks/global-header-footer/build/style.css';

/**
 * Option caching the footer markup and when to fetch it again.
 */
const OPTION = 'wptv_global_footer';

/**
 * Seconds a fetched footer is kept.
 */
const LIFETIME = 12 * HOUR_IN_SECONDS;

/**
 * Seconds until a failed fetch is retried.
 */
const RETRY = 5 * MINUTE_IN_SECONDS;

/**
 * Registers the block the footer template part renders.
 *
 * @return void
 */
function register_block(): void {
	register_block_type(
		'wptv/global-footer',
		array(
			'api_version'     => 3,
			'title'           => __( 'WordPress.org footer', 'wptv' ),
			'category'        => 'theme',
			'supports'        => array(
				'autoRegister' => true,
				'html'         => false,
				'inserter'     => false,
			),
			'render_callback' => __NAMESPACE__ . '\render',
		)
	);
}
add_action( 'init', __NAMESPACE__ . '\register_block' );

/**
 * Renders the global footer, with the styles it needs.
 *
 * @return string Footer markup, or an empty string when it was never fetched.
 */
function render(): string {
	$markup = get_markup();
	if ( '' === $markup ) {
		return '';
	}

	wp_enqueue_style( 'wporg-global-header-footer', STYLESHEET, array(), gmdate( 'Ymd' ) );
	// Core only loads block styles for blocks it renders, and the footer's social links aren't rendered here.
	wp_enqueue_style( 'wp-block-social-links' );

	return $markup;
}

/**
 * Returns the cached footer markup, fetching it again once it expires.
 *
 * A failed fetch keeps the last footer and tries again a few minutes later.
 *
 * @return string Footer markup, or an empty string when it was never fetched.
 */
function get_markup(): string {
	$cached = get_option( OPTION );
	if ( is_array( $cached ) && $cached['expires'] > time() ) {
		return $cached['markup'];
	}

	$fetched = fetch_markup();
	$markup  = $fetched ?? ( $cached['markup'] ?? '' );
	update_option(
		OPTION,
		array(
			'markup'  => $markup,
			'expires' => time() + ( null === $fetched ? RETRY : LIFETIME ),
		),
		false
	);

	return $markup;
}

/**
 * Fetches the footer from wordpress.org.
 *
 * @return string|null Footer markup, or null when the request fails.
 */
function fetch_markup(): ?string {
	$response = wp_safe_remote_get( ENDPOINT, array( 'timeout' => 3 ) );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	return extract_footer( wp_remote_retrieve_body( $response ) );
}

/**
 * Takes the footer element from the endpoint's response.
 *
 * The response goes on with wordpress.org's `wp_footer()` scripts and closes the
 * document, so only the element itself is kept.
 *
 * @param string $body Response body.
 * @return string|null Footer element, or null when the body doesn't start with one.
 */
function extract_footer( string $body ): ?string {
	$body = ltrim( $body );
	$end  = strpos( $body, '</footer>' );
	if ( ! str_starts_with( $body, '<footer ' ) || false === $end ) {
		return null;
	}

	return substr( $body, 0, $end + strlen( '</footer>' ) );
}

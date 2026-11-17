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
	$footer = get_footer();
	if ( '' === $footer['markup'] ) {
		return '';
	}

	// Versioned by the fetch, so a new footer never meets a stylesheet the browser cached for the old one.
	wp_enqueue_style( 'wporg-global-header-footer', 'https://s.w.org/wp-content/mu-plugins/pub-sync/blocks/global-header-footer/build/style.css', array(), (string) $footer['fetched'] );
	// Core only loads block styles for blocks it renders, and the footer's social links aren't rendered here.
	wp_enqueue_style( 'wp-block-social-links' );

	return $footer['markup'];
}

/**
 * Returns the cached footer, fetching it again after 12 hours.
 *
 * A failed fetch keeps the last footer and tries again 5 minutes later.
 *
 * @return array Footer markup, when it was fetched, and when it expires.
 */
function get_footer(): array {
	$cached = get_option( 'wptv_global_footer' );
	if ( is_array( $cached ) && $cached['expires'] > time() ) {
		return $cached;
	}

	$markup = fetch_markup();
	if ( null === $markup ) {
		$footer = array(
			'markup'  => $cached['markup'] ?? '',
			'fetched' => $cached['fetched'] ?? 0,
			'expires' => time() + 5 * MINUTE_IN_SECONDS,
		);
	} else {
		$footer = array(
			'markup'  => $markup,
			'fetched' => time(),
			'expires' => time() + 12 * HOUR_IN_SECONDS,
		);
	}
	update_option( 'wptv_global_footer', $footer, false );

	return $footer;
}

/**
 * Fetches the footer from wordpress.org.
 *
 * @return string|null Footer markup, or null when the request fails.
 */
function fetch_markup(): ?string {
	$response = wp_safe_remote_get( 'https://wordpress.org/wp-json/global-header-footer/v1/footer', array( 'timeout' => 3 ) );
	if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
		return null;
	}

	return extract_footer( wp_remote_retrieve_body( $response ) );
}

/**
 * Takes the footer element from the endpoint's response, as a div.
 *
 * The response goes on with wordpress.org's `wp_footer()` scripts and closes the
 * document, so only the element is kept. The template part is the page's footer
 * element, as on wordpress.org, where the block renders as a div inside it.
 *
 * @param string $body Response body.
 * @return string|null Footer markup, or null when the body doesn't start with a footer element.
 */
function extract_footer( string $body ): ?string {
	$body = ltrim( $body );
	$end  = strpos( $body, '</footer>' );
	if ( ! str_starts_with( $body, '<footer ' ) || false === $end ) {
		return null;
	}

	return '<div ' . substr( $body, strlen( '<footer ' ), $end - strlen( '<footer ' ) ) . '</div>';
}

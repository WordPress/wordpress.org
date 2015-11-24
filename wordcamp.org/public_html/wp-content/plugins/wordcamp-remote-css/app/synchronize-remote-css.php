<?php

namespace WordCamp\RemoteCSS;

defined( 'WPINC' ) or die();

/**
 * Synchronizes the local safe/cached copy of the CSS with the canonical, remote source.
 *
 * @param string $remote_css_url
 */
function synchronize_remote_css( $remote_css_url ) {
	sanitize_and_save_unsafe_css( fetch_unsafe_remote_css( $remote_css_url ) );
	update_option( OPTION_LAST_UPDATE, time() );
}

/**
 * Fetch the unsafe CSS from the remote server
 *
 * @param string $remote_css_url
 *
 * @throws \Exception if the response body could not be retrieved for any reason
 *
 * @return string
 */
function fetch_unsafe_remote_css( $remote_css_url ) {
	$response = wp_remote_get(
		$remote_css_url,
		array(
			'user-agent'         => 'WordCamp.org Remote CSS',  // GitHub's API explicitly requests this, and it could be beneficial for other platforms too
			'reject_unsafe_urls' => true,
		)
	);

	if ( is_wp_error( $response ) ) {
		throw new \Exception( $response->get_error_message() );
	}

	$response_code = (int) wp_remote_retrieve_response_code( $response );

	if ( ! in_array( $response_code, array( 200, 301, 302, 303, 307, 308 ), true ) ) {
		throw new \Exception( sprintf(
			__( 'The remote server responded with status code <code>%d</code>, which is not valid.', 'wordcamporg' ),
			$response_code
		) );
	}

	return apply_filters( 'wcrcss_unsafe_remote_css', wp_remote_retrieve_body( $response ), $remote_css_url );
}

/**
 * Sanitize unsafe CSS and save the safe version
 *
 * Note: If we ever need to decouple from Jetpack Custom CSS, then https://github.com/google/caja might be
 * a viable alternative. It'd be nice to have a modular solution, but we'd also have to keep it up to date,
 * and we'd still need to mirror the Output Mode setting.
 *
 * @param string $unsafe_css
 *
 * @throws \Exception if Jetpack's Custom CSS module isn't available
 */
function sanitize_and_save_unsafe_css( $unsafe_css ) {
	if ( ! is_callable( array( '\Jetpack_Custom_CSS', 'save' ) ) ) {
		throw new \Exception(
			__( "<code>Jetpack_Custom_CSS::save()</code> is not available.
			Please make sure Jetpack's Custom CSS module has been activated.", 'wordcamporg' )
		);
	}

	/*
	 * Note: In addition to the sanitization that Jetpack_Custom_CSS::save() does, there's additional sanitization
	 * done by the callbacks in mu-plugins/jetpack-tweaks.php.
	 */

	add_filter( 'jetpack_custom_css_pre_post_id', __NAMESPACE__ . '\get_safe_css_post_id' );

	\Jetpack_Custom_CSS::save( array(
		'css'             => $unsafe_css,
		'is_preview'      => false,
		'preprocessor'    => '',     // This should never be changed to allow pre-processing. See note in validate_remote_css_url()
		'add_to_existing' => false,  // This isn't actually used, see get_output_mode()
		'content_width'   => false,
	) );

	remove_filter( 'jetpack_custom_css_pre_post_id', __NAMESPACE__ . '\get_safe_css_post_id' );

	/*
	 * Jetpack_Custom_CSS::save_revision() caches our post ID because it retrieves the post ID from
	 * Jetpack_Custom_CSS::post_id() while the get_safe_css_post_id() callback is active. We need to clear that
	 * to avoid unintended side-effects.
	 */
	wp_cache_delete( 'custom_css_post_id' );
}

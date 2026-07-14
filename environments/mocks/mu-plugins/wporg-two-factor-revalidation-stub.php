<?php
/**
 * Plugin Name: WordPress.org Two-Factor Revalidation (local stub)
 * Description: Stubs the WordPressdotorg\Two_Factor\Revalidation helpers from the
 *              wporg-two-factor plugin that the Theme Directory upload page uses.
 *              Reports revalidation as current so uploads aren't blocked locally.
 *              A no-op when the real functions are present.
 *
 * @package theme-directory-env
 */

namespace WordPressdotorg\Two_Factor\Revalidation;

if ( ! function_exists( __NAMESPACE__ . '\get_status' ) ) {
	/**
	 * Report two-factor revalidation as current so uploads aren't blocked.
	 *
	 * @return array
	 */
	function get_status() {
		return array( 'can_save' => true );
	}
}

if ( ! function_exists( __NAMESPACE__ . '\get_url' ) ) {
	/**
	 * Revalidation URL. Returns the given redirect target unchanged.
	 *
	 * @param string $redirect_to Redirect target.
	 * @return string
	 */
	function get_url( $redirect_to = '' ) {
		return $redirect_to;
	}
}

if ( ! function_exists( __NAMESPACE__ . '\enqueue_assets' ) ) {
	/**
	 * Enqueue revalidation assets. No-op in the stub.
	 *
	 * @return void
	 */
	function enqueue_assets() {}
}

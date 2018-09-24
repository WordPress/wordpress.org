<?php

namespace WordCamp\Jetpack_Tweaks\Privacy;

use WordCamp\Logger;
use WP_Widget_Factory;

defined( 'WPINC' ) || die();

add_action( 'jetpack_active_modules',           __NAMESPACE__ . '\load_jetpack_widgets_module'    );
add_action( 'wp_footer',                        __NAMESPACE__ . '\render_cookie_banner'           );
add_filter( 'wp_privacy_personal_data_erasers', __NAMESPACE__ . '\modify_erasers',             99 );


/**
 * Always load Jetpack's `widgets` modules, for the cookie banner.
 *
 * @param array $active_modules
 *
 * @return array
 */
function load_jetpack_widgets_module( $active_modules ) {
	if ( ! in_array( 'widgets', $active_modules, true ) ) {
		$active_modules[] = 'widgets';
	}

	return $active_modules;
}

/**
 * Add the Jetpack cookie banner to the footer of every WordCamp site.
 *
 * @todo This has a very negative impact on user experience, and arguably fails to provide the visitor with any
 *       meaningful protection. It is currently required by Europe's ePrivacy Directive, though. We should consider
 *       removing it if/when ePD v2 is finalized, since that is expected to narrow the requirement to only cover
 *       invasive cookies.
 *       See https://www.lexology.com/library/detail.aspx?g=859d2614-cf11-4f9a-a0e3-f0f8ed15600a.
 */
function render_cookie_banner() {
	/** @var WP_Widget_Factory $wp_widget_factory */
	global $wp_widget_factory;

	$cookie_law_widget = $wp_widget_factory->widgets['Jetpack_EU_Cookie_Law_Widget'];

	if ( ! is_callable( array( $cookie_law_widget, 'enqueue_frontend_scripts' ) ) ) {
		Logger\log( 'cookie_law_widget_missing' );
		return;
	}

	$widget_params = array(
		'hide'               => 'button',
		'consent-expiration' => 365,
		'text'               => 'custom',
		'customtext'         => __( "Privacy & Cookies: This site uses cookies. By continuing to use this website, you agree to their use. \r\nTo find out more, including how to control cookies, please see here:", 'wordcamporg' ) . ' ',
		'color-scheme'       => 'default',
		'policy-url'         => 'custom',
		'custom-policy-url'  => trailingslashit( get_privacy_policy_url() ) . 'cookies/',
		'policy-link-text'   => __( 'Cookie Policy',    'wordcamporg' ),
		'button'             => __( 'Close and accept', 'wordcamporg' ),
	);

	the_widget( 'Jetpack_EU_Cookie_Law_Widget', $widget_params );

	$cookie_law_widget->enqueue_frontend_scripts();
}

/**
 * Modify the list of personal data eraser callbacks.
 *
 * @param array $erasers
 *
 * @return array mixed
 */
function modify_erasers( $erasers ) {
	// Temporarily disable the default eraser callbacks.
	unset( $erasers['jetpack-feedback'] );

	return $erasers;
}

<?php

namespace WordCamp\RemoteCSS;
defined( 'WPINC' ) or die();

add_action( 'admin_menu', __NAMESPACE__ . '\add_admin_pages' );

/**
 * Register new admin pages
 */
function add_admin_pages() {
	$page_hook = \add_submenu_page(
		'themes.php',
		__( 'Remote CSS', 'wordcamporg' ),
		__( 'Remote CSS', 'wordcamporg' ),
		'switch_themes',
		'remote-css',
		__NAMESPACE__ . '\render_options_page'
	);

	add_action( 'admin_print_styles-' . $page_hook, __NAMESPACE__ . '\print_css' );
	add_action( 'load-'               . $page_hook, __NAMESPACE__ . '\add_contextual_help_tabs' );
}

/**
 * Render the view for the options page
 */
function render_options_page() {
	$notice = null;

	if ( isset( $_POST['submit'] ) ) {
		try {
			$notice       = process_options_page();
			$notice_class = 'notice-success';
		} catch( \Exception $exception ) {
			$notice       = $exception->getMessage();
			$notice_class = 'notice-error';
		}
	}

	$output_mode               = get_output_mode();
	$remote_css_url            = get_option( OPTION_REMOTE_CSS_URL , '' );
	$fonts_tool_url            = admin_url( 'themes.php?page=wc-fonts-options' );

	require_once( dirname( __DIR__ ) . '/views/page-remote-css.php' );
}

/**
 * Get or set the mode for outputting the custom CSS.
 *
 * See get_output_mode() for notes.
 *
 * @param string $mode
 */
function set_output_mode( $mode ) {
	$jetpack_settings            = (array) get_theme_mod( 'jetpack_custom_css' );
	$jetpack_settings['replace'] = 'replace' === $mode;

	set_theme_mod( 'jetpack_custom_css', $jetpack_settings );
}

/**
 * Process submissions of the form on the options page
 *
 * @throws \Exception if the user isn't authorized
 *
 * @return string
 */
function process_options_page() {
	check_admin_referer( 'wcrcss-options-submit', 'wcrcss-options-nonce' );

	if ( ! current_user_can( 'switch_themes' ) ) {
		throw new \Exception( __( 'Access denied.', 'wordcamporg' ) );
	}

	$remote_css_url = trim( $_POST['wcrcss-remote-css-url'] );

	if ( '' === $remote_css_url ) {
		$notice = '';
		$post   = get_safe_css_post();

		wp_delete_post( $post->ID );
	} else {
		$notice         = __( 'The remote CSS file was successfully synchronized.', 'wordcamporg' );
		$remote_css_url = validate_remote_css_url( $remote_css_url );

		synchronize_remote_css( $remote_css_url );
	}

	set_output_mode( $_POST['wcrcss-output-mode'] );
	update_option( OPTION_REMOTE_CSS_URL, $remote_css_url );

	return $notice;
}

/**
 * Validate the remote CSS URL provided by the user
 *
 * @param string $remote_css_url
 *
 * @throws \Exception if the URL cannot be validated
 *
 * @return string
 */
function validate_remote_css_url( $remote_css_url ) {
	// Syntactically-valid URLs only
	$remote_css_url = filter_var( $remote_css_url, FILTER_VALIDATE_URL );

	if ( false === $remote_css_url ) {
		throw new \Exception( __( 'The URL was invalid.', 'wordcamporg' ) );
	}

	$remote_css_url = esc_url_raw( $remote_css_url, array( 'http', 'https' ) );

	if ( empty( $remote_css_url ) ) {
		throw new \Exception( __( 'The URL was invalid.', 'wordcamporg' ) );
	}

	$parsed_url = parse_url( $remote_css_url );

	/*
	 * Only allow whitelisted hostnames, to prevent SSRF attacks
	 *
	 * WARNING: These must be trusted in the sense that they're not malicious, but also in the sense that they
	 * have strong internal security. We can't allow sites hosted by local WordPress communities, for instance,
	 * because an attacker could gain control over their DNS zone and then change the A record to 127.0.0.1,
	 * or an IP on our internal network.
	 *
	 * Therefore, only reputable platforms like GitHub, Beanstalk, CloudForge, BitBucket, etc should be added.
	 */
	$trusted_hostnames = apply_filters( 'wcrcss_trusted_remote_hostnames', array() );

	if ( ! in_array( $parsed_url['host'], $trusted_hostnames, true ) ) {
		throw new \Exception( sprintf(
			__( 'Due to security constraints, only certain third-party platforms can be used,
			and the URL you provided is not hosted by one of our currently-supported platforms.
			To request that it be added, please <a href="%s">create a ticket</a> on Meta Trac.',
			'wordcamporg' ),
			'https://meta.trac.wordpress.org/newticket'
		) );
	}

	/*
	 * Vanilla CSS only
	 *
	 * We need to force the user to do their own pre-processing, because Jetpack_Custom_CSS_Enhancements::sanitize() doesn't
	 * sanitize the unsafe CSS when a preprocessor is present. We'd have to add more logic to make sure it gets
	 * sanitized, which would further couple the plugin to Jetpack.
	 */
	if ( '.css' !== substr( $parsed_url['path'], strlen( $parsed_url['path'] ) - 4, 4 ) ) {
		throw new \Exception(
			__( 'The URL must be a vanilla CSS file ending in <code>.css</code>.
			If you\'d like to use SASS/LESS, please compile it into vanilla CSS on your server,
			and then enter the URL for that file.',
			'wordcamporg' )
		);
	}

	/*
	 * Note: We also want to restrict the URL to ports 80, 443, and 8080. The 'reject_unsafe_urls' in
	 * fetch_unsafe_remote_css() takes care of that for us.
	 */

	return apply_filters( 'wcrcss_validate_remote_css_url', $remote_css_url );
}

/**
 * Print CSS for the options page
 */
function print_css() {
	?>

	<style type="text/css">
		body.appearance_page_remote-css button.button-link {
			color: #0073aa;
			padding: 0;
		}
	</style>

	<?php
}

/**
 * Register contextual help tabs
 */
function add_contextual_help_tabs() {
	$screen = get_current_screen();
	$tabs   = array( 'Overview', 'Basic Setup', 'Automated Synchronization', 'Tips' );

	foreach ( $tabs as $tab ) {
		$screen->add_help_tab( array(
			'id'       => 'wcrcss-' . sanitize_title( $tab ),
			'title'    => $tab,
			'callback' => __NAMESPACE__ . '\render_contextual_help_tabs',
		) );
	}
}

/**
 * Render contextual help tabs
 *
 * @param \WP_Screen $screen
 * @param array      $tab
 */
function render_contextual_help_tabs( $screen, $tab ) {
	$view_slug = str_replace( 'wcrcss-', '', $tab['id'] );

	switch ( $view_slug ) {
		case 'overview':
			$custom_css_url = admin_url( 'customize.php?autofocus[section]=custom_css' );
			break;

		case 'automated-synchronization':
			$webhook_payload_url = sprintf( '%s?action=%s', admin_url( 'admin-ajax.php' ), AJAX_ACTION );
			break;

		case 'tips':
			$fonts_tool_url    = admin_url( 'themes.php?page=wc-fonts-options' );
			$media_library_url = admin_url( 'upload.php' );
			break;
	}

	require_once( sprintf( '%s/views/help-%s.php', dirname( __DIR__ ), $view_slug ) );
}

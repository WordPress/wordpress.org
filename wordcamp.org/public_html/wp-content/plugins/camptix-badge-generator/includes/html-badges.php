<?php

/*
 * @todo Rework to take advantage of Additional CSS in 4.7, rather than duplicating syntax highlighting, etc
 */

namespace CampTix\Badge_Generator\HTML;
use \CampTix\Badge_Generator;

defined( 'WPINC' ) or die();

add_action( 'customize_register',    __NAMESPACE__ . '\register_customizer_components'   );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_customizer_scripts'       );
add_action( 'admin_print_styles',    __NAMESPACE__ . '\print_customizer_styles'          );
add_action( 'wp_enqueue_scripts',    __NAMESPACE__ . '\remove_all_previewer_styles', 998 );
add_action( 'wp_enqueue_scripts',    __NAMESPACE__ . '\enqueue_previewer_scripts',   999 );  // after remove_all_previewer_styles()
add_filter( 'template_include',      __NAMESPACE__ . '\use_badges_template'              );

/**
 * Register our Customizer settings, panels, sections, and controls
 *
 * @param \WP_Customize_Manager $wp_customize
 */
function register_customizer_components( $wp_customize ) {
	ob_start();
	require_once( dirname( __DIR__ ) . '/views/html-badges/section-description.php' );
	$section_description = ob_get_clean();

	$wp_customize->add_section(
		'camptix_html_badges',
		array(
			'capability'  => Badge_Generator\REQUIRED_CAPABILITY,
			'type'        => 'cbgSection',
			'title'       => __( 'CampTix HTML Badges', 'wordcamporg' ),
			'description' => $section_description,
		)
	);

	$wp_customize->add_control(
		'cbg_print_badges',
		array(
			'section'     => 'camptix_html_badges',
			'settings'    => array(),
			'type'        => 'button',
			'priority'    => 1,
			'capability'  => Badge_Generator\REQUIRED_CAPABILITY,
			'input_attrs' => array(
				'class' => 'button button-primary',
				'value' => __( 'Print', 'wordcamporg' ),
			),
		)
	);

	$wp_customize->add_control(
		'cbg_reset_css',
		array(
			'section'     => 'camptix_html_badges',
			'settings'    => array(),
			'type'        => 'button',
			'priority'    => 1,
			'capability'  => Badge_Generator\REQUIRED_CAPABILITY,
			'input_attrs' => array(
				'class' => 'button button-secondary',
				'value' => __( 'Reset to Default', 'wordcamporg' ),
			),
		)
	);

	$wp_customize->add_setting(
		'cbg_badge_css',
		array(
			'default'           => file_get_contents( dirname( __DIR__ ) . '/css/html-badges-default-styles.css' ),
			'type'              => 'option',
			'capability'        => Badge_Generator\REQUIRED_CAPABILITY,
			'transport'         => 'postMessage',
			'sanitize_callback' => 'esc_textarea',
		)
	);

	$wp_customize->add_control(
		'cbg_badge_css',
		array(
			'section'  => 'camptix_html_badges',
			'type'     => 'textarea',
			'priority' => 2,
			'label'    => __( 'Customize Badge CSS', 'wordcamporg' ),
		)
	);
}

/**
 * Get the URL for the HTML Badges section in the Customizer
 *
 * @return string
 */
function get_customizer_section_url() {
	$url = add_query_arg(
		array(
			'autofocus[section]' => 'camptix_html_badges',
			'url'                => rawurlencode( add_query_arg( 'camptix-badges', '', site_url() ) ),
		),
		admin_url( 'customize.php' )
	);

	return $url;
}

/**
 * Check if the current page request is in the Customizer
 *
 * @return bool
 */
function is_customizer_request() {
	return isset( $GLOBALS['wp_customize'] );
}

/**
 * Enqueue scripts and styles for the Customizer
 */
function enqueue_customizer_scripts() {
	if ( ! is_customizer_request() ) {
		return;
	}

	// Enqueue CodeMirror script and style
	if ( ! is_callable( array( 'Jetpack_Custom_CSS', 'enqueue_scripts' ) ) ) {
		require_once( JETPACK__PLUGIN_DIR . 'modules/custom-css/custom-css.php' );
		define( 'SAFECSS_USE_ACE', true );
	}

	\Jetpack_Custom_CSS::enqueue_scripts( 'appearance_page_editcss' );

	// Dequeue extraneous Jetpack scripts and styles
	wp_dequeue_script( 'postbox' );
	wp_dequeue_script( 'custom-css-editor' );
	wp_dequeue_style( 'custom-css-editor' );
	wp_dequeue_script( 'jetpack-css-use-codemirror' );

	// Enqueue our scripts
	wp_enqueue_script(
		'camptix-html-badges-customizer',
		plugins_url( 'javascript/html-badges-customizer.js', __DIR__ ),
		array( 'jquery', 'jetpack-css-codemirror' ),
		1,
		true
	);

	wp_localize_script(
		'camptix-html-badges-customizer',
		'cbgHtmlCustomizerData',
		array(
			'defaultCSS' => file_get_contents( dirname( __DIR__ ) . '/css/html-badges-default-styles.css' ),
		)
	);
}

/**
 * Print styles for the Customizer
 */
function print_customizer_styles() {
	if ( ! is_customizer_request() ) {
		return;
	}

	?>

	<!-- BEGIN CampTix Badge Generator -->
	<style type="text/css">
		<?php require_once( dirname( __DIR__ ) . '/css/html-badges-customizer.css' ); ?>
	</style>
	<!-- END CampTix Badge Generator -->

	<?php
}

/**
 * Check if the current page is our section of the Previewer
 *
 * @return bool
 */
function is_badges_preview() {
	/** @global \WP_Customize_Manager $wp_customize */
	global $wp_customize;

	return isset( $_GET['camptix-badges'] ) && $wp_customize->is_preview();
}

/**
 * Use our custom template when the Badges page is requested
 *
 * @param string $template
 *
 * @return string
 */
function use_badges_template( $template ) {
	if ( ! is_badges_preview() ) {
		return $template;
	}

	if ( ! current_user_can( Badge_Generator\REQUIRED_CAPABILITY ) ) {
		return $template;
	}

	return dirname( __DIR__ ) . '/views/html-badges/template-badges.php';
}

/**
 * Render the template for HTML badges
 *
 * @todo Need some way of detecting failed HTTP requests for Gravatars and retrying them, like InDesign badges does
 */
function render_badges_template() {
	/** @global \CampTix_Plugin $camptix */
	global $camptix;

	$allowed_html = array(
		'span' => array(
			'class' => true,
		),
	);

	$attendees = Badge_Generator\get_attendees( 'all' );

	require( dirname( __DIR__ ) . '/views/html-badges/template-badges.php' );
}

/**
 * Remove all other styles from the Previewer, to avoid conflicts
 */
function remove_all_previewer_styles() {
	global $wp_styles;

	if ( ! is_badges_preview() ) {
		return;
	}

	foreach( $wp_styles->queue as $stylesheet ) {
		wp_dequeue_style( $stylesheet );
	}

	remove_all_actions( 'wp_print_styles' );

	remove_action( 'wp_head', array( 'Jetpack_Custom_CSS', 'link_tag' ), 101 );
}

/**
 * Enqueue scripts and styles for the Previewer
 */
function enqueue_previewer_scripts() {
	wp_register_script(
		'camptix-html-badges-previewer',
		plugins_url( 'javascript/html-badges-previewer.js', __DIR__ ),
		array( 'jquery', 'customize-preview' ),
		1,
		true
	);

	wp_register_style(
		'cbg_normalize',
		plugins_url( 'css/normalize.min.css', __DIR__ ),
		array(),
		'4.1.1'
	);

	if ( ! is_badges_preview() ) {
		return;
	}

	wp_enqueue_script( 'camptix-html-badges-previewer' );
	wp_enqueue_style( 'cbg_normalize' );

	/*
	 * Register the callback in this function so that the stylesheet is registered after remove_all_styles().
	 *
	 * Also, register it at wp_print_scripts instead of wp_print_styles, so that it gets printed _after_
	 * normalize.min.css.
	 */
	add_action( 'wp_print_scripts', __NAMESPACE__ . '\print_saved_styles' );
}

/**
 * Print the saved custom badge CSS
 */
function print_saved_styles() {
	if ( ! is_badges_preview() ) {
		return;
	}

	?>

	<style id="camptix-html-badges-css" type="text/css">
		<?php echo esc_html( get_option( 'cbg_badge_css' ) ); ?>
	</style>

	<?php
}

<?php

namespace WordPressdotorg\Openverse\Theme;

if ( !defined('OPENVERSE_URL') ) {
	define('OPENVERSE_URL', 'https://search.creativecommons.org');
}

add_action( 'after_setup_theme', __NAMESPACE__ . '\setup' );
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\enqueue_assets' );

add_filter( 'search_template', __NAMESPACE__ . '\use_index_php_as_template' );
add_filter( 'archive_template', __NAMESPACE__ . '\use_index_php_as_template' );

add_action( 'customize_register', __NAMESPACE__ . '\wporg_ov_customizer' );

/**
 * Sets up theme defaults and registers support for various WordPress features.
 *
 * Note that this function is hooked into the after_setup_theme hook, which
 * runs before the init hook.
 */
function setup() {
	// The parent wporg theme is designed for use on wordpress.org/* and assumes
	// locale-domains are available. Remove hreflang support.
	remove_action( 'wp_head', 'WordPressdotorg\Theme\hreflang_link_attributes' );
}

/**
 * Enqueue styles & scripts.
 *
 * The wporg theme registers these with static versions, so we need to override
 * with dynamic versions for cache-busting. The version is set to the last
 * modified time during development.
 */
function enqueue_assets() {
	$script_debug = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG;
	$suffix       = $script_debug ? '' : '.min';

	wp_enqueue_style(
		/* handle    */ 'wporg-ov-style',
		/* src       */ get_theme_file_uri( '/css/style.css' ),
		/* deps      */ array(),
		/* ver       */ filemtime( __DIR__ . '/css/style.css' )
	);

	wp_enqueue_script(
		/* handle    */ 'wporg-navigation',
		/* src       */ get_theme_file_uri( '/js/resize.js' ),
		/* deps      */ array(),
		/* ver       */ filemtime( __DIR__ . '/js/resize.js' ), 
		/* in_footer */ true
	);
}

/**
 * Use the index.php template for various WordPress views that would otherwise
 * be handled by the parent theme.
 */
function use_index_php_as_template() {
	return __DIR__ . '/index.php';
}

/**
 * Enable the option to set the URL for the Openverse embed via a GUI.
 * 
 * @param \WP_Customize_Manager $wp_customize Theme Customizer object.
 */
function wporg_ov_customizer( $wp_customize ) {
	$wp_customize->add_section( 'ov_embed', array(
		'priority' => 10,
		'capability' => 'edit_theme_options',
		'title' => esc_html__('Openverse Embed', 'wporg-ov'),
		'description' => esc_html__('Configure the Openverse embed iframe.', 'wporg-ov')
	) );
	$wp_customize->add_setting( 'ov_src_url', array(
		'type' => 'theme_mod',
		'capability' => 'edit_theme_options',
		'default' => OPENVERSE_URL,
		'sanitize_callback' => function( $val, $setting ) {
			if ( empty( $val ) ) {
                return $setting->default;
            }
			return $val;
		}
	) );
	$wp_customize->add_control( 'ov_src_url', array(
		'section' => 'ov_embed',
		'type' => 'url',
		'id' => 'ov_src_url',
		'label' => esc_html__('URL', 'wporg-ov'),
		'description' => esc_html__('Default: ', 'wporg-ov') . OPENVERSE_URL,
		'priority' => 10,
		'input_attrs' => array(
			'placeholder' => esc_html__('URL', 'wporg-ov')
		)
	) );
}

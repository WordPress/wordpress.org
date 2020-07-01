<?php
/**
 * Class to initialize handbooks.
 *
 * @package handbook
 */

class WPorg_Handbook_Init {

	public static function get_post_types() {
		return (array) apply_filters( 'handbook_post_types', array( 'handbook' ) );
	}

	static function init() {

		$post_types = self::get_post_types();

		new WPorg_Handbook_TOC( $post_types );

		foreach ( $post_types as $type ) {
			new WPorg_Handbook( $type );
		}

		WPorg_Handbook_Glossary::init();

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	static public function enqueue_styles() {
		wp_enqueue_style( 'wporg-handbook-css', plugins_url( '/stylesheets/callout-boxes.css', __FILE__ ), array(), '20200121' );
	}

	static public function enqueue_scripts() {
		wp_enqueue_script( 'wporg-handbook', plugins_url( '/scripts/handbook.js', __FILE__ ), array( 'jquery' ), '20150930' );
	}

}

add_action( 'after_setup_theme', array( 'WPorg_Handbook_Init', 'init' ) );


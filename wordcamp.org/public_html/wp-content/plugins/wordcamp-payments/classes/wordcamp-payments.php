<?php

/*
 * Main class to provide functionality common to all other classes
 */
class WordCamp_Payments {
	const VERSION = '0.1.0';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Enqueue scripts and stylesheets
	 */
	public function enqueue_assets( $hook ) {
		global $post;

		// todo setup grunt to concat/minify js and css?

		// Register our assets
		wp_register_script(
			'wordcamp-payments',
			plugins_url( 'javascript/wordcamp-payments.js', __DIR__ ),
			array( 'jquery', 'jquery-ui-datepicker', 'media-upload', 'media-views' ),
			self::VERSION,
			true
		);

		wp_register_script(
			'wcp-attached-files',
			plugins_url( 'javascript/attached-files.js', __DIR__ ),
			array( 'wordcamp-payments', 'backbone', 'wp-util' ),
			self::VERSION,
			true
		);

		wp_register_style(
			'wordcamp-payments',
			plugins_url( 'css/wordcamp-payments.css', __DIR__ ),
			array( 'jquery-ui', 'wp-datepicker-skins' ),
			self::VERSION
		);

		// Enqueue our assets if they're needed on the current screen
		$current_screen = get_current_screen();

		if ( in_array( $current_screen->id, array( 'edit-wcp_payment_request', 'wcp_payment_request' ) ) ) {
			wp_enqueue_script( 'wordcamp-payments' );
			wp_enqueue_style( 'wordcamp-payments' );

			if ( in_array( $current_screen->id, array( 'wcp_payment_request' ) ) && isset( $post->ID ) ) {
				wp_enqueue_media( array( 'post' => $post->ID ) );
				wp_enqueue_script( 'wcp-attached-files' );
			}

			wp_localize_script(
				'wordcamp-payments',
				'wcpLocalizedStrings',		// todo merge into wordcampPayments var
				array(
					'uploadModalTitle'  => __( 'Attach Supporting Documentation', 'wordcamporg' ),
					'uploadModalButton' => __( 'Attach Files', 'wordcamporg' ),
				)
			);
		}
	}
}

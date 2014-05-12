<?php

/*
 * Main controller to handle general functionality
 */

class WordCamp_Spreadsheets {
	const VERSION = '0.1';
	
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts',    array( $this, 'load_resources' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_resources' ) );
	}

	/**
	 * Prepares sites to use the plugin during single or network-wide activation
	 *
	 * @param bool $network_wide
	 */
	public function activate( $network_wide ) {
		if ( $network_wide && is_multisite() ) {
			$sites = wp_get_sites( array( 'limit' => false ) );

			foreach ( $sites as $site ) {
				switch_to_blog( $site['blog_id'] );
				$this->single_activate();
			}

			restore_current_blog();
		} else {
			$this->single_activate();
		}
	}

	/**
	 * Runs activation code on a new WPMS site when it's created
	 *
	 * @param int $blog_id
	 */
	public function activate_new_site( $blog_id ) {
		switch_to_blog( $blog_id );
		$this->single_activate();
		restore_current_blog();
	}

	/**
	 * Prepares a single blog to use the plugin
	 */
	protected function single_activate() {
		/** @var $WCSS_Spreadsheet WCSS_Spreadsheet */
		global $WCSS_Spreadsheet;

		$WCSS_Spreadsheet->create_post_type();
		flush_rewrite_rules();
	}

	/**
	 * Enqueues CSS, JavaScript, etc
	 */
	public function load_resources() {
		wp_register_script(
			'wordcamp-spreadsheets',
			plugins_url( 'javascript/wordcamp-spreadsheets.js', dirname( __FILE__ ) ),
			array( 'jquery' ),
			self::VERSION,
			true
		);

		wp_register_script(
			'spreadjs',
			plugins_url( 'includes/spreadjs/jquery.wijmo.wijspread.all.js', dirname( __FILE__ ) ),
			array( 'jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'wordcamp-spreadsheets' ),
			self::VERSION,
			true
		);

		wp_register_style(
			'wijmo',
			plugins_url( 'includes/spreadjs/jquery-wijmo.css', dirname( __FILE__ ) ),
			array(),
			self::VERSION,
			'all'
		);

		wp_register_style(
			'spreadjs',
			plugins_url( 'includes/spreadjs/jquery.wijmo.wijspread.css', dirname( __FILE__ ) ),
			array( 'wijmo' ),
			self::VERSION,
			'all'
		);

		wp_register_style(
			'wordcamp-spreadsheets',
			plugins_url( 'css/wordcamp-spreadsheets.css', dirname( __FILE__ ) ),
			array( 'spreadjs' ),
			self::VERSION,
			'all'
		);

		wp_enqueue_script( 'wordcamp-spreadsheets' );
		wp_enqueue_script( 'spreadjs' );

		wp_enqueue_style(  'spreadjs' );
		wp_enqueue_style(  'wordcamp-spreadsheets' );
	}
} // end WordCamp_Spreadsheets

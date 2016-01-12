<?php

/*
 * Main class to provide functionality common to all other classes
 */
class WordCamp_Budgets {
	const VERSION = '0.1.1';

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts',  array( $this, 'enqueue_common_assets' ), 11 );
	}

	/**
	 * Enqueue scripts and stylesheets common to all modules
	 */
	public function enqueue_common_assets() {
		// todo setup grunt to concat/minify js and css?

		// Let's still include our .css file even if these are unavailable.
		$soft_deps = array( 'jquery-ui', 'wp-datepicker-skins' );
		foreach ( $soft_deps as $key => $handle )
			if ( ! wp_style_is( $handle, 'registered' ) )
				unset( $soft_deps[ $key ] );

		wp_register_style(
			'wordcamp-budgets',
			plugins_url( 'css/wordcamp-budgets.css', __DIR__ ),
			$soft_deps,
			self::VERSION
		);
	}

	/**
	 * Insert an entry into a log for one of the custom post types
	 *
	 * @param int    $post_id The post ID.
	 * @param string $message A log message.
	 * @param array  $data    Optional data.
	 */
	public static function log( $post_id, $message, $data = array() ) {
		global $wpdb;

		$entry = array(
			'timestamp' => time(),
			'message' => $message,
			'data' => $data,
		);

		$log = get_post_meta( $post_id, '_wcp_log', true );
		if ( empty( $log ) )
			$log = '[]';

		$log = json_decode( $log, true );
		$log[] = $entry;
		$log = json_encode( $log );

		update_post_meta( $post_id, '_wcp_log', wp_slash( $log ) );
	}
}

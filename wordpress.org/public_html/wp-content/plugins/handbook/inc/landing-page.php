<?php
/**
 * Class providing landing page functionality.
 *
 * @package handbook
 */

class WPorg_Handbook_Landing_Page {

	/**
	 * Initializes functionality.
	 */
	public static function init() {
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_block_editor_assets' ] );
		add_action( 'admin_head',                  [ __CLASS__, 'add_admin_css' ] );
	}

	/**
	 * Determines if a post is the landing page for its handbook.
	 *
	 * @param int|WP_Post The post object or ID. Use 0 to indicate global post. Default 0. 
	 * @return bool True if the post is the landing page, else false.
	 */
	public static function is_landing_page( $post = 0 ) {
		$post = get_post( $post );
		$post_type = get_post_type( $post );

		// Must have a post.
		if ( ! $post ) {
			return false;
		}

		// Must be a handbook.
		$handbook_obj = WPorg_Handbook_Init::get_handbook( $post_type );
		if ( ! $handbook_obj ) {
			return false;
		}

		// Handbook must have a landing page.
		$landing_page_id = $handbook_obj->get_landing_page( 'ids' );
		if ( ! $landing_page_id ) {
			return false;
		}

		// Post must be the landing page.
		if ( $landing_page_id !== $post->ID ) {
			return false;
		}

		return true;
	}

	/**
	 * Enqueues block editor assets to display a message for a post acting as a
	 * handbook's landing page.
	 */
	public static function enqueue_block_editor_assets() {
		if ( ! self::is_landing_page() ) {
			return;
		}

		$post_type = get_post_type();

		// Enqueue the script.
		wp_enqueue_script(
			'landing-page-script',
			plugins_url( '/scripts/landing-page.js', __DIR__ ),
			[ 'wp-edit-post', 'wp-plugins', 'wp-element' ],
			'1.0.0',
			true
		);

		// Localize the script.
		wp_localize_script(
			'landing-page-script',
			'handbookLandingPage',
			[
				/* translators: %s: The name of the handbook. */
				'message' => sprintf(
					__( "This is the landing page for the %s. A landing page is required. Delete or unpublish it only if it will be immediately replaced.", 'wporg' ),
					WPorg_Handbook::get_name( $post_type )
				),
			]
		);
	}

	/**
	 * Outputs admin CSS for the landing page block editor message.
	 */
	public static function add_admin_css() {
		if ( ! self::is_landing_page() ) {
			return;
		}

		echo '<style>.handbook-landing-page-message { border-left: 4px solid rgb(255,165, 0); background-color: rgb(255,165, 0, 0.1); padding: 4px 4px 4px 8px; }</style>' . "\n";
	}
}

add_action( 'plugins_loaded', [ 'WPorg_Handbook_Landing_Page', 'init' ] );

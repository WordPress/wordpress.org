<?php
namespace WordPressdotorg\Plugin_Directory\Readme;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;

/**
 * A wp-admin interface to validate readme files.
 *
 * @package WordPressdotorg\Plugin_Directory\Readme
 */
class Validator {

	/**
	 * Fetch the instance of the Validator class.
	 *
	 * @static
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Validator();
	}

	/**
	 * Validates a readme by URL.
	 *
	 * @param string $url The URL of the readme to validate.
	 * @return array Array of the readme validation results.
	 */
	public function validate_url( $url ) {
		$url = esc_url_raw( $url );

		if ( strtolower( substr( $url, -10 ) ) != 'readme.txt' ) {
			/* Translators: File name; */
			$error = sprintf( __( 'URL must end in %s!', 'wporg-plugins' ), '<code>readme.txt</code>' );
			return array(
				'errors' => array( $error )
			);
		}

		$readme = wp_safe_remote_get( $url );
		if ( ! $readme_text = wp_remote_retrieve_body( $readme ) ) {
			$error = __( 'Invalid readme.txt URL.', 'wporg-plugins' );
			return array(
				'errors' => array( $error )
			);
		}

		return $this->validate_content( $readme_text );
	}

	/**
	 * Validates readme contents by string.
	 *
	 * @param string $readme The text of the readme.
	 * @return array Array of the readme validation results.
	 */
	public function validate_content( $readme ) {

		$readme = new Parser( 'data:text/plain,' . urlencode( $readme ) );

		$errors = $warnings = $notes = array();

		// Fatal errors.
		if ( empty( $readme->name ) ) {
			/* Translators: Plugin header tag; */
			$errors[] = sprintf( __( "No plugin name detected. Plugin names look like: %s", 'wporg-plugins' ), '<code>=== Plugin Name ===</code>' );
		}

		// Warnings.
		if ( empty( $readme->requires ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( '%s is missing.', 'wporg-plugins' ), '<code>Requires at least</code>' );
		}
		if ( empty( $readme->tested ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( '%s is missing.', 'wporg-plugins' ), '<code>Tested up to</code>' );
		}
		if ( empty( $readme->stable_tag ) ) {
			/* Translators: 1: Plugin header tag; 2: SVN directory; 3: Plugin header tag; */
			$warnings[] = sprintf( __( '%1$s is missing.  Hint: If you treat %2$s as stable, put %3$s.', 'wporg-plugins' ), '<code>Stable tag</code>', '<code>/trunk/</code>', '<code>Stable tag: trunk</code>' );
		}
		if ( ! count( $readme->contributors ) ) {
			/* Translators: Plugin header tag; */
			$warnings[] = sprintf( __( 'No %s listed.', 'wporg-plugins' ), '<code>Contributors</code>' );
		}

		// Notes.
		if ( empty( $readme->sections['faq'] ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Frequently Asked Questions ==</code>' );
		}
		if ( empty( $readme->sections['changelog'] ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Changelog ==</code>' );
		}
		if ( empty( $readme->upgrade_notice ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Upgrade Notice ==</code>' );
		}
		if ( empty( $readme->screenshots ) ) {
			/* Translators: Plugin header tag; */
			$notes[] = sprintf( __( 'No %s section was found', 'wporg-plugins' ), '<code>== Screenshots ==</code>' );
		}
		if ( empty( $readme->donate_link ) ) {
			$notes[] = __( 'No donate link was found', 'wporg-plugins' );
		}

		return compact( 'errors', 'warnings', 'notes' );

	}

}

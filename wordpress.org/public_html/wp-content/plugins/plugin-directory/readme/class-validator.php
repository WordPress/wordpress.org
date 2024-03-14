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

		if (
			strtolower( substr( $url, -10 ) ) != 'readme.txt' &&
			strtolower( substr( $url, -9 ) ) != 'readme.md'
		) {
			$error = sprintf(
				/* translators: 1: readme.txt 2: readme.md */
				__( 'URL must end in %s or %s!', 'wporg-plugins' ),
				'<code>readme.txt</code>', '<code>readme.md</code>'
			);
			return array(
				'errors' => array( $error ),
			);
		}

		$readme = wp_safe_remote_get( $url );
		if ( ! $readme_text = wp_remote_retrieve_body( $readme ) ) {
			$error = __( 'Invalid readme.txt URL.', 'wporg-plugins' );
			return array(
				'errors' => array( $error ),
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

		// Security note: Keep the data: protocol here, Parser accepts a string HOWEVER
		// if a submitted readme.txt URL contents were to contain a file or URL-like string,
		// it could bypass the protections above in validate_url().
		$readme = new Parser( 'data:text/plain,' . urlencode( $readme ) );

		$errors = $warnings = $notes = array();

		// Fatal errors.
		if ( empty( $readme->name ) || isset( $readme->warnings['invalid_plugin_name_header'] ) ) {
			$errors[] = sprintf(
				/* translators: 1: 'Plugin Name' section title, 2: 'Plugin Name' */
				__( 'We cannot find a plugin name in your readme. Plugin names look like: %1$s. Please change %2$s to reflect the actual name of your plugin.', 'wporg-plugins' ),
				'<code>=== Plugin Name ===</code>',
				'<code>Plugin Name</code>'
			);
		}

		// Warnings & Notes.
		if ( isset( $readme->warnings['requires_header_ignored'] ) ) {
			$latest_wordpress_version = defined( 'WP_CORE_STABLE_BRANCH' ) ? WP_CORE_STABLE_BRANCH : '5.0';

			$warnings[] = sprintf(
				/* translators: 1: plugin header tag; 2: Example version 5.0. 3: Example version 4.9. */
				__( 'The %1$s field was ignored. This field should only contain a valid WordPress version such as %2$s or %3$s.', 'wporg-plugins' ),
				'<code>Requires at least</code>',
				'<code>' . number_format( $latest_wordpress_version, 1 ) . '</code>',
				'<code>' . number_format( $latest_wordpress_version - 0.1, 1 ) . '</code>'
			);
		}

		if ( isset( $readme->warnings['tested_header_ignored'] ) ) {
			$latest_wordpress_version = defined( 'WP_CORE_STABLE_BRANCH' ) ? WP_CORE_STABLE_BRANCH : '5.0';

			$warnings[] = sprintf(
				/* translators: 1: plugin header tag; 2: Example version 5.0. 3: Example version 5.1. */
				__( 'The %1$s field was ignored. This field should only contain a valid WordPress version such as %2$s or %3$s.', 'wporg-plugins' ),
				'<code>Tested up to</code>',
				'<code>' . number_format( $latest_wordpress_version, 1 ) . '</code>',
				'<code>' . number_format( $latest_wordpress_version + 0.1, 1 ) . '</code>'
			);
		} elseif ( empty( $readme->tested ) ) {
			$warnings[] = sprintf(
				/* translators: %s: plugin header tag */
				__( 'The %s field is missing.', 'wporg-plugins' ),
				'<code>Tested up to</code>'
			);
		}

		if ( isset( $readme->warnings['requires_php_header_ignored'] ) ) {
			$warnings[] = sprintf(
				/* translators: 1: plugin header tag; 2: Example version 5.2.4. 3: Example version 7.0. */
				__( 'The %1$s field was ignored. This field should only contain a PHP version such as %2$s or %3$s.', 'wporg-plugins' ),
				'<code>Requires PHP</code>',
				'<code>5.2.4</code>',
				'<code>7.0</code>'
			);
		}

		if ( empty( $readme->stable_tag ) || str_contains( $readme->stable_tag, 'trunk' ) ) {
			$warnings[] = sprintf(
				/* translators: 1: 'Stable tag', 2: /trunk/ SVN directory */
				__( 'The %1$s field is missing or invalid.  Note: We ask you no longer attempt to use %2$s as stable, so that all plugins can be rolled back.', 'wporg-plugins' ),
				'<code>Stable tag</code>',
				'<code>/trunk/</code>'
			);
		}

		if ( isset( $readme->warnings['contributor_ignored'] ) ) {
			$warnings[] = sprintf(
				/* translators: %s: plugin header tag */
				__( 'One or more contributors listed were ignored. The %s field should only contain WordPress.org usernames. Remember that usernames are case-sensitive.', 'wporg-plugins' ),
				'<code>Contributors</code>'
			);
		} elseif ( ! count( $readme->contributors ) ) {
			$notes[] = sprintf(
				/* translators: %s: plugin header tag */
				__( 'The %s field is missing.', 'wporg-plugins' ),
				'<code>Contributors</code>'
			);
		}

		if ( isset( $readme->warnings['too_many_tags'] ) ) {
			$warnings[] = __( 'One or more tags were ignored. Please limit your plugin to 5 tags.', 'wporg-plugins' );
		}

		if ( isset( $readme->warnings['ignored_tags'] ) ) {
			$warnings[] = sprintf(
				/* translators: %s: list of tags not supported */
				__( 'One or more tags were ignored. The following tags are not permitted: %s', 'wporg-plugins' ),
				'<code>' . implode( '</code>, <code>', $readme->ignore_tags ) . '</code>'
			);
		}

		if ( isset( $readme->warnings['low_usage_tags'] ) ) {
			$notes[] = sprintf(
				/* translators: %s: list of tags with low usage. */
				__( 'The following tags are not widely used: %s', 'wporg-plugins' ),
				'<code>' . implode( '</code>, <code>', array_map( 'esc_html', $readme->warnings['low_usage_tags'] ) ) . '</code>'
			);
		}

		if ( empty( $readme->requires ) ) {
			$notes[] = sprintf(
				/* translators: %s: plugin header tag */
				__( 'The %s field is missing. It should be defined here, or in your main plugin file.', 'wporg-plugins' ),
				'<code>Requires at least</code>'
			);
		}

		if ( empty( $readme->requires_php ) ) {
			$notes[] = sprintf(
				/* translators: %s: plugin header tag */
				__( 'The %s field is missing. It should be defined here, or in your main plugin file.', 'wporg-plugins' ),
				'<code>Requires PHP</code>'
			);
		}

		if ( isset( $readme->warnings['no_short_description_present'] ) ) {
			$notes[] = sprintf(
				/* translators: %s: section title */
				__( 'The %s section is missing. An excerpt was generated from your main plugin description.', 'wporg-plugins' ),
				'<code>Short Description</code>'
			);
		} elseif( isset( $readme->warnings['trimmed_short_description'] ) ) {
			$warnings[] = sprintf(
				/* translators: %s: section title */
				__( 'The %s section is too long and was truncated. A maximum of 150 characters is supported.', 'wporg-plugins' ),
				'<code>Short Description</code>',
				number_format_i18n( $readme->maximum_field_lengths['short_description'] )
			);
		}

		$trimmed_sections = array_filter( $readme->warnings, function( $warning ) {
			return str_contains( $warning, 'trimmed_section_' );
		}, ARRAY_FILTER_USE_KEY );
		foreach ( $trimmed_sections as $section_name => $dummy ) {
			$section_name = str_replace( 'trimmed_section_', '', $section_name );

			$max_length_field = "section-{$section_name}";
			if ( ! isset( $readme->maximum_field_lengths[ $max_length_field ] ) ) {
				$max_length_field = 'section';
			}

			$warnings[]   = sprintf(
				/* translators: %s: section title */
				__( 'The %s section is too long and was truncated. A maximum of %s words is supported.', 'wporg-plugins' ),
				'<code>' . esc_html( ucwords( str_replace( '_', ' ', $section_name ) ) ) . '</code>',
				number_format_i18n( $readme->maximum_field_lengths[ $max_length_field ] )
			);
		}

		if ( empty( $readme->sections['faq'] ) ) {
			$notes[] = sprintf(
				/* translators: %s: section title */
				__( 'No %s section was found', 'wporg-plugins' ),
				'<code>== Frequently Asked Questions ==</code>'
			);
		}

		if ( empty( $readme->sections['changelog'] ) ) {
			$notes[] = sprintf(
				/* translators: %s: section title */
				__( 'No %s section was found', 'wporg-plugins' ),
				'<code>== Changelog ==</code>'
			);
		}

		if ( empty( $readme->upgrade_notice ) ) {
			$notes[] = sprintf(
				/* translators: %s: section title */
				__( 'No %s section was found', 'wporg-plugins' ),
				'<code>== Upgrade Notice ==</code>'
			);
		}

		if ( empty( $readme->screenshots ) ) {
			$notes[] = sprintf(
				/* translators: %s: section title */
				__( 'No %s section was found', 'wporg-plugins' ),
				'<code>== Screenshots ==</code>'
			);
		}

		if ( empty( $readme->donate_link ) ) {
			$notes[] = __( 'No donate link was found', 'wporg-plugins' );
		}

		return compact( 'errors', 'warnings', 'notes' );

	}

}

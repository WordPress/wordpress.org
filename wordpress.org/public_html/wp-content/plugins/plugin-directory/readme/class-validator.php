<?php
namespace WordPressdotorg\Plugin_Directory\Readme;

use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Trademarks;

/**
 * A wp-admin interface to validate readme files.
 *
 * @package WordPressdotorg\Plugin_Directory\Readme
 */
class Validator {

	/**
	 * Last content validated.
	 *
	 * @var string
	 */
	public $last_content = '';

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
		$output = $this->validate( $readme );

		// Translate error codes to human-readable messages.
		foreach ( $output as $type => $items ) {
			foreach ( $items as $error_code => $error_data ) {
				$output[ $type ][ $error_code ] = $this->translate_code_to_message( $error_code, $error_data );
			}
		}

		return $output;
	}

	/**
	 * Validates a readme by string, and returns a structured array of errors, warnings, and notes.
	 *
	 * These elements can be swapped for a textual translated string at display time via translate_code_to_message().
	 *
	 * @param string $readme The text of the readme.
	 * @return array Array of the readme validation result codes.
	 */
	public function validate( $readme ) {
		$errors = $warnings = $notes = array();

		// Store the last validated content for later use.
		$this->last_content = $readme;

		// Security note: Keep the data: protocol here, Parser accepts a string HOWEVER
		// if a submitted readme.txt URL contents were to contain a file or URL-like string,
		// it could bypass the protections above in validate_url().
		$readme = new Parser( 'data:text/plain,' . urlencode( $readme ) );

		// Fatal errors.
		if ( isset( $readme->warnings['invalid_plugin_name_header'] ) ) {
			$errors['invalid_plugin_name_header'] = $readme->warnings['invalid_plugin_name_header'];
		} elseif ( empty( $readme->name ) ) {
			$errors['invalid_plugin_name_header'] = true;
		}

		if (
			empty( $errors['invalid_plugin_name_header'] ) &&
			( $trademark_check = Trademarks::check( $readme->name, wp_get_current_user() ) )
		) {
			$errors['trademarked_name'] = [
				'trademark' => $trademark_check,
				'context'   => $readme->name,
				'where'     => 'readme',
			];
		}

		// Warnings & Notes.
		if ( isset( $readme->warnings['requires_header_ignored'] ) ) {
			$warnings['requires_header_ignored'] = $readme->warnings['requires_header_ignored'];
		}

		if ( isset( $readme->warnings['tested_header_ignored'] ) ) {
			$warnings['tested_header_ignored'] = $readme->warnings['tested_header_ignored'];
		} elseif ( empty( $readme->tested ) ) {
			$warnings['tested_header_missing'] = true;
		}

		if ( isset( $readme->warnings['requires_php_header_ignored'] ) ) {
			$warnings['requires_php_header_ignored'] = $readme->warnings['requires_php_header_ignored'];
		}

		if ( empty( $readme->stable_tag ) || str_contains( $readme->stable_tag, 'trunk' ) ) {
			$warnings['stable_tag_invalid'] = true;
		}

		if ( isset( $readme->warnings['contributor_ignored'] ) ) {
			$warnings['contributor_ignored'] = $readme->warnings['contributor_ignored'];
		} elseif ( ! count( $readme->contributors ) ) {
			$notes['contributors_missing'] = true;
		}

		if ( ! empty( $readme->warnings['license_missing'] ) ) {
			$warnings['license_missing'] = true;
		} elseif ( ! empty( $readme->warnings['invalid_license'] ) ) {
			$errors['invalid_license'] = $readme->warnings['invalid_license'];
		} elseif ( ! empty( $readme->warnings['unknown_license'] ) ) {
			$notes['unknown_license'] = $readme->warnings['unknown_license'];
		}

		if ( isset( $readme->warnings['too_many_tags'] ) ) {
			$warnings['too_many_tags'] = $readme->warnings['too_many_tags'];
		}

		if ( isset( $readme->warnings['ignored_tags'] ) ) {
			$warnings['ignored_tags'] = $readme->warnings['ignored_tags'];
		}

		// Check if the tags are low-quality (ie. little used)
		if ( $readme->tags && taxonomy_exists( 'plugin_tags' ) ) {
			$tags = get_terms( array(
				'taxonomy' => 'plugin_tags',
				'name'     => $readme->tags,
			) );

			$low_usage_tags = array_filter(
				$tags,
				function( $term ) {
					return $term->count < 5;
				}
			);

			if ( $low_usage_tags ) {
				$notes['low_usage_tags'] = wp_list_pluck( $low_usage_tags, 'name' );
			}
		}

		if ( empty( $readme->requires ) ) {
			$notes['requires_header_missing'] = true;
		}

		if ( empty( $readme->requires_php ) ) {
			$notes['requires_php_header_missing'] = true;
		}

		if ( isset( $readme->warnings['no_short_description_present'] ) ) {
			$notes['no_short_description_present'] = $readme->warnings['no_short_description_present'];

		} elseif ( isset( $readme->warnings['trimmed_short_description'] ) ) {
			$warnings['trimmed_short_description'] = $readme->warnings['trimmed_short_description'];
		}

		$trimmed_sections = array_filter( $readme->warnings, function( $warning ) {
			return str_contains( $warning, 'trimmed_section_' );
		}, ARRAY_FILTER_USE_KEY );
		foreach ( $trimmed_sections as $section_name => $dummy ) {
			$warnings[ $section_name ] = true;
		}

		if ( empty( $readme->sections['faq'] ) ) {
			$notes['faq_missing'] = true;
		}

		if ( empty( $readme->sections['changelog'] ) ) {
			$notes['changelog_missing'] = true;
		}

		if ( empty( $readme->upgrade_notice ) ) {
			$notes['upgrade_notice_missing'] = true;
		}

		if ( empty( $readme->screenshots ) ) {
			$notes['screenshots_missing'] = true;
		}

		if ( empty( $readme->donate_link ) ) {
			$notes['donate_link_missing'] = true;
		}

		return compact( 'errors', 'warnings', 'notes' );
	}

	/**
	 * Translate an error code to a human-readable message.
	 *
	 * @param string $error_code The error code to translate.
	 * @param mixed  $data       Optional data to provide context in the message.
	 * @return string|false The translated message, or false if the error code is not recognized.
	 */
	public function translate_code_to_message( $error_code, $data = false ) {
		if ( $data && is_bool( $data ) ) {
			$data = false;
		}

		switch( $error_code ) {
			case 'invalid_plugin_name_header':
				return sprintf(
					/* translators: 1: 'Plugin Name' section title, 2: 'Plugin Name' */
					__( 'We cannot find a plugin name in your readme. Plugin names look like: %1$s. Please change %2$s to reflect the actual name of your plugin.', 'wporg-plugins' ),
					'<code>=== Plugin Name ===</code>',
					'<code>Plugin Name</code>'
				);
			case 'requires_header_ignored':
				$latest_wordpress_version = defined( 'WP_CORE_STABLE_BRANCH' ) ? WP_CORE_STABLE_BRANCH : '6.5';

				return sprintf(
					/* translators: 1: plugin header tag; 2: Example version 5.0. 3: Example version 4.9. */
					__( 'The %1$s field was ignored. This field should only contain a valid WordPress version such as %2$s or %3$s.', 'wporg-plugins' ),
					'<code>Requires at least</code>',
					'<code>' . number_format( $latest_wordpress_version, 1 ) . '</code>',
					'<code>' . number_format( $latest_wordpress_version - 0.1, 1 ) . '</code>'
				);
			case 'tested_header_ignored':
				$latest_wordpress_version = defined( 'WP_CORE_STABLE_BRANCH' ) ? WP_CORE_STABLE_BRANCH : '6.5';

				return sprintf(
					/* translators: 1: plugin header tag; 2: Example version 5.0. 3: Example version 5.1. */
					__( 'The %1$s field was ignored. This field should only contain a valid WordPress version such as %2$s or %3$s.', 'wporg-plugins' ),
					'<code>Tested up to</code>',
					'<code>' . number_format( $latest_wordpress_version, 1 ) . '</code>',
					'<code>' . number_format( $latest_wordpress_version + 0.1, 1 ) . '</code>'
				);
			case 'tested_header_missing':
				return sprintf(
					/* translators: %s: plugin header tag */
					__( 'The %s field is missing.', 'wporg-plugins' ),
					'<code>Tested up to</code>'
				);
			case 'requires_header_missing':
				return sprintf(
					/* translators: %s: plugin header tag */
					__( 'The %s field is missing. It should be defined here, or in your main plugin file.', 'wporg-plugins' ),
					'<code>Requires at least</code>'
				);
			case 'requires_php_header_ignored':
				global $required_php_version; // WP wp-includes/version.php.
				return sprintf(
					/* translators: 1: plugin header tag; 2: Example version 7.0. 3: Example version 8.2. */
					__( 'The %1$s field was ignored. This field should only contain a PHP version such as %2$s or %3$s.', 'wporg-plugins' ),
					'<code>Requires PHP</code>',
					'<code>' . esc_html( $required_php_version ?? '7.0' ) . '</code>',
					'<code>8.2</code>'
				);
			case 'requires_php_header_missing':
				return sprintf(
					/* translators: %s: plugin header tag */
					__( 'The %s field is missing. It should be defined here, or in your main plugin file.', 'wporg-plugins' ),
					'<code>Requires PHP</code>'
				);
			case 'stable_tag_invalid':
				return sprintf(
					/* translators: 1: 'Stable tag', 2: /trunk/ SVN directory */
					__( 'The %1$s field is missing or invalid.  Note: We ask you no longer attempt to use %2$s as stable, so that all plugins can be rolled back.', 'wporg-plugins' ),
					'<code>Stable tag</code>',
					'<code>/trunk/</code>'
				);
			case 'stable_tag_invalid_trunk_fallback':
				return sprintf(
					/* translators: 1: 'Stable tag', 2: path '/tags/{version}', 3: '/trunk/' */
					__( 'The %1$s field is invalid, the specified SVN tag %2$s does not exist. %3$s will be used instead.', 'wporg-plugins' ),
					'<code>Stable Tag</code>',
					'<code>/tags/' . esc_html( $data ) . '/</code>',
					'<code>/trunk/</code>'
				);
			case 'contributor_ignored':
				if ( ! $data ) {
					return sprintf(
						/* translators: %s: plugin header tag */
						__( 'One or more contributors listed were ignored. The %s field should only contain WordPress.org usernames.', 'wporg-plugins' ),
						'<code>Contributors</code>'
					);
				} else {
					return sprintf(
						/* translators: 1: List of authors from the readme 2: plugin header tag */
						__( 'The following contributors listed were ignored, as the WordPress.org user could not be found. %1$s. The %2$s field should only contain WordPress.org usernames.', 'wporg-plugins' ),
						'<code>' . implode( '</code>, <code>', array_map( 'esc_html', $data ) ) . '</code>',
						'<code>Contributors</code>'
					);
				}
			case 'contributors_missing':
				return sprintf(
					/* translators: %s: plugin header tag */
					__( 'The %s field is missing.', 'wporg-plugins' ),
					'<code>Contributors</code>'
				);
			case 'too_many_tags':
				if ( $data ) {
					return sprintf(
						/* translators: %s: list of tags not supported */
						__( 'One or more tags were ignored: %s. Please limit your plugin to 5 tags.', 'wporg-plugins' ),
						'<code>' . implode( '</code>, <code>', array_map( 'esc_html', $data ) ) . '</code>'
					);
				} else {
					return __( 'One or more tags were ignored. Please limit your plugin to 5 tags.', 'wporg-plugins' );
				}
			case 'ignored_tags':
				return sprintf(
					/* translators: %s: list of tags not supported */
					__( 'One or more tags were ignored. The following tags are not permitted: %s', 'wporg-plugins' ),
					'<code>' . implode( '</code>, <code>', $data ) . '</code>'
				);
			case 'low_usage_tags':
				return sprintf(
					/* translators: %s: list of tags with low usage. */
					__( 'The following tags are not widely used: %s', 'wporg-plugins' ),
					'<code>' . implode( '</code>, <code>', array_map( 'esc_html', $data ) ) . '</code>'
				);
			case 'no_short_description_present':
				return sprintf(
					/* translators: %s: section title */
					__( 'The %s section is missing. An excerpt was generated from your main plugin description.', 'wporg-plugins' ),
					'<code>Short Description</code>'
				);
			case 'trimmed_short_description':
				return sprintf(
					/* translators: %s: section title */
					__( 'The %s section is too long and was truncated. A maximum of %s characters is supported.', 'wporg-plugins' ),
					'<code>Short Description</code>',
					number_format_i18n( (new Parser)->maximum_field_lengths['short_description'] )
				);
			case 'trimmed_section_description':
			case 'trimmed_section_installation':
			case 'trimmed_section_faq':
			case 'trimmed_section_screenshots':
			case 'trimmed_section_changelog':
			case 'trimmed_section_upgrade_notice':
			case 'trimmed_section_other_notes':
				$readme       = new Parser;
				$section_name = str_replace( 'trimmed_section_', '', $error_code );

				$max_length_field = "section-{$section_name}";
				if ( ! isset( $readme->maximum_field_lengths[ $max_length_field ] ) ) {
					$max_length_field = 'section';
				}
	
				return sprintf(
					/* translators: %s: section title */
					__( 'The %s section is too long and was truncated. A maximum of %s words is supported.', 'wporg-plugins' ),
					'<code>' . esc_html( ucwords( str_replace( '_', ' ', $section_name ) ) ) . '</code>',
					number_format_i18n( $readme->maximum_field_lengths[ $max_length_field ] )
				);
			case 'faq_missing':
				return sprintf(
					/* translators: %s: section title */
					__( 'No %s section was found', 'wporg-plugins' ),
					'<code>== Frequently Asked Questions ==</code>'
				);
			case 'changelog_missing':
				return sprintf(
					/* translators: %s: section title */
					__( 'No %s section was found', 'wporg-plugins' ),
					'<code>== Changelog ==</code>'
				);
			case 'upgrade_notice_missing':
				return sprintf(
					/* translators: %s: section title */
					__( 'No %s section was found', 'wporg-plugins' ),
					'<code>== Upgrade Notice ==</code>'
				);
			case 'screenshots_missing':
				return sprintf(
					/* translators: %s: section title */
					__( 'No %s section was found', 'wporg-plugins' ),
					'<code>== Screenshots ==</code>'
				);
			case 'donate_link_missing':
				return __( 'No donate link was found', 'wporg-plugins' );

			case 'license_missing':
				return sprintf(
					/* translators: 1: 'License', 2: Link to a compatible licenses page. */
					__( 'The %1$s field is missing. <a href="%2$s">A GPLv2 or later compatible license</a> should be specified.', 'wporg-plugins' ),
					'<code>License</code>',
					'https://www.gnu.org/licenses/license-list.en.html'
				);

			case 'invalid_license':
				return sprintf(
					/* translators: 1: 'License', 2: Link to a compatible licenses page. */
					__( 'The %1$s field appears to be invalid. <a href="%2$s">A GPLv2 or later compatible license</a> should be specified.', 'wporg-plugins' ),
					'<code>License</code>',
					'https://www.gnu.org/licenses/license-list.en.html'
				);

			case 'unknown_license':
				return sprintf(
					/* translators: 1: 'License', 2: Link to a compatible licenses page. */
					__( 'The %1$s field could not be validated. <a href="%2$s">A GPLv2 or later compatible license</a> should be specified. The specified license may be compatible.', 'wporg-plugins' ),
					'<code>License</code>',
					'https://www.gnu.org/licenses/license-list.en.html'
				);

			case 'trademarked_name':
			case 'trademarked_slug':
			case 'trademarked':
				$trademarks = (array) $data['trademark'];
				$context    = $data['context'];
				$messages   = [];
	
				$cannot_start_with = array_filter( $trademarks, function( $slug ) {
					return str_ends_with( $slug, '-' );
				} );
				$cannot_contain    = array_diff( $trademarks, $cannot_start_with );

				if ( $cannot_start_with ) {
					// Trim the - off.
					$cannot_start_with = array_map( function( $slug ) { return rtrim( $slug, '-' ); }, $cannot_start_with );

					$messages[] = sprintf(
						/* translators: 1: plugin slug, 2: trademarked term, 3: 'Plugin Name:', 4: plugin email address */
						_n(
							'Your chosen plugin name - %1$s - contains the restricted term "%2$s" which cannot be used to begin your permalink or display name. We disallow the use of certain terms in ways that are abused, or potentially infringe on and/or are misleading with regards to trademarks.',
							'Your chosen plugin name - %1$s - contains the restricted terms "%2$s" which cannot be used to begin your permalink or display name. We disallow the use of certain terms in ways that are abused, or potentially infringe on and/or are misleading with regards to trademarks.',
							count( $cannot_start_with ),
							'wporg-plugins'
						),
						'<code>' . $context . '</code>',
						'<code>' . implode( '</code>", "<code>', $cannot_start_with ) . '</code>'
					);
				}
				if ( $cannot_contain ) {
					$messages[] = sprintf(
						/* translators: 1: plugin slug, 2: trademarked term, 3: 'Plugin Name:', 4: plugin email address */
						_n(
							'Your chosen plugin name - %1$s - contains the restricted term "%2$s", which cannot be used at all in your plugin permalink nor the display name.',
							'Your chosen plugin name - %1$s - contains the restricted terms "%2$s", which cannot be used at all in your plugin permalink nor the display name.',
							count( $cannot_contain ),
							'wporg-plugins'
						),
						'<code>' . $context . '</code>',
						'<code>' . implode( '</code>", "<code>', $cannot_contain ) . '</code>'
					);
				}

				return implode( '<br>', $messages );

			/* The are not generated by the Readme Parser, but rather the import parser. */
			case 'invalid_update_uri':
				return sprintf(
					/* translators: %s 'Update URI' */
					__( 'The %s specified is invalid. This field should not be used with WordPress.org hosted plugins.', 'wporg-plugins' ),
					'<code>Update URI</code>'
				);
			case 'unmet_dependencies':
				return sprintf(
					/* translators: %s: list of plugin dependencies */
					__( 'Invalid plugin dependencies specified. The following dependencies could not be resolved: %s', 'wporg-plugins' ),
					'<code>' . implode( '</code>, <code>', array_map( 'esc_html', $data ) ) . '</code>'
				);
		}

		return false;
	}

}

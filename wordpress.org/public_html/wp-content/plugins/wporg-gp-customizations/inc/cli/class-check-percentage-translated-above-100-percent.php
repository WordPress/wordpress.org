<?php
/**
 * This WP-CLI command checks for translation sets with a percentage translated above 100%.
 *
 * The command parses the list of top plugins and themes for all locales to identify
 * any translation sets that have an invalid percentage (above 100%). This can help
 * identify data inconsistencies or calculation errors in the translation system.
 *
 * To execute this command, use:
 *
 * wp wporg-translate check-percentage-translated-above-100-percent --url=translate.wordpress.org
 *
 * Or to check a specific locale:
 *
 * wp wporg-translate check-percentage-translated-above-100-percent --locale=es --url=translate.wordpress.org
 *
 * @package WordPressdotorg\GlotPress\Customizations\CLI
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP_Locales;
use WP_CLI;
use WP_CLI_Command;

/**
 * Class Check_Percentage_Translated_Above_100_Percent
 */
class Check_Percentage_Translated_Above_100_Percent extends WP_CLI_Command {
	/**
	 * Check for translation sets with a percentage translated above 100%.
	 *
	 * This command checks the stats pages for plugins and themes across all locales
	 * (or a specific locale) to identify any translation sets that report a percentage
	 * translated above 100%, which would indicate a data inconsistency.
	 *
	 * ## OPTIONS
	 *
	 * [--locale=<locale>]
	 * : Check only a specific locale (e.g., 'es', 'de', 'fr'). If not specified, all locales will be checked.
	 *
	 * [--type=<type>]
	 * : Check only a specific project type: 'plugins' or 'themes'. If not specified, both will be checked.
	 *
	 * [--verbose]
	 * : Output detailed information during the check process.
	 * ---
	 * default: false
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Check all locales for translation percentages above 100%
	 *     wp wporg-translate check-percentage-translated-above-100-percent --url=translate.wordpress.org
	 *
	 *     # Check only Spanish locale
	 *     wp wporg-translate check-percentage-translated-above-100-percent --locale=es --url=translate.wordpress.org
	 *
	 *     # Check only plugins with verbose output
	 *     wp wporg-translate check-percentage-translated-above-100-percent --type=plugins --verbose --url=translate.wordpress.org
	 *
	 *     # Check themes for a specific locale
	 *     wp wporg-translate check-percentage-translated-above-100-percent --locale=de --type=themes --url=translate.wordpress.org
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       The arguments.
	 * @param array $assoc_args The associative arguments.
	 */
	public function __invoke( $args, $assoc_args ) {
		$specific_locale = isset( $assoc_args['locale'] ) ? $assoc_args['locale'] : null;
		$specific_type   = isset( $assoc_args['type'] ) ? $assoc_args['type'] : null;
		$verbose         = isset( $assoc_args['verbose'] ) ? (bool) $assoc_args['verbose'] : false;

		// Validate type parameter
		if ( $specific_type && ! in_array( $specific_type, array( 'plugins', 'themes' ), true ) ) {
			WP_CLI::error( 'The --type parameter must be either "plugins" or "themes".' );
			return;
		}

		// Get all GlotPress locales
		$gp_locales = GP_Locales::locales();
		$locales_to_check = array();

		if ( $specific_locale ) {
			// Validate the specific locale
			$gp_locale = GP_Locales::by_field( 'slug', $specific_locale );
			if ( ! $gp_locale ) {
				WP_CLI::error( sprintf( 'Locale "%s" not found.', $specific_locale ) );
				return;
			}
			$locales_to_check[ $specific_locale ] = $gp_locale;
			WP_CLI::log( sprintf( 'Checking locale: %s', $specific_locale ) );
		} else {
			$locales_to_check = $gp_locales;
			WP_CLI::log( sprintf( 'Checking all locales (%d total)', count( $locales_to_check ) ) );
		}

		// Determine which project types to check
		$types_to_check = array();
		if ( $specific_type ) {
			$types_to_check[] = $specific_type;
		} else {
			$types_to_check = array( 'plugins', 'themes' );
		}

		WP_CLI::log( sprintf( 'Checking project types: %s', implode( ', ', $types_to_check ) ) );
		WP_CLI::log( '' );

		$issues_found = array();
		$locales_checked = 0;

		foreach ( $locales_to_check as $locale_slug => $locale_obj ) {
			$locales_checked++;

			if ( $verbose ) {
				WP_CLI::log( sprintf( 'Checking locale %s (%d/%d)...', $locale_slug, $locales_checked, count( $locales_to_check ) ) );
			}

			foreach ( $types_to_check as $type ) {
				$url = sprintf(
					'https://translate.wordpress.org/locale/%s/default/stats/%s/',
					$locale_slug,
					$type
				);

				if ( $verbose ) {
					WP_CLI::log( sprintf( '  Fetching: %s', $url ) );
				}

				$invalid_percentages = $this->check_stats_page( $url, $locale_slug, $type, $verbose );

				if ( ! empty( $invalid_percentages ) ) {
					$issues_found = array_merge( $issues_found, $invalid_percentages );
				}
			}
		}

		WP_CLI::log( '' );
		WP_CLI::log( '---' );

		// Output results
		if ( empty( $issues_found ) ) {
			WP_CLI::success( 'All translation percentages are within the valid range (0-100%).' );
		} else {
			WP_CLI::warning( sprintf( 'Found %d translation set(s) with percentage above 100%%:', count( $issues_found ) ) );
			WP_CLI::log( '' );

            foreach ( $issues_found as $issue ) {
                $stats_url = sprintf(
                    'https://translate.wordpress.org/locale/%s/default/stats/%s/',
                    $issue['locale'],
                    $issue['type']
                );
                
                WP_CLI::log( sprintf(
                    '📢❗🚨 Locale: %s | Type: %s | Project: %s | Percentage: %s%% | URL: %s',
                    $issue['locale'],
                    $issue['type'],
                    $issue['project'],
                    $issue['percentage'],
                    $stats_url
                ) );
            }
		}
	}

	/**
	 * Fetch and parse a stats page to check for percentages above 100%.
	 *
	 * @param string $url          The URL of the stats page to check.
	 * @param string $locale       The locale slug.
	 * @param string $type         The project type (plugins or themes).
	 * @param bool   $verbose      Whether to output verbose logging.
	 * @return array Array of issues found (empty if none).
	 */
	private function check_stats_page( $url, $locale, $type, $verbose ) {
		$issues = array();

		// Fetch the page content
		$response = wp_remote_get( $url, array(
			'timeout' => 30,
		) );

		if ( is_wp_error( $response ) ) {
			if ( $verbose ) {
				WP_CLI::warning( sprintf( '  Failed to fetch %s: %s', $url, $response->get_error_message() ) );
			}
			return $issues;
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $http_code ) {
			if ( $verbose ) {
				WP_CLI::warning( sprintf( '  HTTP %d for %s', $http_code, $url ) );
			}
			return $issues;
		}

		$body = wp_remote_retrieve_body( $response );

		// Parse the HTML to find translation percentages
		// The stats pages typically show percentages in a specific format
		// Look for patterns like "123%" or data attributes with percentage values
		
		// Pattern 1: Look for percentage in spans or divs with class containing 'percent'
		// Pattern 2: Look for data-percent attributes
		// Pattern 3: Look for explicit percentage text patterns
		
		// Using DOMDocument to parse HTML more reliably
		if ( empty( $body ) ) {
			return $issues;
		}

		// Suppress warnings from malformed HTML
		libxml_use_internal_errors( true );
		$dom = new \DOMDocument();
		$dom->loadHTML( $body );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );

		// Find all elements that might contain percentage information
		// Looking for table rows that represent project entries
		$rows = $xpath->query( "//tr[contains(@class, 'project-status')]" );

		if ( ! $rows || 0 === $rows->length ) {
			// Try alternative selectors
			$rows = $xpath->query( "//tr[td[contains(@class, 'percent')]]" );
		}

		if ( $rows && $rows->length > 0 ) {
			foreach ( $rows as $row ) {
				$project_name = '';
				$percentage = null;

				// Try to extract project name
				$project_cells = $xpath->query( ".//td[contains(@class, 'project')]//a | .//th[contains(@class, 'project')]//a", $row );
				if ( $project_cells && $project_cells->length > 0 ) {
					$project_name = trim( $project_cells->item( 0 )->textContent );
				}

				// Try to extract percentage
				// Look for percent class or data-percent attribute
				$percent_cells = $xpath->query( ".//td[contains(@class, 'percent')] | .//td[@data-percent]", $row );
				if ( $percent_cells && $percent_cells->length > 0 ) {
					$percent_cell = $percent_cells->item( 0 );
					
					// Try data-percent attribute first
					if ( $percent_cell->hasAttribute( 'data-percent' ) ) {
						$percentage = (float) $percent_cell->getAttribute( 'data-percent' );
					} else {
						// Parse text content
						$text = trim( $percent_cell->textContent );
						if ( preg_match( '/(\d+(?:\.\d+)?)%?/', $text, $matches ) ) {
							$percentage = (float) $matches[1];
						}
					}
				}

				// Check if percentage is above 100
				if ( null !== $percentage && $percentage > 100 ) {
					$issues[] = array(
						'locale'     => $locale,
						'type'       => $type,
						'project'    => $project_name ? $project_name : 'Unknown',
						'percentage' => $percentage,
					);

					if ( $verbose ) {
						WP_CLI::log( sprintf( '  📢❗🚨 Found: %s at %s%%', $url, $percentage ) );
					}
				}
			}
		} else {
			// Fallback: use regex pattern matching
			$issues = array_merge( $issues, $this->check_with_regex( $body, $locale, $type, $verbose ) );
		}

		return $issues;
	}

	/**
	 * Fallback method to check for percentages using regex.
	 *
	 * @param string $html    The HTML content to parse.
	 * @param string $locale  The locale slug.
	 * @param string $type    The project type.
	 * @param bool   $verbose Whether to output verbose logging.
	 * @return array Array of issues found.
	 */
	private function check_with_regex( $html, $locale, $type, $verbose ) {
		$issues = array();

		// Pattern to match percentages over 100
		// This is a broad pattern and may need refinement based on actual HTML structure
		if ( preg_match_all( '/(?:>|data-percent=["\']?)(1\d{2}(?:\.\d+)?|[2-9]\d{2,}(?:\.\d+)?)%?(?:<|["\'])/i', $html, $matches ) ) {
			foreach ( $matches[1] as $percentage ) {
				$pct = (float) $percentage;
				if ( $pct > 100 ) {
					$issues[] = array(
						'locale'     => $locale,
						'type'       => $type,
						'project'    => 'Unknown (regex match)',
						'percentage' => $pct,
					);

					if ( $verbose ) {
						WP_CLI::log( sprintf( '    Found (regex): %s%%', $pct ) );
					}
				}
			}
		}

		return $issues;
	}
}

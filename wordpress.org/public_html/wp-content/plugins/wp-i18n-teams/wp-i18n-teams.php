<?php
/*
Plugin Name: WP I18N Teams
Description: Provides shortcodes for displaying details about translation teams.
Version:     1.0
License:     GPLv2 or later
Author:      WordPress.org
Author URI:  http://wordpress.org/
Text Domain: wporg
*/

class WP_I18n_Teams {
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );
	}

	/**
	 * Attaches hooks and registers shortcodes once plugins are loasded.
	 */
	function plugins_loaded() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_shortcode( 'wp-locales',      array( $this, 'wp_locales' ) );
	}

	/**
	 * Enqueue JavaScript and CSS
	 */
	public function enqueue_assets() {
		if ( is_singular() && false !== strpos( get_post()->post_content, '[wp-locales' ) ) {
			wp_enqueue_style( 'wp-i18n-teams', plugins_url( 'css/i18n-teams.css', __FILE__ ), array(), 2 );
			wp_enqueue_script( 'wp-i18n-teams', plugins_url( 'js/i18n-teams.js', __FILE__ ), array( 'jquery' ), 2 );
		}
	}

	/**
	 * Render the [wp-locales] shortcode.
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	public function wp_locales( $attributes ) {
		ob_start();

		if ( empty( $_GET['locale'] ) ) {
			$locales = self::get_locales();
			$locale_data = $this->get_locales_data();
			$percentages = $this->get_core_translation_data();
			require( __DIR__ . '/views/all-locales.php' );
		} else {
			require_once( WPORGPATH . 'translate/glotpress/locales/locales.php' );
			$locale = GP_Locales::by_field( 'wp_locale', $_GET['locale'] );
			$locale_data = $this->get_extended_locale_data( $locale );
			require( __DIR__ . '/views/locale-details.php' );
		}

		return ob_get_clean();
	}

	/**
	 * Get GlotPress locales that have a wp_locale, sorted alphabetically.
	 *
	 * @return array
	 */
	protected static function get_locales() {
		require_once( WPORGPATH . 'translate/glotpress/locales/locales.php' );

		$locales = GP_Locales::locales();
		$locales = array_filter( $locales, array( __CLASS__, 'filter_locale_for_wp' ) );
		unset( $locales['en'] );
		usort( $locales, array( __CLASS__, 'sort_locales' ) );

		return $locales;
	}

	/**
	 * Remove locales that are missing a wp_locale.
	 *
	 * This is a callback for array_filter().
	 *
	 * @param GP_Locale $element
	 *
	 * @return bool
	 */
	protected static function filter_locale_for_wp( $element ) {
		return isset( $element->wp_locale );
	}

	/**
	 * Sort GlotPress locales alphabetically by the English name.
	 *
	 * @param GP_Locale $a
	 * @param GP_Locale $b
	 *
	 * @return int
	 */
	protected static function sort_locales( $a, $b ) {
		return strcmp( $a->english_name, $b->english_name );
	}

	/**
	 * Gather all the required data and cache it.
	 */
	public function get_locales_data() {
		global $wpdb;

		$cache = get_transient( 'wp_i18n_teams_locales_data' );
		if ( false !== $cache ) {
			return $cache;
		}

		$gp_locales = self::get_locales();
		$translation_data = $this->get_core_translation_data();
		$locale_data = array();

		$statuses = array(
			'no-site'            => 0,
			'no-releases'        => 0,
			'latest'             => 0,
			'minor-behind'       => 0,
			'major-behind-one'   => 0,
			'major-behind-many'  => 0,
			'translated-100'     => 0,
			'translated-95'      => 0,
			'translated-90'      => 0,
			'translated-50'      => 0,
			'translated-50-less' => 0,
		);

		$wporg_data = $wpdb->get_results( "SELECT locale, subdomain, latest_release FROM locales ORDER BY locale", OBJECT_K );

		foreach ( $gp_locales as $locale ) {
			$subdomain = $wporg_data[ $locale->wp_locale ]->subdomain;
			$latest_release = $wporg_data[ $locale->wp_locale ]->latest_release;
			$release_status = self::get_locale_release_status( $subdomain, $latest_release );
			$statuses[ $release_status ]++;

			$translation_status = '';
			if ( isset ( $translation_data[ $locale->wp_locale ] ) ) {
				$translation_status = self::get_locale_translation_status( $translation_data[ $locale->wp_locale ] );
				$statuses[ $translation_status ]++;
			}

			$locale_data[ $locale->wp_locale ] = array(
				'release_status'     => $release_status,
				'translation_status' => $translation_status,
				'rosetta_site_url'   => $subdomain ? 'https://' . $subdomain . '.wordpress.org' : false,
				'latest_release'     => $latest_release ? $latest_release : false,
			);
		}

		$locale_data['status_counts'] = $statuses;
		$locale_data['status_counts']['all'] = count( $gp_locales );
		set_transient( 'wp_i18n_teams_locales_data', $locale_data, 900 );
		return $locale_data;
	}

	public function get_extended_locale_data( $locale ) {
		$locales_data = $this->get_locales_data();
		$locale_data = $locales_data[ $locale->wp_locale ];
		$locale_data['localized_core_url'] = $locale_data['language_pack_url'] = false;

		$latest_release = $locale_data['latest_release'];
		if ( $latest_release ) {
			list( $x, $y ) = explode( '.', $latest_release );
			$latest_branch = "$x.$y";
			$locale_data['localized_core_url'] = sprintf( '%s/wordpress-%s-%s.zip', $locale_data['rosetta_site_url'], $latest_release, $locale->wp_locale );

			if ( version_compare( $latest_release, '4.0', '>=' ) ) {
				$locale_data['language_pack_url'] = sprintf( 'https://downloads.wordpress.org/translation/core/%s/%s.zip', $latest_branch, $locale->wp_locale );
			}
		}

		$contributors = self::get_contributors( $locale );
		$locale_data['validators'] = $contributors['validators'];
		$locale_data['translators'] = $contributors['translators'];

		return $locale_data;
	}

	public function get_core_translation_data() {
		$cache = get_transient( 'core_translation_data' );
		if ( false !== $cache ) {
			return $cache;
		}

		$projects = array( 'wp/dev', 'wp/dev/admin', 'wp/dev/admin/network' );
		$counts = $percentages = array();
		foreach ( $projects as $project ) {
			$results = json_decode( file_get_contents( 'https://translate.wordpress.org/api/projects/' . $project ) );
			foreach ( $results->translation_sets as $set ) {
				if ( $set->slug !== 'default' ) {
					continue;
				}
				if ( ! isset( $counts[ $set->wp_locale ] ) ) {
					$counts[ $set->wp_locale ] = array( 'current' => 0, 'total' => 0 );
				}
				$counts[ $set->wp_locale ]['total'] += (int) $set->current_count + (int) $set->untranslated_count;
				$counts[ $set->wp_locale ]['current'] += (int) $set->current_count;
			}
		}
		foreach ( $counts as $locale => $count ) {
			$percentages[ $locale ] = ( $count['total'] > 0 ) ? floor( $count['current'] / $count['total'] * 100 ) : 0;
		}
		set_transient( 'core_translation_data', $percentages, 900 );
		return $percentages;
	}

	/**
	 * Get the translators and validators for the given locale.
	 *
	 * @param GP_Locale $locale
	 * @return array
	 */
	public static function get_contributors( $locale ) {
		require_once( API_WPORGPATH . 'core/credits/wp-credits.php' );

		$credits = WP_Credits::factory( WP_CORE_LATEST_RELEASE, $locale );
		$results = $credits->get_results();

		$contributors = array(
			'validators'  => ! empty( $results['groups']['validators']['data'] )  ? $results['groups']['validators']['data']  : array(),
			'translators' => ! empty( $results['groups']['translators']['data'] ) ? $results['groups']['translators']['data'] : array(),
		);

		return $contributors;
	}

	/**
	 * Determine the release status of the given locale,
	 *
	 * @param string $rosetta_site_url
	 * @param string $latest_release
	 *
	 * @return string
	 */
	protected static function get_locale_release_status( $rosetta_site_url, $latest_release ) {
		if ( ! $rosetta_site_url ) {
			return 'no-site';
		}

		if ( ! $latest_release ) {
			return 'no-releases';
		}

		$one_lower = WP_CORE_LATEST_RELEASE - 0.1;

		if ( $latest_release == WP_CORE_LATEST_RELEASE ) {
			return 'latest';
		} elseif ( substr( $latest_release, 0, 3 ) == substr( WP_CORE_LATEST_RELEASE, 0, 3 ) ) {
			return 'minor-behind';
		} elseif ( substr( $latest_release, 0, 3 ) == substr( $one_lower, 0, 3 ) ) {
			return 'major-behind-one';
		} else {
			return 'major-behind-many';
		}
	}

	/**
	 * Determine the translation status of the given locale.
	 *
	 * @param int $percent_translated
	 *
	 * @return string
	 */
	protected static function get_locale_translation_status( $percent_translated ) {
		if ( $percent_translated == 100 ) {
			return 'translated-100';
		} elseif ( $percent_translated >= 95 ) {
			return 'translated-95';
		} elseif ( $percent_translated >= 90 ) {
			return 'translated-90';
		} elseif ( $percent_translated >= 50 ) {
			return 'translated-50';
		} else {
			return 'translated-50-less';
		}
	}
}

$GLOBALS['wp_i18n_teams'] = new WP_I18n_Teams();

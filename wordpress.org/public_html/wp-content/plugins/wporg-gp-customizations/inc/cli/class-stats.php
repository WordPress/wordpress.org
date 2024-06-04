<?php
/**
 * This WP-CLI command has to be in the sandbox in the
 * /home/wporg/public_html/wp-content/plugins/wporg-gp-customizations/inc/cli folder
 *
 * To execute this command, you need to use this text in the CLI:
 *
 * wp wporg-translate show-stats --url=translate.wordpress.org
 */

namespace WordPressdotorg\GlotPress\Customizations\CLI;

require WP_PLUGIN_DIR . '/wp-i18n-teams/wp-i18n-teams.php';

use DateTime;
use GP_Locale;
use WP_CLI;
use WP_Query;
use WordPressdotorg\GlotPress\Routes\Plugin;
use function WordPressdotorg\Locales\get_locales;
use function WordPressdotorg\I18nTeams\Locales\get_locales_data;

class Stats {

	/**
	 * Number of years backward from which you want to obtain statistics.
	 * First translation was added to the database at 2010-02-17 18:09:13.
	 *
	 * @var int
	 */
	private int $number_of_years = 3;

	/**
	 * Number of translators with more translations each year to get feedback from.
	 *
	 * @var int
	 */
	private int $number_of_translators = 100;

	/**
	 * First user at DotOrg registered the current year.
	 *
	 * @var int
	 */
	private int $id_first_user_of_this_year = 0;

	/**
	 * Percentage of non-EN_US sites.
	 */
	private string $translated_sites_pct = '';

	/**
	 * Id of each forum database, for each country
	 *
	 * @var int[]
	 */
	private array $forum_ids = array(
		'pt'                => 383,
		'make'              => 384,
		// ''      => 385,
					'emoji' => 386,
		'hau'               => 387,
		'fao'               => 388,
		'af'                => 389,
		'am'                => 390,
		'id'                => 391,
		'mya'               => 392,
		'ja'                => 393,
		'ru'                => 394,
		'de'                => 395,
		'bs'                => 396,
		'th'                => 397,
		'cn'                => 398,
		'ak'                => 399,
		'an'                => 400,
		'ar'                => 401,
		'arq'               => 402,
		'ug'                => 403,
		'tw'                => 404,
		'bg'                => 405,
		'sr'                => 406,
		'hr'                => 407,
		'nl'                => 408,
		'he'                => 409,
		'ka'                => 410,
		'fi'                => 411,
		'mk'                => 412,
		'ca'                => 413,
		'sk'                => 414,
		'pl'                => 415,
		'ro'                => 416,
		'es'                => 417,
		'br'                => 418,
		'en'                => 419,
		'sq'                => 420,
		'hy'                => 421,
		'rup'               => 422,
		'frp'               => 423,
		'as'                => 424,
		'ast'               => 425,
		'az'                => 426,
		'az-tr'             => 427,
		'bcc'               => 428,
		'eu'                => 429,
		'bel'               => 430,
		'bn'                => 431,
		'bre'               => 432,
		'ceb'               => 433,
		'zh-hk'             => 434,
		'co'                => 435,
		'cs'                => 436,
		'et'                => 437,
		'eo'                => 438,
		'uz'                => 439,
	);

	private string $header                           = '';
	private string $originals_by_year                = '';
	private string $originals_by_translation_source  = '';
	private string $translations_translators_by_year = '';
	private string $forum_post_and_replies_by_year   = '';
	private string $wordpress_translation_percentage = '';
	private string $packages_generated_by_year       = '';
	private string $themes_plugins_by_year           = '';
	private string $feedback_received                = '';
	private string $contributors_per_locale          = '';
	private string $managers_stats                   = '';
	private string $most_active_translators          = '';
	private string $stats_comparison                 = '';


	/**
	 * The name of the custom post type used to the translation feedback.
	 */
	private const FEEDBACK_POST_TYPE = 'gth_original';

	/**
	 * Whether the class should print the info in the CLI or not.
	 *
	 * @var bool
	 */
	private bool $echo_the_values = false;

	/**
	 * The id of the blog where the stats are stored.
	 *
	 * @var int
	 */
	private const MAKE_POLYGLOTS_BLOG_ID = 19;

	/**
	 * The id of the page where the stats are stored.
	 *
	 * @var int
	 */
	private const POLYGLOTS_PAGE_ID = 42132;

	/**
	 * Prints the Polyglots stats or stores them on a page.
	 *
	 * @param bool        $echo_the_values Whether it should print the info in the CLI or stores it on a page.
	 * @param string|null $old_date        The date to compare the stats with. Format: 'Y-m-d'.
	 *
	 * @return void
	 */
	public function __invoke( bool $echo_the_values = false, string $old_date = null ): void {
		global $wpdb;

		// This value is only set in the production site (translate.wordpress.org).
		// This check avoids to fire the cron job from another sites.
		if ( ! isset( $wpdb->project_translation_status ) ) {
			return;
		}

		$this->echo_the_values = $echo_the_values;
		$this->set_number_of_years_with_data();
		$this->print_header();
		$this->print_wordpress_translation_percentage();
		$this->print_stats_for_translation_sources();
		$this->print_packages_generated();
		$this->print_unique_themes_plugins_by_year();
		$this->print_originals_natural_year();
		$this->print_total_translations_translators_by_year();
		$this->print_forum_by_locale_and_year();
		$this->print_feedback_received();
		$this->print_contributors_per_locale();
		$this->print_managers_stats();
		$this->print_most_active_translators();
		// Don't store the stats if we execute the command in the CLI, to avoid storing the same stats twice.
		if ( ! $echo_the_values ) {
			$this->store_stats();
		}
		$this->print_stats_comparison( gmdate( 'Y-m-d' ), $old_date );

		$this->update_page();
	}

	/**
	 * Store the generated stats in the database
	 *
	 * @return void
	 */
	private function store_stats() {
		global $wpdb;

		$this->translated_sites_pct = $this->get_translated_sites_pct();

		$contributors_per_locale  = Plugin::get_contributors_count();
		$total_contributors_count = array_sum( $contributors_per_locale );

		$all_locales_data = get_locales_data();
		$stats_data       = $all_locales_data['status_counts'];
		$total_gtes       = array_sum( $this->count_managers( 'general_translation_editor' ) );
		$total_ptes       = array_sum( $this->count_managers( 'translation_editor' ) );

		$locale_requests = $this->get_locale_requests();
		$editor_requests = $this->get_editor_requests();

		$result = $wpdb->insert(
			'polyglot_stats',
			array(
				'releases_by_locale'                    => $stats_data['all'],
				'releases_by_locale_uptodate'           => $stats_data['latest'],
				'releases_by_locale_minor_behind'       => $stats_data['minor-behind'],
				'releases_by_locale_one_major_behind'   => $stats_data['major-behind-one'],
				'releases_by_locale_multi_major_behind' => $stats_data['major-behind-many'],
				'locales_total'                         => $this->get_core_total(),
				'locales_100'                           => $this->get_core_full_translated(),
				'locales_95_plus'                       => $this->get_core_interval( 100, 95 ),
				'locales_90_plus'                       => $this->get_core_interval( 95, 90 ),
				'locales_50_plus'                       => $this->get_core_interval( 90, 50 ),
				'locales_below_50'                      => $this->get_core_interval( 50, 0, '<', '>' ),
				'locales_with_language_packs'           => $stats_data['has-language-pack'],
				'locales_without_project'               => $stats_data['no-wp-project'],
				'requests_total'                        => $editor_requests['total'],
				'requests_unresolved'                   => $editor_requests['unresolved_editor_requests'],
				'locale_requests_total'                 => $locale_requests['total'],
				'locale_requests_unresolved'            => $locale_requests['unresolved_locale_requests'],
				'translators_gtes'                      => $total_gtes,
				'translators_ptes'                      => $total_ptes,
				'translators_contributors'              => $total_contributors_count,
				'wp_translated_sites_pct'               => $this->translated_sites_pct,
				'date'                                  => gmdate( 'Y-m-d' ),
			)
		);
	}

	/**
	 * Fetch total number of locale requests and number of unresolved locale requests.
	 *
	 * @return array $locale_requests Array of the count of each of resolved and unresolved locale requests.
	 */
	private function get_locale_requests() {
		$locale_requests = array();
		switch_to_blog( self::MAKE_POLYGLOTS_BLOG_ID );
		$args                     = array(
			'post_type'   => 'post',
			'tag'         => 'locale-requests',
			'post_status' => 'publish',
		);
		$locale_requests['total'] = ( new WP_query( $args ) )->found_posts;
		register_taxonomy(
			'p2_resolved',
			'post',
			array(
				'public'    => true,
				'query_var' => 'resolved',
				'rewrite'   => false,
				'show_ui'   => false,
			)
		);

		$args = array(
			'tag'       => 'locale-requests',
			'tax_query' => array(
				array(
					'taxonomy' => 'p2_resolved',
					'field'    => 'slug',
					'terms'    => 'unresolved',
				),
			),
		);
		$locale_requests['unresolved_locale_requests'] = ( new WP_query( $args ) )->found_posts;

		restore_current_blog();

		return $locale_requests;
	}

	/**
	 * Fetch total number of editor requests and number of unresolved editor requests.
	 *
	 * @return array $editor_requests Array of the count of each of resolved and unresolved editor requests.
	 */
	private function get_editor_requests() {
		$editor_requests = array();
		switch_to_blog( self::MAKE_POLYGLOTS_BLOG_ID );
		register_taxonomy(
			'p2_resolved',
			'post',
			array(
				'public'    => true,
				'query_var' => 'resolved',
				'rewrite'   => false,
				'show_ui'   => false,
			)
		);
		$args                     = array(
			'post_type'   => 'post',
			'tag'         => 'editor-requests',
			'post_status' => 'publish',
		);
		$editor_requests['total'] = ( new WP_query( $args ) )->found_posts;
		$args                     = array(
			'post_type' => 'post',
			'tag'       => 'editor-requests',
			'tax_query' => array(
				array(
					'taxonomy' => 'p2_resolved',
					'field'    => 'slug',
					'terms'    => 'unresolved',
				),
			),
		);
		$editor_requests['unresolved_editor_requests'] = ( new WP_query( $args ) )->found_posts;
		restore_current_blog();

		return $editor_requests;
	}

	/**
	 * Fetch stats data for a specific date.
	 *
	 * @param string $date The date for the record to be retrieved.
	 *
	 * @return mixed
	 */
	private function get_data_for_date( $date ) {
		global $wpdb;
		$sql     = "SELECT * FROM polyglot_stats WHERE date = '" . $date . "'";
		$results = $wpdb->get_results( $sql );
		return $results[0];
	}


	/**
	 * Get the percentage of non-EN_US sites.
	 *
	 * @return float
	 */
	private function get_translated_sites_pct() {
		$translation_stats_json  = file_get_contents( 'https://api.wordpress.org/stats/locale/1.0/' );
		$ranslation_stats_array  = $translation_stats_json && '{' == $translation_stats_json[0] ? json_decode( $translation_stats_json, true ) : null;
		$wp_translated_sites_pct = 100 - $ranslation_stats_array['English (US)'];
		return $wp_translated_sites_pct;
	}

	/**
	 * Print stats compared week on week.
	 *
	 * @param string $current_date The date for which we display the stats.
	 * @param string $old_date     The date to compare the stats with.
	 *
	 * @return void
	 */
	private function print_stats_comparison( $current_date, $old_date = null ) {
		if ( ! $this->is_date_valid( $current_date ) || ( $old_date && ! $this->is_date_valid( $old_date ) ) ) {
			return;
		}
		$old_date          = is_null( $old_date ) ? date( 'Y-m-d', strtotime( '-1 week' ) ) : $old_date;
		$current_date_data = $this->get_data_for_date( $current_date );
		$old_date_data     = $this->get_data_for_date( $old_date );

		if ( ! $current_date_data || ! $old_date_data ) {
			return;
		}

		$current_datetime = DateTime::createFromFormat( 'Y-m-d', $current_date );
		$old_datetime     = DateTime::createFromFormat( 'Y-m-d', $old_date );
		if ( ! $current_datetime || ! $old_datetime ) {
			return;
		}
		$interval        = $current_datetime->diff( $old_datetime );
		$days_difference = $interval->days;

		if ( ! $this->echo_the_values ) {
			$this->stats_comparison = $this->create_gutenberg_heading( "Summary for the last $days_difference days" );
		} else {
			$this->print_wpcli_heading( "Summary for the last $days_difference days" );
		}
		$stats_diff = new \stdClass();
		foreach ( $current_date_data as $key => $value ) {
			$stats_diff->$key = $value - $old_date_data->$key;
		}
		$all_locales_data = get_locales_data();
		$stats_data       = $all_locales_data['status_counts'];

		$code = 'Below stats are dated ' . $current_date . ' compared to ' . $old_date . ' (differences between brackets)' . PHP_EOL . PHP_EOL;

		$code .= 'Releases: ' . $current_date_data->releases_by_locale . ' (' . $this->prefix_num( $stats_diff->releases_by_locale ) . ') locale, ' . $current_date_data->releases_by_locale_uptodate . ' (' . $this->prefix_num( $stats_diff->releases_by_locale_uptodate ) . ') up to date, ' . $current_date_data->releases_by_locale_minor_behind . ' (' . $this->prefix_num( $stats_diff->releases_by_locale_minor_behind ) . ') behind by minor versions, ' . $current_date_data->releases_by_locale_one_major_behind . ' (' . $this->prefix_num( $stats_diff->releases_by_locale_one_major_behind ) . ') behind by one major version, ' . $current_date_data->releases_by_locale_multi_major_behind . ' (' . $this->prefix_num( $stats_diff->releases_by_locale_multi_major_behind ) . ') behind more than one major version, ' . $stats_data['no-site'] . ' (N/A) have site but never released, ' . $stats_data['no-releases'] . ' (N/A) have no site.' . PHP_EOL . PHP_EOL;

		$code .= 'Translations: ' . $current_date_data->locales_total . ' (' . $this->prefix_num( $stats_diff->locales_total ) . ') total, ' . $current_date_data->locales_100 . ' (' . $this->prefix_num( $stats_diff->locales_100 ) . ') at 100%, ' . $current_date_data->locales_95_plus . ' (' . $this->prefix_num( $stats_diff->locales_95_plus ) . ') over 95%, ' . $current_date_data->locales_90_plus . ' (' . $this->prefix_num( $stats_diff->locales_90_plus ) . ') over 90%, ' . $current_date_data->locales_50_plus . ' (' . $this->prefix_num( $stats_diff->locales_50_plus ) . ') over 50%, ' . $current_date_data->locales_below_50 . ' (' . $this->prefix_num( $stats_diff->locales_below_50 ) . ') below 50%, ' . $current_date_data->locales_with_language_packs . ' (' . $this->prefix_num( $stats_diff->locales_with_language_packs ) . ') have a language pack generated, ' . $current_date_data->locales_without_project . ' (' . $this->prefix_num( $stats_diff->locales_without_project ) . ') have no project.' . PHP_EOL . PHP_EOL;

		$code .= 'Requests: There are ' . $current_date_data->requests_unresolved . ' unresolved editor requests out of ' . $current_date_data->requests_total . ' (' . $this->prefix_num( $stats_diff->requests_unresolved ) . ') total and ' . $current_date_data->locale_requests_unresolved . ' unresolved locale requests out of ' . $current_date_data->locale_requests_total . ' (' . $this->prefix_num( $stats_diff->locale_requests_unresolved ) . ') total.' . PHP_EOL . PHP_EOL;

		$code .= 'Translators: There are ' . number_format_i18n( $current_date_data->translators_gtes ) . ' (' . $this->prefix_num( $stats_diff->translators_gtes ) . ') GTEs, ' . number_format_i18n( $current_date_data->translators_ptes ) . ' (' . $this->prefix_num( $stats_diff->translators_ptes ) . ') PTEs and ' . number_format_i18n( $current_date_data->translators_contributors ) . ' (' . $this->prefix_num( $stats_diff->translators_contributors ) . ') translation contributors.' . PHP_EOL;
		$code .= '(A wordpress.org account could have multiple roles over different locale).' . PHP_EOL . PHP_EOL;

		$code .= 'Site language: ' . $current_date_data->wp_translated_sites_pct . '% (' . $this->prefix_num( round( $stats_diff->wp_translated_sites_pct, 3 ), 3 ) . '%) of WordPress sites are running a translated WordPress site. ' . PHP_EOL;
		if ( ! $this->echo_the_values ) {
			$this->stats_comparison .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Checks if date is valid
	 *
	 * @param string $date The date that needs to be validated.
	 *
	 * @return boolean True if date is valid and False if invalid
	 */
	private function is_date_valid( $date, $format = 'Y-m-d' ) {
		$_date = DateTime::createFromFormat( $format, $date );
		return $_date && $_date->format( $format ) === $date;
	}

	/**
	 * Prefix numbers greater than zero with plus sign and plus_minus sign if number is zero.
	 *
	 * @param float $number          Number to be prefixed.
	 * @param int   $number_decimals Number of decimals to be displayed.
	 *
	 * @return string Prefixed number
	 */
	private function prefix_num( $number, $number_decimals = 0 ) {
		if ( 0 === $number ) {
			return '±0';
		}
		return $number > 0 ? sprintf( '+%s', number_format_i18n( $number, $number_decimals ) ) : number_format_i18n( $number, $number_decimals );
	}

	/**
	 * Set the number of years between 2010 (first year with translations) and the current year.
	 *
	 * @return void
	 */
	private function set_number_of_years_with_data(): void {
		$this->number_of_years = gmdate( 'Y' ) - 2010 + 1;
	}

	/**
	 * Print the main header.
	 *
	 * @return void
	 */
	private function print_header(): void {
		if ( ! $this->echo_the_values ) {
			$this->header  = $this->create_gutenberg_paragraph( 'Polyglots stats. Created at ' . gmdate( 'Y-m-d H:i:s' ) . ' ' . date_default_timezone_get() );
			$this->header .= $this->create_gutenberg_paragraph( 'Created using the <b>wp wporg-translate show-stats --url=translate.wordpress.org</b> command.' );
		} else {
			$this->print_wpcli_heading( 'Polyglots stats. Created at ' . gmdate( 'Y-m-d H:i:s' ) . ' ' . date_default_timezone_get() );
		}
	}

	/**
	 * Print the number of original strings grouped by year.
	 *
	 * @return void
	 */
	private function print_originals_natural_year(): void {
		global $wpdb;
		$originals = $wpdb->get_results(
			"SELECT
				YEAR( date_added ) as year,
				count(*) as strings
			FROM {$wpdb->gp_originals} 
			GROUP BY YEAR( date_added )
			ORDER BY YEAR( date_added ) DESC"
		);

		if ( ! $this->echo_the_values ) {
			$this->originals_by_year = $this->create_gutenberg_heading( 'Number of originals in the last ' . $this->number_of_years . ' years.' );
		} else {
			$this->print_wpcli_heading( 'Number of originals in the last ' . $this->number_of_years . ' years.' );
		}
		$code  = "Year \t\t Number of strings" . PHP_EOL;
		$code .= '................................................................' . PHP_EOL;

		foreach ( $originals as $original ) {
			if ( gmdate( 'Y' ) == $original->year ) {
				$originals_estimated = $this->estimate_value_for_full_year( $original->strings );
				$code               .= $original->year . " (*) \t " . number_format_i18n( $originals_estimated ) . PHP_EOL;
			}
			$code .= $original->year . " \t\t " . number_format_i18n( $original->strings ) . PHP_EOL;
		}
		$code .= '................................................................' . PHP_EOL;
		$code .= '(*) Estimated for the current year.' . PHP_EOL;
		$code .= PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->originals_by_year .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the number of locales, with and without variants, and the % of core translation in each locale.
	 *
	 * @return void
	 */
	private function print_wordpress_translation_percentage(): void {
		global $wpdb;
		$core_total  = $this->get_core_total();
		$core_100    = $this->get_core_full_translated();
		$core_95_100 = $this->get_core_interval( 100, 95 );
		$core_90_95  = $this->get_core_interval( 95, 90 );
		$core_50_90  = $this->get_core_interval( 90, 50 );
		$core_50     = $this->get_core_interval( 50, 0, '<', '>' );
		$core_0      = $this->get_core_empty_translated();

		if ( ! $this->echo_the_values ) {
			$this->wordpress_translation_percentage = $this->create_gutenberg_heading( 'WordPress core: Translated percentage by the locale.' );
		} else {
			$this->print_wpcli_heading( 'WordPress core: Translated percentage by the locale.' );
		}

		$code  = '................................................................' . PHP_EOL;
		$code .= "Number of locales:                         \t" . number_format_i18n( count( $this->get_existing_locales() ) ) . PHP_EOL;
		$code .= "Number of locales with variants:           \t" . number_format_i18n( count( $this->get_existing_locales( 'with_variants' ) ) ) . PHP_EOL;
		$code .= '................................................................' . PHP_EOL;
		$code .= 'Info from the historical stats.' . PHP_EOL;
		$code .= "Number of WordPress (wp/dev) to translate: \t" . $core_total . PHP_EOL;
		$code .= "100% WordPress translated:                 \t" . $core_100 . PHP_EOL;
		$code .= "95-100% WordPress translated:              \t" . $core_95_100 . PHP_EOL;
		$code .= "90-95% WordPress translated:               \t" . $core_90_95 . PHP_EOL;
		$code .= "50-90% WordPress translated:               \t" . $core_50_90 . PHP_EOL;
		$code .= "0-50% WordPress translated:                \t" . $core_50 . PHP_EOL;
		$code .= "0% WordPress translated:                   \t" . $core_0 . PHP_EOL;
		$code .= '................................................................' . PHP_EOL;
		$code .= 'The difference between the number of locales and the number of ' . PHP_EOL;
		$code .= "WordPress (wp/dev) is due to some duplicated variants. \n" . PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->wordpress_translation_percentage .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the packages generated each year.
	 *
	 * @return void
	 */
	private function print_packages_generated() {
		global $wpdb;

		$packages = $wpdb->get_results(
			"SELECT
				LEFT( updated, 4 ) as year,
				SUM( CASE WHEN type = 'core' THEN 1 ELSE 0 END ) as core_packs,
				SUM( CASE WHEN type = 'plugin' THEN 1 ELSE 0 END ) as plugin_packs,
				SUM( CASE WHEN type = 'theme' THEN 1 ELSE 0 END ) as theme_packs,
				count(*) as total_packs
			FROM language_packs
			WHERE updated >= '2010-01-01'
			GROUP BY LEFT( updated, 4 )
			ORDER BY LEFT( updated, 4 ) DESC",
			ARRAY_A
		);

		$header = 'Language Packs generated per year.';
		if ( ! $this->echo_the_values ) {
			$this->packages_generated_by_year = $this->create_gutenberg_heading( $header );
		} else {
			$this->print_wpcli_heading( $header );
		}
		$code  = "Year \t Core Packs \t Plugin Packs \t Theme Packs \t Total Packs " . PHP_EOL;
		$code .= '.......................................................................' . PHP_EOL;

		foreach ( $packages as $package ) {
			if ( gmdate( 'Y' ) == $package['year'] ) {
				$code .= str_pad( $package['year'] . ' (*) ', 10 ) .
						 str_pad( number_format_i18n( $this->estimate_value_for_full_year( $package['core_packs'] ) ), 16 ) .
						 str_pad( number_format_i18n( $this->estimate_value_for_full_year( $package['plugin_packs'] ) ), 16 ) .
						 str_pad( number_format_i18n( $this->estimate_value_for_full_year( $package['theme_packs'] ) ), 16 ) .
						 str_pad( number_format_i18n( $this->estimate_value_for_full_year( $package['total_packs'] ) ), 16 ) .
						 PHP_EOL;
			}
			$code .= str_pad( $package['year'], 10 ) .
					 str_pad( number_format_i18n( $package['core_packs'] ), 16 ) .
					 str_pad( number_format_i18n( $package['plugin_packs'] ), 16 ) .
					 str_pad( number_format_i18n( $package['theme_packs'] ), 16 ) .
					 str_pad( number_format_i18n( $package['total_packs'] ), 16 ) .
					 PHP_EOL;
		}
		$code .= '.......................................................................' . PHP_EOL;
		$code .= '(*) Estimated for the current year.' . PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->packages_generated_by_year .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the Unique Plugins / Themes language packs per year.
	 *
	 * @return void
	 */
	private function print_unique_themes_plugins_by_year() {
		global $wpdb;

		$packages = $wpdb->get_results(
			"SELECT
				year,
				SUM( CASE WHEN type = 'plugin' THEN 1 ELSE 0 END ) as plugins,
				SUM( CASE WHEN type = 'theme' THEN 1 ELSE 0 END ) as themes,
				count(*) as total
			FROM (
				SELECT
					domain,
					type,
					LEFT( updated, 4 ) as year
				FROM language_packs
				WHERE updated >= '2010-01-01' AND type IN( 'plugin', 'theme' )
				GROUP BY domain, type, LEFT( updated, 4 )
				ORDER BY type, LEFT( updated, 4 )+0 ASC
			)a
			GROUP BY year
			ORDER BY year DESC",
			ARRAY_A
		);

		$header = 'Unique Plugins / Themes language packs per year.';
		if ( ! $this->echo_the_values ) {
			$this->themes_plugins_by_year = $this->create_gutenberg_heading( $header );
		} else {
			$this->print_wpcli_heading( $header );
		}
		$code  = "Year \t Plugins \t Themes \t Total" . PHP_EOL;
		$code .= '................................................................' . PHP_EOL;

		foreach ( $packages as $package ) {
			if ( gmdate( 'Y' ) == $package['year'] ) {
				$code .= str_pad( $package['year'] . ' (*) ', 10 ) .
						 str_pad( number_format_i18n( $this->estimate_value_for_full_year( $package['plugins'] ) ), 16 ) .
						 str_pad( number_format_i18n( $this->estimate_value_for_full_year( $package['themes'] ) ), 16 ) .
						 str_pad( number_format_i18n( $this->estimate_value_for_full_year( $package['total'] ) ), 16 ) .
						 PHP_EOL;
			}
			$code .= str_pad( $package['year'], 10 ) .
					 str_pad( number_format_i18n( $package['plugins'] ), 16 ) .
					 str_pad( number_format_i18n( $package['themes'] ), 16 ) .
					 str_pad( number_format_i18n( $package['total'] ), 16 ) .
					 PHP_EOL;
		}
		$code .= '................................................................' . PHP_EOL;
		$code .= '(*) Estimated for the current year.' . PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->themes_plugins_by_year .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the number of translations and translators in the last years.
	 *
	 * We don't use a query like this one because we get MySQL timeout:
	 *      $translators =  $wpdb->get_var(
	 *      $wpdb->prepare(
	 *      "SELECT COUNT( DISTINCT user_id) as translators
	 *      FROM gp_translations
	 *      WHERE date_modified >= '%s' and date_modified <= '%s'",
	 *      '2022-01-01 00:00:00',
	 *      '2022-12-31 23:59:59'
	 *      ) );
	 *
	 * We get the first and the last translation id for each year and
	 * then count the number of different translators.
	 *
	 * @return void
	 */
	private function print_total_translations_translators_by_year() {
		global $wpdb;
		if ( ! $this->echo_the_values ) {
			$this->translations_translators_by_year = $this->create_gutenberg_heading( 'Number of translations and translators in the last ' . $this->number_of_years . ' years.' );
		} else {
			$this->print_wpcli_heading( 'Number of translations and translators in the last ' . $this->number_of_years . ' years.' );
		}

		$code       = "Year \t\t Number of translations \t Translators \t Translators with > 1 translation" . PHP_EOL;
		$code      .= '................................................................' . PHP_EOL;
		$last_year  = gmdate( 'Y' );
		$first_year = $last_year - $this->number_of_years;
		$first_id   = 0;

		for ( $year = $last_year; $year > $first_year; $year -- ) {
			if ( gmdate( 'Y' ) == $year ) {
				$last_id = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->gp_translations}" );
			} else {
				$last_id = $first_id - 1;
			}

			$first_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MIN(id) FROM {$wpdb->gp_translations} WHERE date_added BETWEEN %s AND %s",
					$year . '-01-01 00:00:00',
					$year . '-01-02 00:00:00',
				)
			);

			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as strings_added,
						COUNT( DISTINCT user_id ) as contributors
					FROM {$wpdb->gp_translations}
					WHERE id BETWEEN %d AND %d",
					$first_id,
					$last_id,
				),
				ARRAY_A
			);

			$repeat_contributors_val = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(user_id) FROM (
						SELECT DISTINCT user_id, count(id) as translations_number
						FROM {$wpdb->gp_translations} 
						WHERE id BETWEEN %d AND %d 
						GROUP BY user_id 
						HAVING translations_number > 1
					) a",
					$first_id,
					$last_id,
				)
			);

			if ( gmdate( 'Y' ) == $year ) {
				$strings_added       = str_pad( number_format_i18n( $this->estimate_value_for_full_year( $row['strings_added'] ) ), 10, ' ', STR_PAD_LEFT );
				$contributors        = str_pad( number_format_i18n( $this->estimate_value_for_full_year( $row['contributors'] ) ), 6, ' ', STR_PAD_LEFT );
				$repeat_contributors = str_pad( number_format_i18n( $this->estimate_value_for_full_year( $repeat_contributors_val ) ), 8, ' ', STR_PAD_LEFT );
				$code               .= "{$year} (*) \t {$strings_added} \t\t\t {$contributors} \t {$repeat_contributors}" . PHP_EOL;
			}
			$strings_added       = number_format_i18n( $row['strings_added'] );
			$contributors        = number_format_i18n( $row['contributors'] );
			$strings_added       = str_pad( $strings_added, 10, ' ', STR_PAD_LEFT );
			$contributors        = str_pad( $contributors, 6, ' ', STR_PAD_LEFT );
			$repeat_contributors = str_pad( number_format_i18n( $repeat_contributors_val ), 8, ' ', STR_PAD_LEFT );
			$code               .= "{$year} \t\t {$strings_added} \t\t\t {$contributors} \t {$repeat_contributors}" . PHP_EOL;
		}

		$code .= '................................................................' . PHP_EOL;
		$code .= '(*) Estimated for the current year.' . PHP_EOL;
		$code .= PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->translations_translators_by_year .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	private function print_forum_by_locale_and_year() {
		$last_year     = gmdate( 'Y' );
		$first_year    = $last_year - $this->number_of_years;
		$forum_posts   = array();
		$forum_replies = array();
		$code          = '';

		for ( $year = $last_year; $year > $first_year; $year -- ) {
			$forum_posts[ $year ]   = $this->get_forums_stats( 'topic', $year );
			$forum_replies[ $year ] = $this->get_forums_stats( 'reply', $year );
		}

		ksort( $this->forum_ids );

		$header = 'Forums. Topics by year and locale.';
		if ( ! $this->echo_the_values ) {
			$this->forum_post_and_replies_by_year = $this->create_gutenberg_heading( $header );
		} else {
			$this->print_wpcli_heading( $header );
		}

		$code .= str_pad( 'Locale', 12 );
		for ( $year = $last_year; $year > $first_year; $year -- ) {
			$code .= str_pad( $year, 8, ' ', STR_PAD_LEFT );
		}
		$code .= PHP_EOL;

		foreach ( $this->forum_ids as $key => $value ) {
			$code .= str_pad( $key, 12 );
			for ( $year = $last_year; $year > $first_year; $year -- ) {
				$code .= str_pad( number_format_i18n( $forum_posts[ $year ][ $key ] ), 8, ' ', STR_PAD_LEFT );
			}
			$code .= PHP_EOL;
		}

		if ( ! $this->echo_the_values ) {
			$this->forum_post_and_replies_by_year .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}

		$header = 'Forums. Replies by year and locale.';
		if ( ! $this->echo_the_values ) {
			$this->forum_post_and_replies_by_year .= $this->create_gutenberg_heading( $header );
		} else {
			$this->print_wpcli_heading( $header );
		}

		$code = str_pad( 'Locale', 12 );
		for ( $year = $last_year; $year > $first_year; $year -- ) {
			$code .= str_pad( $year, 8, ' ', STR_PAD_LEFT );
		}
		$code .= PHP_EOL;

		foreach ( $this->forum_ids as $key => $value ) {
			$code .= str_pad( $key, 12 );
			for ( $year = $last_year; $year > $first_year; $year -- ) {
				$code .= str_pad( number_format_i18n( $forum_replies[ $year ][ $key ] ), 8, ' ', STR_PAD_LEFT );
			}
			$code .= PHP_EOL;
		}
		if ( ! $this->echo_the_values ) {
			$this->forum_post_and_replies_by_year .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the most active translators in the last years.
	 *
	 * @return void
	 */
	private function print_most_active_translators(): void {
		global $wpdb;

		$last_year  = gmdate( 'Y' );
		$first_year = $last_year - $this->number_of_years;
		$first_id   = 0;

		if ( ! $this->echo_the_values ) {
			$this->most_active_translators = $this->create_gutenberg_heading( 'Most active translators in the last ' . $this->number_of_years . ' years.' );
		} else {
			$this->print_wpcli_heading( 'Most active translators in the last ' . $this->number_of_years . ' years.' );
		}

		$code = "Year \t Translations \t Translator" . PHP_EOL;
		for ( $year = $last_year; $year > $first_year; $year -- ) {
			if ( gmdate( 'Y' ) == $year ) {
				$last_id = $wpdb->get_var( "SELECT MAX(id) FROM {$wpdb->gp_translations}" );
			} else {
				$last_id = $first_id - 1;
			}

			$first_id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT MIN(id) FROM {$wpdb->gp_translations} WHERE date_added >= %s",
					$year . '-01-01'
				)
			);

			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT  
						user_id, 
						COUNT(*) as strings_added 
					FROM {$wpdb->gp_translations} 
					WHERE id BETWEEN %d AND %d
					GROUP BY user_id 
					ORDER BY `strings_added` DESC 
					LIMIT %d",
					$first_id,
					$last_id,
					$this->number_of_translators
				),
				ARRAY_A
			);

			$code .= '................................................................' . PHP_EOL;
			foreach ( $rows as $row ) {
				if ( 0 == $row['user_id'] ) {
					continue;
				}
				$strings_added = number_format_i18n( $row['strings_added'] );
				$contributor   = get_user_by( 'id', $row['user_id'] );
				if ( $contributor ) {
					$code .= "{$year} \t  {$strings_added}  \t {$contributor->user_login}" . PHP_EOL;
				}
			}
		}

		if ( ! $this->echo_the_values ) {
			$this->most_active_translators .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Estimate the value for the end of the year, using a direct rule of 3.
	 *
	 * @param int $current_value The value used to calculate the estimation at the end of the year.
	 *
	 * @return int The estimated value.
	 */
	private function estimate_value_for_full_year( int $current_value ): int {
		$current_day_of_year  = gmdate( 'z' ) + 1;
		$days_in_current_year = date_diff( new DateTime( 'last day of december' ), new DateTime( 'first day of january' ) )->days + 1;

		return round( $current_value * $days_in_current_year / $current_day_of_year );
	}

	/**
	 *
	 * We release the feedback functionality on July 28, 2022
	 * in the  Polyglots Coffee Break
	 * https://make.wordpress.org/polyglots/2022/06/28/polyglots-coffee-break-july-28-2022-at-2200-utc/
	 *
	 * @return void
	 */
	private function print_feedback_received() {
		global $wpdb;
		$original_strings_with_comments = 0;
		$total_comments                 = 0;
		$optin_users                    = 0;
		$comment_meta_translation_ids   = array();
		$comment_user_ids               = array();
		$commenters_number              = 0;
		$commenters                     = array();
		$status_counter                 = array(
			'changesrequested'       => 0,
			'current'                => 0,
			'current_from_rejection' => 0,
			'fuzzy'                  => 0,
			'rejected'               => 0,
			'old'                    => 0,
			'unknown'                => 0,
			'waiting'                => 0,
		);

		// Get the number of opt-in users.
		$optin_users = number_format_i18n(
			$wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(user_id) as optin_users
						FROM {$wpdb->usermeta}
						WHERE meta_key = %s AND meta_value LIKE %s",
					$wpdb->base_prefix . WPORG_TRANSLATE_BLOGID . '_gp_default_sort',
					'%s:19:\"notifications_optin\";s:2:\"on\";%'
				)
			)
		);

		// Get the number of original strings with comments: one CPT for each original.
		$feedback_posts_args = array(
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
			'post_type'      => self::FEEDBACK_POST_TYPE,
			'date_query'     => array(
				array( 'after' => '2022-07-28' ),
			),
		);
		$feedback_posts_count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->posts WHERE post_status='publish' AND post_type=%s AND post_date > %s",
				self::FEEDBACK_POST_TYPE,
				'2022-07-28'
			)
		);
		$original_strings_with_comments = number_format_i18n( $feedback_posts_count );

		// Get the total number of comments.
		$total_comments = number_format_i18n(
			get_comments(
				array(
					'number'     => - 1,
					'post_type'  => self::FEEDBACK_POST_TYPE,
					'count'      => true,
					'date_query' => array(
						array( 'after' => '2022-07-28' ),
					),
				)
			)
		);

		// Get some info related with the status of the translations who get feedback.
		// First, get the comments related with a translation, because we can get comments related
		// only with the original.
		$comment_meta_translation_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT cm.meta_value FROM $wpdb->commentmeta cm, $wpdb->comments c, $wpdb->posts p WHERE p.post_status='publish' AND p.post_type=%s AND p.post_date > %s AND cm.meta_key = 'translation_id' AND c.comment_post_id = p.id AND cm.comment_id = c.comment_id",
				self::FEEDBACK_POST_TYPE,
				'2022-07-28'
			)
		);

		// Check all comments with a related translation.
		foreach ( $comment_meta_translation_ids as $comment_meta_translation_id ) {
			$translation = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT s.locale, t.id, t.original_id, t.user_id, t.status 
					FROM {$wpdb->gp_translations} t
					INNER JOIN {$wpdb->gp_translation_sets} s ON t.translation_set_id = s.id 
					WHERE t.id = %s",
					$comment_meta_translation_id
				)
			);
			if ( ! $translation ) {
				continue;
			}

			// If this translation was rejected, I look for a current translation for the same original (original_id),
			// translator (user_id) and language (locale).
			if ( 'rejected' == $translation->status ) {
				$is_current_from_rejection = $wpdb->get_row(
					$wpdb->prepare(
						"SELECT t.id, s.locale
						FROM {$wpdb->gp_translations} t
						INNER JOIN {$wpdb->gp_translation_sets} s ON t.translation_set_id = s.id 
						WHERE
							t.status='current' 
							AND t.original_id = %d  
							AND t.user_id = %d 
							AND s.locale = %s",
						$translation->original_id,
						$translation->user_id,
						$translation->locale
					)
				);
				if ( $is_current_from_rejection ) {
					$status_counter['current_from_rejection'] ++;
				}
			}

			$status_counter[ $translation->status ?: 'unknown' ] ++;
		}

		// Get most active commenter's.
		$comment_user_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.user_id FROM $wpdb->comments c, $wpdb->posts p WHERE p.post_status='publish' AND p.post_type=%s AND p.post_date > %s AND c.comment_post_id = p.id",
				self::FEEDBACK_POST_TYPE,
				'2022-07-28'
			)
		);

		$commenters_with_comment_count = array_count_values( $comment_user_ids );
		arsort( $commenters_with_comment_count );

		$commenters_number = number_format_i18n( count( $commenters_with_comment_count ) );
		foreach ( $commenters_with_comment_count as $user_id => $comment_number ) {
			$user                  = get_user_by( 'id', $user_id );
			$user->comments_number = $comment_number;
			$commenters[]          = $user;
		}

		// Format and print the info.
		$comment_meta_translation_id_number = number_format_i18n( count( $comment_meta_translation_ids ) );
		$rejected_number                    = number_format_i18n( $status_counter['rejected'] );
		$current_from_rejection_number      = number_format_i18n( $status_counter['current_from_rejection'] );
		$current_number                     = number_format_i18n( $status_counter['current'] );
		$fuzzy_number                       = number_format_i18n( $status_counter['fuzzy'] );
		$old_number                         = number_format_i18n( $status_counter['old'] );

		if ( ! $this->echo_the_values ) {
			$this->feedback_received = $this->create_gutenberg_heading( 'Feedback in the last ' . $this->number_of_years . ' years (starting on 2022-07-28).' );
		} else {
			$this->print_wpcli_heading( 'Feedback in the last ' . $this->number_of_years . ' years (starting on 2022-07-28).' );
		}
		$code  = "Opt-in users: \t\t\t\t\t {$optin_users}" . PHP_EOL;
		$code .= "Original strings with comments: \t\t {$original_strings_with_comments}" . PHP_EOL;
		$code .= "Comments: \t\t\t\t\t {$total_comments}" . PHP_EOL;
		$code .= "Different translations with comments: \t\t {$comment_meta_translation_id_number}" . PHP_EOL;
		$code .= " - Rejected translations: \t\t\t {$rejected_number}" . PHP_EOL;
		$code .= " - Approved translations from a rejection: \t {$current_from_rejection_number}" . PHP_EOL;
		$code .= " - Approved translations (not rejected): \t {$current_number}" . PHP_EOL;
		$code .= " - Fuzzy translations (not rejected): \t\t {$fuzzy_number}" . PHP_EOL;
		$code .= " - Old translations: \t\t\t\t {$old_number}" . PHP_EOL;
		$code .= "Number of different commenters: \t\t {$commenters_number}" . PHP_EOL;
		foreach ( $commenters as $commenter ) {
			if ( strlen( $commenter->user_login ) > 10 ) {
				$tabs = "\t";
			} else {
				$tabs = "\t\t";
			}
			$url             = 'https://profiles.wordpress.org/' . sanitize_title_with_dashes( $commenter->user_login );
			$comments_number = number_format_i18n( $commenter->comments_number );
			$code           .= " - {$commenter->user_login}: {$tabs} {$comments_number} comments. Profile: {$url}" . PHP_EOL;
		}
		$code .= PHP_EOL;
		if ( ! $this->echo_the_values ) {
			$this->feedback_received .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the total number of LM, GTE and PTE and grouped by locale.
	 *
	 * @return void
	 */
	private function print_managers_stats() {
		$this->id_first_user_of_this_year = $this->get_id_first_user_of_this_year();
		$locales                          = get_locales();

		$locale_managers                          = $this->count_managers( 'locale_manager', 'total' );
		$registered_this_year_new_locale_managers = $this->count_managers( 'locale_manager', 'registered_this_year' );
		$started_this_year_new_locale_managers    = $this->count_managers( 'locale_manager', 'started_this_year' );

		$general_translation_editors                          = $this->count_managers( 'general_translation_editor' );
		$registered_this_year_new_general_translation_editors = $this->count_managers( 'general_translation_editor', 'registered_this_year' );
		$started_this_year_new_general_translation_editors    = $this->count_managers( 'general_translation_editor', 'started_this_year' );

		$project_translation_editors                          = $this->count_managers( 'translation_editor' );
		$registered_this_year_new_project_translation_editors = $this->count_managers( 'translation_editor', 'registered_this_year' );
		$started_this_year_new_translation_editor             = $this->count_managers( 'translation_editor', 'started_this_year' );

		if ( ! $this->echo_the_values ) {
			$this->managers_stats = $this->create_gutenberg_heading( 'Managers stats.' );
		} else {
			$this->print_wpcli_heading( 'Managers stats.' );
		}

		$code  = 'Local managers (LM):' . PHP_EOL;
		$code .= " - Total:\t\t\t\t\t\t\t\t" . number_format_i18n( array_sum( $locale_managers ) ) . PHP_EOL;
		$code .= " - Total users that have been registered this year and get the role:\t" . number_format_i18n( array_sum( $registered_this_year_new_locale_managers ) ) . PHP_EOL;
		$code .= " - Total users that have start translating this year and get the role:\t" . number_format_i18n( array_sum( $started_this_year_new_locale_managers ) ) . PHP_EOL;
		foreach ( $locales as $locale ) {
			if ( array_key_exists( $locale->english_name, $locale_managers ) ) {
				$code .= "\t - " . $locale->english_name . ': ' . number_format_i18n( $locale_managers[ $locale->english_name ] ) . ', ' . number_format_i18n( $registered_this_year_new_locale_managers[ $locale->english_name ] ) . ', ' . number_format_i18n( $started_this_year_new_locale_managers[ $locale->english_name ] ) . PHP_EOL;
			}
		}

		$code .= 'General Translator Editors (GTE):' . PHP_EOL;
		$code .= " - Total:\t\t\t\t\t\t\t\t" . number_format_i18n( array_sum( $general_translation_editors ) ) . PHP_EOL;
		$code .= " - Total users that have been registered this year and get the role:\t" . number_format_i18n( array_sum( $registered_this_year_new_general_translation_editors ) ) . PHP_EOL;
		$code .= " - Total users that have start translating this year and get the role:\t" . number_format_i18n( array_sum( $started_this_year_new_general_translation_editors ) ) . PHP_EOL;
		foreach ( $locales as $locale ) {
			if ( array_key_exists( $locale->english_name, $locale_managers ) ) {
				$code .= "\t - " . $locale->english_name . ': ' . number_format_i18n( $general_translation_editors[ $locale->english_name ] ) . ', ' . number_format_i18n( $registered_this_year_new_general_translation_editors[ $locale->english_name ] ) . ', ' . number_format_i18n( $started_this_year_new_general_translation_editors[ $locale->english_name ] ) . PHP_EOL;
			}
		}

		$code .= 'Project Translation Editors (PTE):' . PHP_EOL;
		$code .= " - Total:\t\t\t\t\t\t\t\t" . number_format_i18n( array_sum( $project_translation_editors ) ) . PHP_EOL;
		$code .= " - Total users that have been registered this year and get the role:\t" . number_format_i18n( array_sum( $registered_this_year_new_project_translation_editors ) ) . PHP_EOL;
		$code .= " - Total users that have start translating this year and get the role:\t" . number_format_i18n( array_sum( $started_this_year_new_translation_editor ) ) . PHP_EOL;
		foreach ( $locales as $locale ) {
			if ( array_key_exists( $locale->english_name, $locale_managers ) ) {
				$code .= "\t - " . $locale->english_name . ': ' . number_format_i18n( $project_translation_editors[ $locale->english_name ] ) . ', ' . number_format_i18n( $registered_this_year_new_project_translation_editors[ $locale->english_name ] ) . ', ' . number_format_i18n( $started_this_year_new_translation_editor[ $locale->english_name ] ) . PHP_EOL;
			}
		}

		if ( ! $this->echo_the_values ) {
			$this->managers_stats .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the number of contributors per locale.
	 *
	 * @return void
	 */
	private function print_contributors_per_locale():void {
		$locales = get_locales();
		$header  = 'Contributors per locale';

		if ( ! $this->echo_the_values ) {
			$this->contributors_per_locale = $this->create_gutenberg_heading( $header );
		} else {
			$this->print_wpcli_heading( $header );
		}

		$code  = '.........................................................................................' . PHP_EOL;
		$code .= 'Active contributor: 1 translation in the last 365 days.' . PHP_EOL;
		$code .= '.........................................................................................' . PHP_EOL;
		$code .= "Locale \t\t\t\t Active contributors Past contributors All contributors" . PHP_EOL;
		$code .= '.........................................................................................' . PHP_EOL;
		foreach ( $locales as $locale ) {
			$current_contributors = $this->get_translation_contributors( $locale, 365 );
			$all_contributors     = $this->get_translation_contributors( $locale );
			$code                .= str_pad( $locale->english_name, 40 ) .
									str_pad( number_format_i18n( count( $current_contributors ) ), 20 ) .
									str_pad( number_format_i18n( count( $all_contributors ) - count( $current_contributors ) ), 20 ) .
									str_pad( number_format_i18n( count( $all_contributors ) ), 20 ) . PHP_EOL;
		}

		if ( ! $this->echo_the_values ) {
			$this->contributors_per_locale .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Print the stats for translation sources.
	 *
	 * @return void
	 */
	private function print_stats_for_translation_sources(): void {
		global $wpdb;
		// Source used: frontend, file import, playground
		$originals = $wpdb->get_results(
			"SELECT 
    			meta_key, 
    			meta_value, 
    			count(*) as number_of_strings 
			FROM `translate_meta` 
			WHERE 
			    object_type = 'translation' 
			  	AND meta_key = 'source' 
			    AND meta_value <> '' 
			GROUP BY 
			    meta_key, 
			    meta_value 
			ORDER BY 
			    `translate_meta`.
			    `meta_key` ASC, 
			    count(*) desc
			"
		);
		if ( ! $this->echo_the_values ) {
			$this->originals_by_translation_source = $this->create_gutenberg_heading( 'Number of translations by translation source (starting on 2023-06-30)' );
		} else {
			$this->print_wpcli_heading( 'Number of translations by translation source (starting on 2023-06-30)' );
		}
		$code  = "Source \t\t\t\t Number of strings" . PHP_EOL;
		$code .= '................................................................' . PHP_EOL;

		foreach ( $originals as $original ) {
			$code .= str_pad( ucfirst( $original->meta_value ), 15 ) . " \t\t " . str_pad( number_format_i18n( $original->number_of_strings ), 15, ' ', STR_PAD_LEFT ) . PHP_EOL;
		}
		$code .= PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->originals_by_translation_source .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}

		// Suggestion used: TM, OpenAI, DeepL, undefined.
		$suggestions = $wpdb->get_results(
			"SELECT 
    			meta_key, 
    			meta_value, 
    			count(*) as number_of_strings 
			FROM `translate_meta` 
			WHERE 
			    object_type = 'translation' 
			  	AND meta_key = 'suggestion_used' 
			    AND 
                	(meta_value LIKE 'tm%' 
                     OR meta_value LIKE 'openai%'
                     OR meta_value LIKE 'deepl%')  
			GROUP BY 
			    meta_key, 
			    meta_value 
			ORDER BY 
			    `translate_meta`.
			    `meta_key` ASC, 
			    count(*) desc
			"
		);
		if ( ! $this->echo_the_values ) {
			$this->originals_by_translation_source .= $this->create_gutenberg_heading( 'Number of translations by suggestion source (starting on 2023-06-30)' );
		} else {
			$this->print_wpcli_heading( 'Number of translations by suggestion source (starting on 2023-06-30)' );
		}
		$code  = "Source \t\t\t\t Number of strings" . PHP_EOL;
		$code .= '................................................................' . PHP_EOL;

		foreach ( $suggestions as $suggestion ) {
			$code .= str_pad( $suggestion->meta_value, 15 ) . " \t\t " . str_pad( number_format_i18n( $suggestion->number_of_strings ), 15, ' ', STR_PAD_LEFT ) . PHP_EOL;
		}
		$code .= PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->originals_by_translation_source .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}

		// Suggestion from other languages.
		$suggestions_ol = $wpdb->get_results(
			"SELECT 
    			meta_key, 
    			meta_value, 
    			count(*) as number_of_strings 
			FROM `translate_meta` 
			WHERE 
			    object_type = 'translation' 
			  	AND meta_key = 'suggestion_used' 
			    AND 
                	NOT (meta_value LIKE 'tm%' 
                     OR meta_value LIKE 'undefined%' 
                     OR meta_value LIKE 'openai%'
                     OR meta_value LIKE 'deepl%')  
			GROUP BY 
			    meta_key, 
			    meta_value 
			ORDER BY 
			    `translate_meta`.
			    `meta_key` ASC, 
			    count(*) desc
			"
		);
		if ( ! $this->echo_the_values ) {
			$this->originals_by_translation_source .= $this->create_gutenberg_heading( 'Number of translations suggested from another language (starting on 2023-06-30)' );
		} else {
			$this->print_wpcli_heading( 'Number of translations suggested from another language (starting on 2023-06-30)' );
		}
		$code  = "Language \t\t\t\t Number of strings" . PHP_EOL;
		$code .= '................................................................' . PHP_EOL;

		foreach ( $suggestions_ol as $suggestion ) {
			$code .= str_pad( $suggestion->meta_value, 15 ) . " \t\t " . str_pad( number_format_i18n( $suggestion->number_of_strings ), 15, ' ', STR_PAD_LEFT ) . PHP_EOL;
		}
		$code .= PHP_EOL;

		if ( ! $this->echo_the_values ) {
			$this->originals_by_translation_source .= $this->create_gutenberg_code( $code );
		} else {
			WP_CLI::log( $code );
		}
	}

	/**
	 * Get the locales at DotOrg.
	 *
	 * @param string $locale_slug Locale slug.
	 *      - 'default' for the default locales without variants.
	 *      - 'with_variants' for the default locales with variants.
	 *
	 * @return array Number of locales
	 */
	private function get_existing_locales( string $locale_slug = 'default' ): array {
		global $wpdb;
		$query = '';
		if ( 'with_variants' == $locale_slug ) {
			$query = $wpdb->prepare(
				"SELECT locale FROM {$wpdb->gp_translation_sets} WHERE `project_id` = %d",
				2 // 2 = wp/dev
			);
		} elseif ( 'default' == $locale_slug ) {
			$query = $wpdb->prepare(
				"SELECT locale FROM {$wpdb->gp_translation_sets} WHERE `project_id` = %d and slug = %s",
				2, // 2 = wp/dev
				'default'
			);
		} else {
			return array();
		}

		return $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Returns the number of locales with WordPress core translation project generated.
	 *
	 * @return mixed
	 */
	private function get_core_total() {
		global $wpdb;

		return $wpdb->get_var(
			"SELECT count(*) as counter FROM (
				SELECT
					(100 * stats.current/stats.all) as percent_complete
					FROM {$wpdb->project_translation_status} stats
						LEFT JOIN {$wpdb->gp_projects} projects ON stats.project_id = projects.id
					WHERE
						projects.path = 'wp/dev'
						AND projects.active = 1
			) n"
		);
	}

	/**
	 * Returns the number of locales with WordPress full translated
	 *
	 * @return mixed
	 */
	private function get_core_full_translated() {
		global $wpdb;

		return $wpdb->get_var(
			"SELECT count(*) as counter FROM (
				SELECT
					(100 * stats.current/stats.all) as percent_complete
				FROM {$wpdb->project_translation_status} stats
					LEFT JOIN {$wpdb->gp_projects} projects ON stats.project_id = projects.id
				WHERE
					projects.path = 'wp/dev'
					AND projects.active = 1
				HAVING 
					percent_complete >= 100.00
			) n"
		);
	}

	/**
	 * Returns the number of locales with WordPress core translation between the two values.
	 *
	 * @param int    $upper_value
	 * @param int    $lower_value
	 * @param string $minor_symbol
	 * @param string $greater_symbol
	 *
	 * @return mixed
	 */
	private function get_core_interval( int $upper_value, int $lower_value, string $minor_symbol = '<', string $greater_symbol = '>=' ) {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT count(*) as counter FROM (
					SELECT
						(100 * stats.current/stats.all) as percent_complete
					FROM {$wpdb->project_translation_status} stats
						LEFT JOIN {$wpdb->gp_projects} projects ON stats.project_id = projects.id
					WHERE
						projects.path = 'wp/dev'
						AND projects.active = 1
					HAVING
						percent_complete %1\$s %2\$d
						AND percent_complete %3\$s %4\$d
				) n",
				$greater_symbol,
				$lower_value,
				$minor_symbol,
				$upper_value
			)
		);
	}

	/**
	 * Returns the number of locales with WordPress empty translated.
	 *
	 * @return mixed
	 */
	private function get_core_empty_translated() {
		global $wpdb;

		return $wpdb->get_var(
			"SELECT count(*) as counter FROM (
				SELECT
					(100 * stats.current/stats.all) as percent_complete
				FROM {$wpdb->project_translation_status} stats
					LEFT JOIN {$wpdb->gp_projects} projects ON stats.project_id = projects.id
				WHERE
					projects.path = 'wp/dev'
					AND projects.active = 1
				HAVING 
					percent_complete <= 0.00
			) n"
		);
	}

	/**
	 * Returns the first user registered in the current year.
	 *
	 * @return int
	 */
	private function get_id_first_user_of_this_year(): int {
		global $wpdb;

		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->users} WHERE user_registered >= %s LIMIT 1",
				gmdate( 'Y-m-d H:i:s', strtotime( 'first day of january this year' ) )
			)
		);
	}

	/**
	 * Gets the managers for the $role category for each locale.
	 *
	 * The role can be:
	 * - locale_manager.
	 * - general_translation_editor.
	 * - translation_editor.
	 *
	 * The $type parameter can be:
	 * - total: get all the contributors.
	 * - registered_this_year: get the users that have been registered this year and get the $role.
	 * - started_this_year: get the users that have start translating this year and get the $role.
	 *
	 * @param string $role Translator role.
	 * @param string $type Filter.
	 *
	 * @return array
	 */
	private function count_managers( string $role, string $type = 'total' ): array {
		global $wpdb;

		$locales  = get_locales();
		$managers = array();
		foreach ( $locales as $locale ) {
			$result  = get_sites(
				array(
					'locale'     => $locale->wp_locale,
					'network_id' => WPORG_GLOBAL_NETWORK_ID,
					'path'       => '/',
					'fields'     => 'ids',
					'number'     => '1',
				)
			);
			$site_id = array_shift( $result );
			if ( ! $site_id ) {
				continue;
			}
			$query = new \WP_User_Query();

			$query->prepare_query(
				array(
					'blog_id' => $site_id,
					'role'    => $role,
				)
			);

			if ( 'registered_this_year' === $type ) {
				// Get the users that have registered this year.
				$query->query_where .= ' AND user_id >= ' . $this->id_first_user_of_this_year;
			}

			if ( 'started_this_year' === $type ) {
				// Get the users that have start translating this year.
				$user_ids = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT `user_id`
						FROM `{$wpdb->user_translations_count}`
						WHERE `locale` = %s AND
							`accepted` > 0 AND
							`date_added` > %s",
						$locale->slug,
						gmdate( 'Y-01-01' )
					)
				);

				$query->query_where .= ' AND user_id IN (' . implode( ', ', array_map( 'intval', $user_ids ) ) . ')';
			}

			$query->query();
			$users = $query->get_results();

			$managers[ $locale->english_name ] = count( $users );
		}

		return $managers;
	}

	/**
	 * Gets the translation contributors for the given locale.
	 *
	 * @return array
	 */
	private function get_translation_contributors( GP_Locale $locale, $max_age_days = null ): array {
		global $wpdb;

		$contributors = array();

		$date_constraint = '';
		if ( null !== $max_age_days ) {
			$date_constraint = $wpdb->prepare( ' AND date_modified >= CURRENT_DATE - INTERVAL %d DAY', $max_age_days );
		}

		[ $locale, $locale_slug ] = array_merge( explode( '/', $locale->slug ), array( 'default' ) );

		$users = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT user_id
				FROM {$wpdb->user_translations_count}
				WHERE accepted > 0 AND
					locale = %s AND
					locale_slug = %s",
				$locale,
				$locale_slug
			) . $date_constraint
		);

		if ( ! $users ) {
			return $contributors;
		}

		$user_data = $wpdb->get_results( "SELECT user_nicename, display_name, user_email FROM $wpdb->users WHERE ID IN (" . implode( ',', $users ) . ')' );
		foreach ( $user_data as $user ) {
			if ( $user->display_name && $user->display_name !== $user->user_nicename ) {
				$contributors[ $user->user_nicename ] = array(
					'display_name' => $user->display_name,
					'nice_name'    => $user->user_nicename,
				);
			} else {
				$contributors[ $user->user_nicename ] = array(
					'display_name' => $user->user_nicename,
					'nice_name'    => $user->user_nicename,
				);
			}
		}

		uasort( $contributors, fn( $a, $b ) => strnatcasecmp( $a['display_name'], $b['display_name'] ) );

		return $contributors;
	}

	/**
	 * Type:
	 * - topic
	 * - reply
	 *
	 * @param  string   $type Type of query: topic or reply.
	 * @param  int|null $year Year fot the stats.
	 * @return array
	 */
	private function get_forums_stats( string $type, int $year = null ): array {
		global $wpdb;

		$date_constraint = '';
		if ( $year ) {
			$date_constraint = $wpdb->prepare( ' AND YEAR(post_date) = %d', $year );
		}

		foreach ( $this->forum_ids as $key => $value ) {
			global $wpdb;

			$posts[ $key ] = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(id) as posts FROM {$wpdb->base_prefix}{$value}_posts WHERE post_status = 'publish' AND post_type = %s",
					$type
				) . $date_constraint
			);
		}

		return $posts;
	}

	/**
	 * Update the page with the Polyglots stats.
	 *
	 * All the previous information is deleted.
	 * The page updated is https://make.wordpress.org/polyglots/stats.
	 *
	 * @return void
	 */
	private function update_page() {
		if ( $this->echo_the_values ) {
			return;
		}

		add_filter(
			'wp_revisions_to_keep',
			function ( $num, $post ) {
				if ( self::POLYGLOTS_PAGE_ID === $post->ID ) {
					$num = 0; // pretend we don't want to keep revisions so that it will not lookup all old revisions.
				}

				return $num;
			},
			10,
			2
		);
		switch_to_blog( self::MAKE_POLYGLOTS_BLOG_ID );

		wp_update_post(
			array(
				'ID'           => self::POLYGLOTS_PAGE_ID,
				'post_type'    => 'page',
				'post_author'  => 'Amieiro',
				'post_content' => $this->get_polyglots_stats_page_content(),
			),
			true
		);
		restore_current_blog();
	}

	/**
	 * Return the content for the web page, concatenating some strings.
	 *
	 * @return string
	 */
	private function get_polyglots_stats_page_content(): string {
		return $this->header .
			$this->stats_comparison .
			$this->wordpress_translation_percentage .
			$this->originals_by_translation_source .
			$this->originals_by_year .
			$this->packages_generated_by_year .
			$this->themes_plugins_by_year .
			$this->translations_translators_by_year .
			$this->forum_post_and_replies_by_year .
			$this->feedback_received .
			$this->contributors_per_locale .
			$this->managers_stats .
			$this->most_active_translators;
	}

	/**
	 * Create a Gutenberg heading.
	 *
	 * @param string $text_to_insert
	 * @param string $header_type
	 *
	 * @return string
	 */
	private function create_gutenberg_heading( string $text_to_insert, string $header_type = 'h1' ): string {
		$heading  = '<!-- wp:heading -->';
		$heading .= '<' . $header_type . '>' . $text_to_insert . '</' . $header_type . '>';
		$heading .= '<!-- /wp:heading -->';

		return $heading;
	}

	/**
	 * Print a WP-CLI heading.
	 *
	 * @param string $text_to_print
	 *
	 * @return void
	 */
	private function print_wpcli_heading( string $text_to_print ): void {
		WP_CLI::log( '' );
		WP_CLI::log( '----------------------------------------------------------------' );
		WP_CLI::log( $text_to_print );
		WP_CLI::log( '----------------------------------------------------------------' );
	}

	/**
	 *  Create a Gutenberg paragraph.
	 *
	 * @param string $text_to_insert
	 *
	 * @return string
	 */
	private function create_gutenberg_paragraph( string $text_to_insert ): string {
		$paragraph  = '<!-- wp:paragraph -->';
		$paragraph .= '<p>' . $text_to_insert . '<p>';
		$paragraph .= '<!-- /wp:paragraph -->';

		return $paragraph;
	}

	/**
	 * Create a Gutenberg paragraph.
	 *
	 * @param string $text_to_insert
	 *
	 * @return string
	 */
	private function create_gutenberg_code( string $text_to_insert ): string {
		$code  = '<!-- wp:code -->';
		$code .= '<pre class="wp-block-code"><code>';
		$code .= '<p>' . $text_to_insert . '<p>';
		$code .= '</code></pre>';
		$code .= '<!-- /wp:code -->';

		return $code;
	}
}

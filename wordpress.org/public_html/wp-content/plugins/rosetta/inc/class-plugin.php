<?php
/**
 * Main plugin class for Rosetta.
 *
 * @package WordPressdotorg\Rosetta
 */

namespace WordPressdotorg\Rosetta;

use WP_Site;

/**
 * Class Plugin.
 *
 * Handles plugin initialization, site routing, navigation menu customizations,
 * and default date/time option filters.
 */
class Plugin {

	/**
	 * The singleton instance.
	 *
	 * @var \WordPressdotorg\Rosetta\Plugin|null
	 */
	private static $instance = null;

	/**
	 * Array of site classes to test against the current site.
	 *
	 * @var string[]
	 */
	private $sites = [];

	/**
	 * Returns the singleton instance of this plugin.
	 *
	 * @return \WordPressdotorg\Rosetta\Plugin The plugin instance.
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof self ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object and registers initial hooks.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ], 1 );
		add_filter( 'wp_nav_menu_objects', [ $this, 'download_button_menu_item' ], 11 );

		$this->sites = [
			Site\Global_WordPress_Org::class,
			Site\Translate_WordPress_Org::class,
			Site\Locale_Main::class,
			Site\Locale_Team::class,
			Site\Locale_Support::class,
		];
	}

	/**
	 * Initializes the plugin, determines the current site, and sets up filters.
	 *
	 * @return void
	 */
	public function plugins_loaded() {
		$current_site = get_site( get_current_blog_id() );

		// Determine and initialize site-specific customizations.
		foreach ( $this->sites as $site ) {
			if ( $site::test( $current_site ) ) {
				/** @var \WordPressdotorg\Rosetta\Site\Site $site_instance */
				$site_instance = new $site();
				$site_instance->register_events();
				break;
			}
		}

		// Register global filters for all sites.
		$this->filter_date_options();
	}

	/**
	 * Turns a menu item that links to the Downloads page into a download button.
	 *
	 * @param \WP_Post[] $menu_items Menu items sorted by each item's menu order.
	 * @return \WP_Post[] Filtered menu items.
	 */
	public function download_button_menu_item( $menu_items ) {
		foreach ( $menu_items as $menu_item ) {
			if (
				false !== stripos( $menu_item->url, 'download/' )
				|| (
					! empty( $menu_item->object_id )
					&& 'post_type' === $menu_item->type
					&& 'page-download.php' === get_page_template_slug( (int) $menu_item->object_id )
				)
			) {
				$menu_item->ID      = -1; // Prevents the title from being overwritten by the page title.
				$menu_item->classes = array_merge( (array) $menu_item->classes, [ 'button', 'button-primary', 'download' ] );
				$menu_item->title   = _x( 'Get WordPress', 'Menu title', 'rosetta' );
				break;
			}
		}

		return $menu_items;
	}

	/**
	 * Registers native WordPress filters for default date and time options.
	 *
	 * @return void
	 */
	private function filter_date_options() {
		add_filter( 'pre_option_timezone_string', [ $this, 'filter_timezone_string' ] );
		add_filter( 'pre_option_gmt_offset', [ $this, 'filter_gmt_offset' ], 11 );
		add_filter( 'pre_option_date_format', [ $this, 'filter_date_format' ] );
		add_filter( 'pre_option_time_format', [ $this, 'filter_time_format' ] );
		add_filter( 'pre_option_start_of_week', [ $this, 'filter_start_of_week' ] );
	}

	/**
	 * Filters the default timezone string.
	 *
	 * @return string Valid timezone identifier or empty string.
	 */
	public function filter_timezone_string() {
		/*
		 * translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
		 * or a valid timezone string (America/New_York). See https://www.php.net/manual/timezones.php
		 * for all timezone strings supported by PHP.
		 */
		$offset_or_tz = _x( '0', 'default GMT offset or timezone string', 'rosetta' );

		if ( '' !== $offset_or_tz && ! is_numeric( $offset_or_tz ) && in_array( $offset_or_tz, timezone_identifiers_list(), true ) ) {
			return $offset_or_tz;
		}

		return '';
	}

	/**
	 * Filters the default GMT offset.
	 *
	 * @return float|int Calculated offset in hours.
	 */
	public function filter_gmt_offset() {
		$timezone_string = get_option( 'timezone_string' );
		if ( $timezone_string ) {
			$timezone_object = timezone_open( $timezone_string );
			$datetime_object = date_create();

			if ( false !== $timezone_object && false !== $datetime_object ) {
				return round( timezone_offset_get( $timezone_object, $datetime_object ) / HOUR_IN_SECONDS, 2 );
			}
		}

		/*
		 * translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
		 * or a valid timezone string (America/New_York). See https://www.php.net/manual/timezones.php
		 * for all timezone strings supported by PHP.
		 */
		$offset_or_tz = _x( '0', 'default GMT offset or timezone string', 'rosetta' );

		if ( '' !== $offset_or_tz && is_numeric( $offset_or_tz ) ) {
			return (int) $offset_or_tz;
		}

		return 0;
	}

	/**
	 * Filters the default date format.
	 *
	 * @return string Localized date format.
	 */
	public function filter_date_format() {
		/* translators: default date format, see https://www.php.net/date */
		return __( 'F j, Y', 'rosetta' );
	}

	/**
	 * Filters the default time format.
	 *
	 * @return string Localized time format.
	 */
	public function filter_time_format() {
		/* translators: default time format, see https://www.php.net/date */
		return __( 'g:i a', 'rosetta' );
	}

	/**
	 * Filters the default start day of the week.
	 *
	 * @return string Default start of the week (0 = Sunday, 1 = Monday).
	 */
	public function filter_start_of_week() {
		/* translators: default start of the week. 0 = Sunday, 1 = Monday */
		return _x( '1', 'start of week', 'rosetta' );
	}
}

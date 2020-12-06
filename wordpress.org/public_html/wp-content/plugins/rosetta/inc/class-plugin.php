<?php

namespace WordPressdotorg\Rosetta;

class Plugin {

	/**
	 * @var \WordPressdotorg\Rosetta\Plugin The singleton instance.
	 */
	private static $instance;

	/**
	 * Array of sites.
	 *
	 * @var \WordPressdotorg\Rosetta\Site\Site[]
	 */
	private $sites = [];

	/**
	 * Returns always the same instance of this plugin.
	 *
	 * @return \WordPressdotorg\Rosetta\Plugin
	 */
	public static function get_instance() {
		if ( ! ( self::$instance instanceof Plugin ) ) {
			self::$instance = new Plugin();
		}
		return self::$instance;
	}

	/**
	 * Instantiates a new Plugin object.
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
	 * Initializes the plugin.
	 */
	public function plugins_loaded() {
		$current_site = get_site( get_current_blog_id() );

		// Site specific customizations.
		foreach ( $this->sites as $site ) {
			if ( $site::test( $current_site ) ) {
				/** @var \WordPressdotorg\Rosetta\Site\Site $site_instance */
				$site_instance = new $site();
				$site_instance->register_events();
				break;
			}
		}

		// Customizations for all sites.
		$this->filter_date_options();
	}

	/**
	 * Turns a menu item that links to the Downloads page into a download button.
	 *
	 * @param array $menu_items The menu items, sorted by each menu item's menu order.
	 * @return array
	 */
	public function download_button_menu_item( $menu_items ) {
		foreach ( $menu_items as $menu_item ) {
			if (
				false !== stripos( $menu_item->url, 'download/' ) ||
				(
					$menu_item->object_id &&
					'post_type' == $menu_item->type &&
					'page-download.php' === get_page_template_slug( $menu_item->object_id )
				)
			) {
				$menu_item->ID      = -1; // Prevents the title being overwritten by the page title.
				$menu_item->classes = array_merge( $menu_item->classes, ['button', 'button-primary', 'download'] );
				$menu_item->title   = _x( 'Get WordPress', 'Menu title', 'rosetta' );
				break;
			}
		}

		return $menu_items;
	}

	/**
	 * Adds filters for all date options.
	 */
	private function filter_date_options() {
		$options = new Filter\Options();

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'timezone_string' )
				->set_callback( function() {
					/* translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
					 * or a valid timezone string (America/New_York). See https://www.php.net/manual/timezones.php
					 * for all timezone strings supported by PHP.
					 */
					$offset_or_tz = _x( '0', 'default GMT offset or timezone string', 'rosetta' );
					if ( $offset_or_tz && ! is_numeric( $offset_or_tz ) && in_array( $offset_or_tz, timezone_identifiers_list(), true ) ) {
						return $offset_or_tz;
					} else {
						return '';
					}
				} )
		);

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'gmt_offset' )
				->set_callback( function() {
					// Do the smart timezone handling like `wp_timezone_override_offset()`.
					$timezone_string = get_option( 'timezone_string' );
					if ( $timezone_string ) {
						$timezone_object = timezone_open( $timezone_string );
						$datetime_object = date_create();
						if ( false !== $timezone_object && false !== $datetime_object ) {
							return round( timezone_offset_get( $timezone_object, $datetime_object ) / HOUR_IN_SECONDS, 2 );
						}
					}

					/* translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
					 * or a valid timezone string (America/New_York). See https://www.php.net/manual/timezones.php
					 * for all timezone strings supported by PHP.
					 */
					$offset_or_tz = _x( '0', 'default GMT offset or timezone string', 'rosetta' );
					if ( $offset_or_tz && is_numeric( $offset_or_tz ) ) {
						return (int) $offset_or_tz;
					} else {
						return 0;
					}
				} )
				->set_priority( 11 ) // After `wp_timezone_override_offset()`.
		);

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'date_format' )
				->set_callback( function() {
					/* translators: default date format, see https://www.php.net/date */
					return __( 'F j, Y', 'rosetta' );
				} )
		);

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'time_format' )
				->set_callback( function() {
					/* translators: default time format, see https://www.php.net/date */
					return __( 'g:i a', 'rosetta' );
				} )
		);

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'start_of_week' )
				->set_callback( function() {
					/* translators: default start of the week. 0 = Sunday, 1 = Monday */
					return _x( '1', 'start of week', 'rosetta' );
				} )
		);

		$options->setup();
	}
}

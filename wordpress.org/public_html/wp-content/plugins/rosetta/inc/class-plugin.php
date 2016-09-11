<?php

namespace WordPressdotorg\Rosetta;

class Plugin {

	/**
	 * @var Plugin The singleton instance.
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
	 * @return Plugin
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
		add_action( 'plugins_loaded', [ $this, 'plugins_loaded' ] );

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
	 * Adds filters for all date options.
	 */
	private function filter_date_options() {
		$options = new Filter\Options();

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'timezone_string' )
				->set_callback( function() {
					/* translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
					 * or a valid timezone string (America/New_York). See https://secure.php.net/manual/timezones.php
					 * for all timezone strings supported by PHP.
					 */
					$offset_or_tz = _x( '0', 'default GMT offset or timezone string', 'rosetta' );
					if ( $offset_or_tz && ! is_numeric( $offset_or_tz ) && in_array( $offset_or_tz, timezone_identifiers_list() ) ) {
						return $offset_or_tz;
					} else {
						return '';
					}
				} )
				->set_priority( 9 ) // Before `wp_timezone_override_offset()`
		);

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'gmt_offset' )
				->set_callback( function() {
					/* translators: default GMT offset or timezone string. Must be either a valid offset (-12 to 14)
					 * or a valid timezone string (America/New_York). See https://secure.php.net/manual/timezones.php
					 * for all timezone strings supported by PHP.
					 */
					$offset_or_tz = _x( '0', 'default GMT offset or timezone string', 'rosetta' );
					if ( $offset_or_tz && is_numeric( $offset_or_tz ) ) {
						return $offset_or_tz;
					} else {
						return 0;
					}
				} )
				->set_priority( 9 ) // Before `wp_timezone_override_offset()`
		);

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'date_format' )
				->set_callback( function() {
					/* translators: default date format, see https://secure.php.net/date */
					return __( 'F j, Y', 'rosetta' );
				} )
		);

		$options->add_option(
			( new Filter\Option() )
				->set_name( 'time_format' )
				->set_callback( function() {
					/* translators: default time format, see https://secure.php.net/date */
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

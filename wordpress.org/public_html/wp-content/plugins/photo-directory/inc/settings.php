<?php
/**
 * Admin customizations.
 *
 * @package WordPressdotorg\Photo_Directory
 */

namespace WordPressdotorg\Photo_Directory;

class Settings {

	/**
	 * Setting name for killswitch.
	 *
	 * @var string
	 */
	const KILLSWITCH_OPTION_NAME = 'photo_killswitch';

	public static function init() {
		if ( ! is_admin() ) {
			return;
		}

		add_action( 'admin_init', [ __CLASS__, 'initialize_settings' ] );
	}

	/**
	 * Initializes settings.
	 */
	public static function initialize_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		register_setting( 'media', self::KILLSWITCH_OPTION_NAME );

		add_filter( 'allowed_options', [ __CLASS__, 'allowed_options' ] );

		add_settings_field(
			self::KILLSWITCH_OPTION_NAME,
			__( 'Prevent all photo uploads?', 'wporg-photos' ),
			[ __CLASS__, 'display_option' ],
			'media',
			'uploads'
		);
	}

	/**
	 * Allows the plugin's options.
	 *
	 * @param array $options Array of allowed options.
	 * @return array The amended allowed options array.
	 */
	public static function allowed_options( $options ) {
		$added = [
			self::KILLSWITCH_OPTION_NAME => [ self::KILLSWITCH_OPTION_NAME ]
		];

		return add_allowed_options( $added, $options );
	}

	/**
	 * Outputs markup for the plugin settings.
	 *
	 * @param array $args Arguments.
	 */
	public static function display_option( $args = array() ) {
		printf(
			'<fieldset id="%s"><label for="%s"><input type="checkbox" id="%s" name="%s" value="1"%s /> %s</label></fieldset>' . "\n",
			esc_attr( self::KILLSWITCH_OPTION_NAME ),
			esc_attr( self::KILLSWITCH_OPTION_NAME ),
			esc_attr( self::KILLSWITCH_OPTION_NAME ),
			esc_attr( self::KILLSWITCH_OPTION_NAME ),
			checked( true, self::is_killswitch_enabled(), false ),
			__( 'Prevents all users from being able to upload any photos', 'wporg-photos' )
		);
	}

	/**
	 * Determines if the killswitch setting has been enabled to disable
	 * all uploading functionality.
	 *
	 * @return bool True if killswitch is enabled (and uploading should be
	 *              disabled), else false.
	 */
	public static function is_killswitch_enabled() {
		return (bool) get_option( self::KILLSWITCH_OPTION_NAME );
	}

}

add_action( 'plugins_loaded', [ __NAMESPACE__ . '\Settings', 'init' ] );

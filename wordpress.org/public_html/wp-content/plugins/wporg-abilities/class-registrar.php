<?php
/**
 * Registrar for WordPress.org abilities.
 *
 * Handles registration of ability categories and individual abilities.
 *
 * @package WordPressdotorg\Abilities
 */

namespace WordPressdotorg\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registrar class.
 */
class Registrar {

	/**
	 * Initialize abilities registration.
	 */
	public static function init(): void {
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
	}

	/**
	 * Register ability categories.
	 */
	public static function register_categories(): void {
	}

	/**
	 * Register abilities.
	 */
	public static function register_abilities(): void {
	}
}

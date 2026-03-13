<?php
/**
 * Registrar for WordPress.org abilities.
 *
 * Handles registration of ability categories and individual abilities.
 *
 * @package WordPressdotorg\Abilities
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory;

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
		wp_register_ability_category(
			'wporg-plugins-plugin-directory',
			array(
				'label'       => 'Plugin Directory',
				'description' => 'Tools, resources, and prompts for the WordPress.org plugin directory.',
			)
		);
	}

	/**
	 * Register abilities.
	 */
	public static function register_abilities(): void {
		// Resources.
		Plugin_Directory\Resources\Plugin_Guidelines::register();
		Plugin_Directory\Resources\Readme_Standard::register();
		Plugin_Directory\Resources\Plugin_Headers::register();
		Plugin_Directory\Resources\Reserved_Slugs::register();
		Plugin_Directory\Resources\Plugin_Check_Guide::register();
		Plugin_Directory\Resources\Plugin_FAQ::register();

		// Tools.
		Plugin_Directory\Tools\Validate_Readme::register();

		// Prompts.
		Plugin_Directory\Prompts\Prepare_Plugin::register();
		Plugin_Directory\Prompts\Run_Plugin_Check::register();
		Plugin_Directory\Prompts\Address_Review_Feedback::register();
	}
}

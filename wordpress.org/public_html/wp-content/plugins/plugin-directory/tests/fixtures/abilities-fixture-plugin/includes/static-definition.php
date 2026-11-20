<?php
/**
 * Pattern: one class per ability, with static get_name() and get_registration_args().
 *
 * Seen in: WooCommerce (src/Internal/Abilities/Domain/*.php, registered by
 * AbilitiesLoader). The name is a literal returned from get_name(); the label,
 * description and category are in the array returned by get_registration_args().
 * Some WooCommerce classes give the name as an ABILITY_NAME class constant instead.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\Domain;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Contract for an ability definition.
 */
interface Ability_Definition {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string;

	/**
	 * Returns the ability registration arguments.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array;
}

/**
 * Queries products.
 */
class Products_Query implements Ability_Definition {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'abilities-fixture/products-query';
	}

	/**
	 * Returns the ability registration arguments.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Query products', 'abilities-fixture' ),
			'description'         => __(
				'Finds products by ID or common catalog filters.',
				'abilities-fixture'
			),
			'category'            => 'abilities-fixture',
			'input_schema'        => array( 'type' => 'object' ),
			'execute_callback'    => '__return_empty_array',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

/**
 * Updates a product, naming itself with a class constant.
 */
class Product_Update implements Ability_Definition {

	const ABILITY_NAME = 'abilities-fixture/product-update';

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return self::ABILITY_NAME;
	}

	/**
	 * Returns the ability registration arguments.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Update a product', 'abilities-fixture' ),
			'description'         => __( 'Updates the title, price or stock of a product.', 'abilities-fixture' ),
			'category'            => 'abilities-fixture',
			'execute_callback'    => '__return_true',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

add_action(
	'wp_abilities_api_init',
	function () {
		foreach ( array( Products_Query::class, Product_Update::class ) as $class_name ) {
			$ability_name = $class_name::get_name();
			if ( '' === $ability_name || wp_has_ability( $ability_name ) ) {
				continue;
			}
			wp_register_ability( $ability_name, $class_name::get_registration_args() );
		}
	}
);

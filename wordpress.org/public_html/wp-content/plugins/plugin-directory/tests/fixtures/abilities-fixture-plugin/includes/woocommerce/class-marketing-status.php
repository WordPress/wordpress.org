<?php
/**
 * An ability WooCommerce registers on the plugin's behalf. Loaded only from
 * WooCommerce's woocommerce_ability_definition_classes filter.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\WooCommerce;

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\Abilities\AbilityDefinition;

/**
 * Reports marketing status, in WooCommerce's category.
 */
class Marketing_Status implements AbilityDefinition {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public static function get_name(): string {
		return 'abilities-fixture/woocommerce-marketing-status';
	}

	/**
	 * Returns the ability registration arguments.
	 *
	 * @return array
	 */
	public static function get_registration_args(): array {
		return array(
			'label'               => __( 'Get marketing status', 'abilities-fixture' ),
			'description'         => __( 'Reports which store marketing emails are switched on.', 'abilities-fixture' ),
			'category'            => 'woocommerce',
			'execute_callback'    => '__return_empty_array',
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

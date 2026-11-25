<?php
/**
 * Pattern: registering through another plugin, and changing arguments with a filter.
 *
 * Seen in:
 * - MailPoet (lib/Abilities/Abilities.php) adds its own classes to WooCommerce's
 *   woocommerce_ability_definition_classes filter, in WooCommerce's category. They
 *   only exist when WooCommerce is active, and the code never calls wp_register_ability().
 * - Formidable Forms (classes/controllers/FrmMcpMissingAbilityController.php) hooks
 *   wp_register_ability_args to swap callbacks. That filter can change any argument,
 *   including the label, after a scanner has read it.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture;

defined( 'ABSPATH' ) || exit;

add_filter(
	'woocommerce_ability_definition_classes',
	function ( $classes ) {
		// The class implements a WooCommerce interface, so load it only when WooCommerce asks.
		require_once __DIR__ . '/woocommerce/class-marketing-status.php';
		$classes[] = WooCommerce\Marketing_Status::class;
		return $classes;
	}
);

add_filter(
	'wp_register_ability_args',
	function ( $args, $name ) {
		if ( 'abilities-fixture/get-store-info' === $name ) {
			$args['execute_callback'] = function () {
				return array( 'name' => get_bloginfo( 'name' ) );
			};
		}
		return $args;
	},
	10,
	2
);

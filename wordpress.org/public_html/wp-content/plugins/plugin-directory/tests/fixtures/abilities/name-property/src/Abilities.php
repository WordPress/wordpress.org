<?php
namespace NameProp;

final class Abilities {
	private const DEFINITIONS = [
		[
			'name'        => 'name-prop/list-orders',
			'label'       => 'List orders',
			'description' => 'Lists recent orders.',
			'category'    => 'commerce',
		],
		[
			'name'        => 'name-prop/refund-order',
			'label'       => 'Refund order',
			'description' => 'Refunds an order.',
		],
	];

	public static function register(): void {
		foreach ( self::DEFINITIONS as $definition ) {
			$name = $definition['name'];
			unset( $definition['name'] );
			\wp_register_ability( $name, $definition );
		}
	}
}

<?php
/**
 * Pattern: one class per ability, with a getter method for each value.
 *
 * Seen in: SureCart (app/src/Abilities/), Akismet (abilities/) and Easy Digital
 * Downloads (src/Abilities/). Each value comes from its own method: get_name(),
 * get_label(), get_description(). The category is set by a parent class, and the
 * call to wp_register_ability() is in the parent too, sometimes in its constructor.
 * Easy Digital Downloads builds the name from a namespace constant and get_slug().
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\Getters;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Holds the category slug, as a constant on a different class from the abilities.
 */
class Loader {

	const CATEGORY = 'abilities-fixture-commerce';

	/**
	 * Hooks the registrations.
	 */
	public static function init() {
		add_action( 'wp_abilities_api_categories_init', array( self::class, 'register_category' ) );

		// Akismet registers straight away if the hook has already run.
		if ( did_action( 'wp_abilities_api_init' ) ) {
			self::register_abilities();
		} else {
			add_action( 'wp_abilities_api_init', array( self::class, 'register_abilities' ) );
		}
	}

	/**
	 * Registers the category.
	 */
	public static function register_category() {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Abilities Fixture Commerce', 'abilities-fixture' ),
				'description' => __( 'Abilities for orders and store information.', 'abilities-fixture' ),
			)
		);
	}

	/**
	 * Instantiating each class registers its ability.
	 */
	public static function register_abilities() {
		foreach ( array( Get_Order::class, Get_Store_Info::class, Order_Read::class ) as $ability_class ) {
			new $ability_class();
		}
	}
}

/**
 * Registers itself from the constructor (SureCart, Akismet).
 */
abstract class Abstract_Ability {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	abstract public function get_name(): string;

	/**
	 * Returns the ability label.
	 *
	 * @return string
	 */
	abstract public function get_label(): string;

	/**
	 * Returns the ability description.
	 *
	 * @return string
	 */
	abstract public function get_description(): string;

	/**
	 * Registers the ability.
	 */
	public function __construct() {
		wp_register_ability( $this->get_name(), $this->get_config() );
	}

	/**
	 * Returns the registration arguments, with the category set here rather than by each ability.
	 *
	 * @return array
	 */
	protected function get_config(): array {
		return array(
			'label'               => $this->get_label(),
			'description'         => $this->get_description(),
			'category'            => Loader::CATEGORY,
			'execute_callback'    => '__return_empty_array',
			'permission_callback' => 'Abilities_Fixture\can_manage',
			'meta'                => array(
				'annotations' => array( 'readonly' => true ),
				// SureCart sets both, with different meanings.
				'public'      => true,
				'mcp'         => array( 'public' => true ),
			),
		);
	}
}

/**
 * Gets an order.
 */
class Get_Order extends Abstract_Ability {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'abilities-fixture/get-order';
	}

	/**
	 * Returns the ability label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Get order', 'abilities-fixture' );
	}

	/**
	 * Returns the ability description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Returns one order by ID.', 'abilities-fixture' );
	}
}

/**
 * Gets store information, with an untranslated label like Akismet's.
 */
class Get_Store_Info extends Abstract_Ability {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'abilities-fixture/get-store-info';
	}

	/**
	 * Returns the ability label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return 'Get store info';
	}

	/**
	 * Returns the ability description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Returns the store name, currency and address.', 'abilities-fixture' );
	}
}

/**
 * Builds its name from a namespace constant and a slug (Easy Digital Downloads).
 */
abstract class Namespaced_Ability extends Abstract_Ability {

	const ABILITY_NAMESPACE = 'abilities-fixture';

	/**
	 * Returns the part of the name after the slash.
	 *
	 * @return string
	 */
	abstract protected function get_slug(): string;

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	public function get_name(): string {
		return self::ABILITY_NAMESPACE . '/' . $this->get_slug();
	}
}

/**
 * Reads an order's line items.
 */
class Order_Read extends Namespaced_Ability {

	/**
	 * Returns the part of the name after the slash.
	 *
	 * @return string
	 */
	protected function get_slug(): string {
		return 'order-read';
	}

	/**
	 * Returns the ability label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return __( 'Read order items', 'abilities-fixture' );
	}

	/**
	 * Returns the ability description.
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Returns the line items of one order.', 'abilities-fixture' );
	}
}

Loader::init();

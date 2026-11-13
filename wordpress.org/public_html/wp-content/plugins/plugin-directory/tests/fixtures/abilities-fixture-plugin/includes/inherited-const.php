<?php
/**
 * Pattern: a literal call whose name and category come from inherited class constants.
 *
 * Seen in: WPForms Lite (src/Integrations/Abilities/Abilities.php). The call is written
 * out in full, but the name is self::ABILITY_NAMESPACE . '/list-forms' and the category
 * is self::CATEGORY_SLUG. The class that runs is a subclass in another file, which
 * inherits the constants.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\Forms;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Shared registration for the free and paid editions.
 */
abstract class Abilities {

	protected const ABILITY_NAMESPACE = 'abilities-fixture-forms';

	protected const CATEGORY_SLUG = 'abilities-fixture-forms';

	/**
	 * Hooks the registrations.
	 */
	public function hooks() {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	/**
	 * Registers the category.
	 */
	public function register_category() {
		wp_register_ability_category(
			self::CATEGORY_SLUG,
			array(
				'label'       => __( 'Abilities Fixture Forms', 'abilities-fixture' ),
				'description' => __( 'Abilities for forms and entries.', 'abilities-fixture' ),
			)
		);
	}

	/**
	 * Registers the abilities.
	 */
	public function register_abilities() {
		wp_register_ability(
			self::ABILITY_NAMESPACE . '/list-forms',
			array(
				'label'               => __( 'List forms', 'abilities-fixture' ),
				'description'         => __( 'Lists every form with its entry count.', 'abilities-fixture' ),
				'category'            => self::CATEGORY_SLUG,
				'execute_callback'    => '__return_empty_array',
				'permission_callback' => 'Abilities_Fixture\can_manage',
			)
		);
	}
}

/**
 * The free edition, which inherits everything.
 */
class Lite_Abilities extends Abilities {}

( new Lite_Abilities() )->hooks();

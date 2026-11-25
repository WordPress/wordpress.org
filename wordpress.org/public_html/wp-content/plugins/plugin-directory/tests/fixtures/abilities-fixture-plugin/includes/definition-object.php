<?php
/**
 * Pattern: a value object built with positional constructor arguments.
 *
 * Seen in: Elementor (modules/mcp/abilities/*-ability.php). get_ability_id() returns
 * the name; get_definition() returns new Ability_Definition( label, description,
 * category, schema ). The array that reaches wp_register_ability() is built by
 * to_array(), so no 'label' => … pair appears anywhere in the ability's file.
 *
 * @package abilities-fixture
 */

namespace Abilities_Fixture\Builder;

defined( 'ABSPATH' ) || exit;

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Each file reproduces one plugin's layout, which keeps related classes together.

/**
 * Holds an ability's registration arguments.
 */
class Ability_Definition {

	/**
	 * Stores the values.
	 *
	 * @param string $label        Label.
	 * @param string $description  Description.
	 * @param string $category     Category slug.
	 * @param array  $input_schema Input schema.
	 */
	public function __construct(
		public string $label,
		public string $description,
		public string $category,
		public array $input_schema = array()
	) {}

	/**
	 * Returns the registration arguments.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'label'               => $this->label,
			'description'         => $this->description,
			'category'            => $this->category,
			'input_schema'        => $this->input_schema,
			'permission_callback' => 'Abilities_Fixture\can_manage',
		);
	}
}

/**
 * Shared registration.
 */
abstract class Abstract_Ability {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	abstract protected function get_ability_id(): string;

	/**
	 * Returns the ability definition.
	 *
	 * @return Ability_Definition
	 */
	abstract protected function get_definition(): Ability_Definition;

	/**
	 * Runs the ability.
	 *
	 * @param array $input Ability input.
	 * @return mixed
	 */
	abstract public function execute( $input = array() );

	/**
	 * Registers the ability.
	 */
	public function register(): void {
		$definition                     = $this->get_definition()->to_array();
		$definition['execute_callback'] = array( $this, 'execute' );
		$definition['meta']             = array( 'show_in_rest' => true );
		wp_register_ability( $this->get_ability_id(), $definition );
	}
}

/**
 * Creates a draft page.
 */
class Create_Page_Ability extends Abstract_Ability {

	/**
	 * Returns the ability name.
	 *
	 * @return string
	 */
	protected function get_ability_id(): string {
		return 'abilities-fixture/create-page';
	}

	/**
	 * Returns the ability definition.
	 *
	 * @return Ability_Definition
	 */
	protected function get_definition(): Ability_Definition {
		return new Ability_Definition(
			__( 'Create page', 'abilities-fixture' ),
			__( 'Creates a new draft page and returns its ID and edit link.', 'abilities-fixture' ),
			'abilities-fixture',
			array(
				'type'       => 'object',
				'properties' => array( 'title' => array( 'type' => 'string' ) ),
			)
		);
	}

	/**
	 * Runs the ability.
	 *
	 * @param array $input Ability input.
	 * @return int|\WP_Error
	 */
	public function execute( $input = array() ) {
		return wp_insert_post(
			array(
				'post_type'  => 'page',
				'post_title' => $input['title'] ?? '',
			),
			true
		);
	}
}

add_action(
	'wp_abilities_api_init',
	function () {
		( new Create_Page_Ability() )->register();
	}
);

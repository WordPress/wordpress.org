<?php
/**
 * Plugin Name: Lazy Definition
 */
namespace Lazy;

// Modelled on Elementor's MCP abilities: the definition object is built once and memoised.
final class Definition {
	public string $label;
	public string $description;

	public function __construct( string $label, string $description ) {
		$this->label       = $label;
		$this->description = $description;
	}

	public function to_array(): array {
		return [ 'label' => $this->label, 'description' => $this->description ];
	}
}

abstract class Ability {
	private ?Definition $cached = null;

	abstract protected function get_ability_id(): string;
	abstract protected function get_definition(): Definition;

	protected function definition(): Definition {
		if ( null === $this->cached ) {
			$this->cached = $this->get_definition();
		}
		return $this->cached;
	}

	public function register(): void {
		$args                     = $this->definition()->to_array();
		$args['execute_callback'] = [ $this, 'execute' ];
		wp_register_ability( $this->get_ability_id(), $args );
	}
}

final class List_Posts extends Ability {
	protected function get_ability_id(): string {
		return 'lazy/list-posts';
	}
	protected function get_definition(): Definition {
		return new Definition( __( 'List posts', 'lazy' ), __( 'Lists recent posts.', 'lazy' ) );
	}
}

final class Read_Prompt extends Ability {
	protected function get_ability_id(): string {
		return 'lazy/read-prompt';
	}
	protected function get_definition(): Definition {
		// Loaded from a file at runtime: unknown to a scanner.
		return new Definition( __( 'Read prompt', 'lazy' ), Prompt_Loader::load( 'read-prompt' ) );
	}
}

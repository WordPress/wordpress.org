<?php
namespace Acme\Core;

abstract class Registrar {
	abstract protected function abilities(): array;

	public function register(): void {
		foreach ( $this->abilities() as $name => $args ) {
			wp_register_ability( $name, $args );
		}
	}
}

<?php
/**
 * Plugin Name: Bundled Registrar
 */

// Modelled on Jetpack: the plugin's own ability goes through a bundled base class,
// and a second bundled package registers through the same base class.
require __DIR__ . '/jetpack_vendor/autoload.php';

final class Plugin_Abilities extends \Acme\Core\Registrar {
	protected function abilities(): array {
		return array( 'bundled-registrar/own' => array( 'label' => 'Own', 'description' => "The plugin's own." ) );
	}
}

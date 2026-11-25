<?php
namespace Acme\Forms;

// This package never mentions registration itself.
final class Forms_Abilities extends \Acme\Core\Registrar {
	protected function abilities(): array {
		return array( 'acme-forms/list-forms' => array( 'label' => 'List forms', 'description' => 'Lists forms.' ) );
	}
}

<?php
// Rows can also be added one key at a time.
add_filter( 'regf_abilities_registry', function ( array $registry ): array {
	$registry['regf/get-user']    = array(
		'label'        => __( 'Get user', 'regf' ),
		'description'  => __( 'Read one user.', 'regf' ),
		'args_builder' => 'regf_args_get_user',
	);
	$registry['regf/delete-user'] = [
		'label'        => __( 'Delete user', 'regf' ),
		'description'  => __( 'Delete a user.', 'regf' ),
		'args_builder' => 'regf_args_delete_user',
	];
	return $registry;
} );

// Audit templates keyed by ability name are not definitions: no label or description.
function regf_audit_templates(): array {
	return array(
		'regf/get-user' => array( 'template' => __( 'Read user #%s', 'regf' ) ),
	);
}

<?php
// Integration files contribute rows through a filter, with plugin-specific keys alongside label and description.
add_filter( 'regf_abilities_registry', 'regf_register_post_definitions' );

function regf_register_post_definitions( array $registry ): array {
	return array_merge( $registry, regf_post_definitions() );
}

function regf_post_definitions(): array {
	return array(
		'regf/get-posts'   => array(
			'label'        => __( 'Get posts', 'regf' ),
			'description'  => __( 'List posts.', 'regf' ),
			'group'        => 'reads',
			'args_builder' => 'regf_args_get_posts',
		),
		'regf/create-post' => array(
			'label'        => __( 'Create a post', 'regf' ),
			'description'  => __( "Create a post with a title and content. It's saved as a draft.", 'regf' ),
			'group'        => 'writes',
			'args_builder' => 'regf_args_create_post',
		),
	);
}

// The args builders repeat label/description through helpers; these aren't keyed by name, so they add nothing.
function regf_args_get_posts(): array {
	return array(
		'label'       => regf_ability_label( 'regf/get-posts' ),
		'description' => regf_ability_description( 'regf/get-posts' ),
		'category'    => 'regf-reads',
	);
}

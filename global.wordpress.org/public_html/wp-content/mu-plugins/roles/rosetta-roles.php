<?php

add_filter( 'gettext_with_context', 'ros_rename_user_roles', 10, 4 );
function ros_rename_user_roles( $translated, $text, $context, $domain ) {
	if ( $domain !== 'default' || $context !== 'User role' ) {
		return $translated;
	}
	if ( 'Validator' === $text ) {
		return __( 'Validator', 'rosetta' );
	}
	return $translated;
}

add_action( 'admin_menu', 'ros_remove_widgets_menu' );
function ros_remove_widgets_menu() {
	remove_submenu_page( 'themes.php', 'themes.php' );
	remove_submenu_page( 'themes.php', 'widgets.php' );
}

add_filter( 'editable_roles', 'ros_editable_roles' );
function ros_editable_roles( $roles ) {
	$subscriber = $roles['subscriber'];
	unset( $roles['subscriber'] );
	reset( $roles );
	$roles['subscriber'] = $subscriber;
	if ( ! is_super_admin() && ! is_main_site() ) {
		unset( $roles['administrator'] );
	}
	return $roles;
}

add_filter( 'admin_init', 'ros_role_modifications' );
function ros_role_modifications() {
	if ( ! get_role( 'validator' ) ) {
		add_role( 'validator', __( 'Validator', 'rosetta' ), array( 'read' => true, 'level_0' => true ) );
	}
	$editor_role = get_role( 'editor' );
	if ( $editor_role && ! $editor_role->has_cap( 'remove_users' ) ) {
		$editor_role->add_cap( 'edit_theme_options' );
		$editor_role->add_cap( 'list_users' );
		$editor_role->add_cap( 'promote_users' );
		$editor_role->add_cap( 'remove_users' );
	}
}

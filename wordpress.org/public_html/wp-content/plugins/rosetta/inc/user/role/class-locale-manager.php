<?php
namespace WordPressdotorg\Rosetta\User\Role;

class Locale_Manager implements Role {

	/**
	 * Retrieves the name of this role.
	 *
	 * @return string The name of this role.
	 */
	public static function get_name() {
		return 'locale_manager';
	}

	/**
	 * Retrieves the display name of this role.
	 *
	 * @param bool $translated Whether the name should be translated or not.
	 * @return string. The display name.
	 */
	public static function get_display_name( $translated = false ) {
		return $translated ? __( 'Locale Manager', 'rosetta' ) : 'Locale Manager';
	}

	/**
	 * Retrieves the capabilities of this role.
	 *
	 * @return array Array of capabilities.
	 */
	public static function get_capabilities() {
		return [];
	}

	/**
	 * Retrieves the dynamic capabilities for this role.
	 *
	 * @return array Array of dynamic capabilities.
	 */
	public static function get_dynamic_capabilities() {
		return [
			// Core.
			'read'                       => true,
			'moderate_comments'          => true,
			'manage_categories'          => true,
			'manage_links'               => true,
			'upload_files'               => true,
			'edit_pages'                 => true,
			'publish_pages'              => true,
			'delete_pages'               => true,
			'edit_private_pages'         => true,
			'delete_private_pages'       => true,
			'read_private_pages'         => true,
			'edit_published_pages'       => true,
			'delete_published_pages'     => true,
			'edit_others_pages'          => true,
			'delete_others_pages'        => true,
			'edit_posts'                 => true,
			'publish_posts'              => true,
			'delete_posts'               => true,
			'edit_private_posts'         => true,
			'delete_private_posts'       => true,
			'read_private_posts'         => true,
			'edit_published_posts'       => true,
			'delete_published_posts'     => true,
			'edit_others_posts'          => true,
			'delete_others_posts'        => true,
			'edit_theme_options'         => true,
			'list_users'                 => true,
			'promote_users'              => true,
			'remove_users'               => true,

			// Custom.
			'manage_translation_editors' => true,
		];
	}

	/**
	 * Whether this role is an additional role.
	 *
	 * @return bool True if role is additional, false if not.
	 */
	public static function is_additional_role() {
		return false;
	}

	/**
	 * Whether this role is an editable role.
	 *
	 * @see get_editable_roles()
	 *
	 * @return bool True if role is editable, false if not.
	 */
	public static function is_editable_role() {
		return true;
	}
}

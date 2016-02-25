<?php
namespace WordPressdotorg\Plugin_Directory;

/**
 * Various functions used by other processes, will make sense to move to specific classes.
 *
 * @package WordPressdotorg_Plugin_Directory
 */
class Tools {

	/**
	 * @param string $readme
	 * @return object
	 */
	static function get_readme_data( $readme ) {

		// Uses https://github.com/rmccue/WordPress-Readme-Parser (with modifications)
		include_once __DIR__ . '/readme-parser/markdown.php';
		include_once __DIR__ . '/readme-parser/compat.php';

		$data = (object) \WPorg_Readme::parse_readme( $readme );

		unset( $data->sections['screenshots'] ); // Useless.

		// Sanitize contributors.
		foreach ( $data->contributors as $i => $name ) {
			if ( get_user_by( 'login', $name ) ) {
				continue;
			} elseif ( false !== ( $user = get_user_by( 'slug', $name ) ) ) {
				$data->contributors[] = $user->user_login;
				unset( $data->contributors[ $i ] );
			} else {
				unset( $data->contributors[ $i ] );
			}
		}

		return $data;
	}

	/**
	 * Retrieve the average color of a specified image.
	 * This currently relies upon the Jetpack libraries.
	 *
	 * @param $file_location string URL or filepath of image
	 * @return string|bool Average color as a hex value, False on failure
	 */
	static function get_image_average_color( $file_location ) {
		if ( ! class_exists( 'Tonesque' ) && function_exists( 'jetpack_require_lib' ) ) {
			jetpack_require_lib( 'tonesque' );
		}
		if ( ! class_exists( 'Tonesque' ) ) {
			return false;
		}

		$tonesque = new \Tonesque( $file_location );
		return $tonesque->color();
	}

	/**
	 * Retrieve a list of users who have commit to a specific plugin.
	 *
	 * @param string $plugin_slug The plugin slug.
	 * @return array The list of user_login's which have commit.
	 */
	public static function get_plugin_committers( $plugin_slug ) {
		global $wpdb;

		return $wpdb->get_col( $wpdb->prepare( 'SELECT user FROM `' . PLUGINS_TABLE_PREFIX . 'svn_access' . '` WHERE path = %s', "/{$plugin_slug}" ) );
	}

	/**
	 * Grant a user RW access to a plugin.
	 *
	 * @param string         $plugin_slug The plugin slug.
	 * @param string|WP_User $user        The user to grant access to.
	 */
	public static function grant_plugin_committer( $plugin_slug, $user ) {
		global $wpdb;
		if ( ! $user instanceof \WP_User ) {
			$user = new \WP_User( $user );
		}

		if ( ! $user->exists() || ! Plugin_Directory::instance()->get_plugin_post( $plugin_slug ) ) {
			return false;
		}

		$existing_committers = wp_list_pluck( self::get_plugin_committers( $plugin_slug ), 'user_login' );
		if ( in_array( $user->user_login, $existing_committers, true ) ) {
			// User already has write access
			return true;
		}

		return (bool)$wpdb->insert(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array(
				'path'   => "/{$plugin_slug}",
				'user'   => $user->user_login,
				'access' => 'rw'
			)
		);
	}

	/**
	 * Revoke a users RW access to a plugin.
	 *
	 * @param string         $plugin_slug The plugin slug.
	 * @param string|WP_User $user        The user to revoke access of.
	 */
	public static function revoke_plugin_committer( $plugin_slug, $user ) {
		global $wpdb;
		if ( ! $user instanceof \WP_User ) {
			$user = new \WP_User( $user );
		}

		if ( ! $user->exists() || ! Plugin_Directory::instance()->get_plugin_post( $plugin_slug ) ) {
			return false;
		}

		return $wpdb->delete(
			PLUGINS_TABLE_PREFIX . 'svn_access',
			array(
				'path'   => "/{$plugin_slug}",
				'user'   => $user->user_login
			)
		);
	}


}

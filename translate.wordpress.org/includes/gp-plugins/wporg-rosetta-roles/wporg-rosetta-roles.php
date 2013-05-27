<?php
/**
 * Tie roles on Rosetta sites directly into translate.wordpress.org.
 *
 * Anyone with the role of Validator, Contributor, Author, or Editor
 * has the ability to validate strings for that language.
 *
 * Future improvements to this would make this more granular (i.e. per-project)
 * with a UI in Rosetta to control those permissions.
 *
 * @author Nacin
 */
class GP_WPorg_Rosetta_Roles extends GP_Plugin {
	var $id = 'wporg-rosetta-roles';

	function __construct() {
		parent::__construct();
		$this->add_filter( 'pre_can_user', array( 'args' => 2, 'priority' => 9 ) );
	}

	function pre_can_user( $verdict, $args ) {
		if ( ! class_exists( 'BP_Roles' ) )
			require_once( BACKPRESS_PATH . 'class.bp-roles.php' );
		if ( ! class_exists( 'BP_User' ) )
			require_once( BACKPRESS_PATH . 'class.bp-user.php' );
		$user = new BP_User( $args['user_id'] );

		// 78 = global.wordpress.org. Administrators on this site are considered global admins in GlotPress.
		if ( ! empty( $user->ros_78_capabilities ) && is_array( $user->ros_78_capabilities ) && ! empty( $user->ros_78_capabilities['administrator'] ) )
			return true;

		if ( $args['action'] !== 'approve' || ! in_array( $args['object_type'], array( 'project|locale|set-slug', 'translation-set' ) ) )
			return false;
 
		if ( ! $locale_slug = $this->get_locale_slug( $args['object_type'], $args['object_id'] ) )
			return false;

		if ( ! $maybe_cap_key = $this->get_cap_key( $locale_slug ) )
			return false;

		$user->cap_key = $maybe_cap_key;
		$user->caps = &$user->{$user->cap_key};
		if ( ! is_array( $user->caps ) )
			$user->caps = array();
		$user->get_role_caps();
		foreach ( array( 'administrator', 'editor', 'author', 'contributor', 'validator' ) as $role ) {
			if ( $user->has_cap( $role ) )
				return true;
		}
		return false;
	}

	function get_locale_slug( $object_type, $object_id ) {
		switch ( $object_type ) {
			case 'translation-set' :
				return GP::$translation_set->get( $object_id )->locale;
				break;
			case 'project|locale|set-slug' :
				list( , $locale ) = explode( '|', $object_id );
				return $locale;
				break;
		}
		return false;
	}

	function get_cap_key( $locale_slug ) {
		global $gpdb;
		static $ros_blogs, $ros_locale_assoc;

		$gp_locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $gp_locale || ! isset( $gp_locale->wp_locale ) )
			return false;

		$wp_locale = $gp_locale->wp_locale;

		if ( ! isset( $ros_blogs ) ) {
			$ros_locale_assoc = $gpdb->get_results( "SELECT locale, subdomain FROM locales", OBJECT_K );
			$ros_blogs = $gpdb->get_results( "SELECT domain, blog_id FROM ros_blogs", OBJECT_K );
		}

		if ( isset( $ros_locale_assoc[ $wp_locale ] ) )
			$subdomain = $ros_locale_assoc[ $wp_locale ]->subdomain;
		else
			return false;

		if ( isset( $ros_blogs[ "$subdomain.wordpress.org" ] ) )
			return 'ros_' . $ros_blogs[ "$subdomain.wordpress.org" ]->blog_id . '_capabilities';

		return false;
	}
}
GP::$plugins->wporg_rosetta_roles = new GP_WPorg_Rosetta_Roles;

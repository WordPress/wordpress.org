<?php

/**
 * Remove a few toolbar items we do not need
 *
 * @author johnjamesjacoby
 * @since 1.0.1
 */
function bporg_toolbar_tweaks() {

	// WordPress Menus
	remove_action( 'admin_bar_menu', 'wp_admin_bar_sidebar_toggle',     0     );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu',            10    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_site_menu',          30    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_edit_site_menu',     40    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_customize_menu',     40    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_updates_menu',       50    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu',      60    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_new_content_menu',   70    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_edit_menu',          80    );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_my_account_item',    9991  );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_recovery_mode_menu', 9992  );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_search_menu',        9999  );

	// BuddyPress Menus
	remove_action( 'bp_setup_admin_bar', 'bp_members_admin_bar_my_account_menu', 4 );

	// WordPress.org Profiles site specific removals
	if ( 'profiles.wordpress.org' === $_SERVER['HTTP_HOST'] ) {
		remove_action( 'admin_bar_menu', 'bp_groups_group_admin_menu',   99  );
		remove_action( 'admin_bar_menu', 'bp_admin_bar_my_account_root', 100 );
	}
}
add_action( 'add_admin_bar_menus', 'bporg_toolbar_tweaks', 11 );

/**
 * Remove the BuddyPress and bbPress about menus
 *
 * @author johnjamesjacoby
 * @param WP_Admin_Bar $wp_admin_bar WP_Admin_Bar instance, passed by reference.
 */
function bporg_remove_about_pages( $wp_admin_bar ) {

	// About page links
	$wp_admin_bar->remove_menu( 'bp-about'  );
	$wp_admin_bar->remove_menu( 'bbp-about' );
}
add_action( 'admin_bar_menu', 'bporg_remove_about_pages', 99 );

/**
 * Remove the BuddyPress XProfile
 * @author johnjamesjacoby
 * @since 1.1.0
 */
function bporg_remove_my_account_items( $wp_admin_bar ) {
	$wp_admin_bar->remove_menu( 'my-account-blogs' );
	$wp_admin_bar->remove_menu( 'my-account-xprofile-edit' );
	$wp_admin_bar->remove_menu( 'my-account-xprofile-change-avatar' );
}
add_action( 'admin_bar_menu', 'bporg_remove_my_account_items', 99 );

/**
 * Prevent the BuddyPress Component admin-bar nav items from being added.
 *
 * This function adds filters to return false inside of the
 * BP_Component::setup_admin_bar() methods.
 *
 * WordPress.org & BuddyPress.org implement their own user profile flow. The
 * relevant components all have their
 *
 * @author johnjamesjacoby
 * @since 1.1.0
 */
function bporg_remove_component_menu_items() {

	// BuddyPress
	$components = bp_core_get_active_components();

	// bbPress
	if ( ! array_search( 'forums', $components, true ) ) {
		array_push( $components, 'forums' );
	}

	// Filter admin-nav for all components
	foreach ( $components as $component ) {
		add_filter( 'bp_' . $component . '_admin_nav', '__return_false' );
	}
}
add_action( 'bp_init', 'bporg_remove_component_menu_items', 99 );

/**
 * Add a new main top-left menu with links for each project.
 *
 * @todo GlotPress/BackPress
 *
 * @author johnjamesjacoby
 * @since 1.0.1
 */
function bporg_new_admin_bar_wp_menu( $wp_admin_bar ) {
	$wp_admin_bar->add_menu( array(
		'id'    => 'wp-logo',
		'title' => '<span class="ab-icon"></span>',
		'href'  => 'https://wordpress.org',
		'meta'  => array(
			'title' => __( 'WordPress.org' ),
		),
	) );

	/** WordPress *************************************************************/

	// Add WordPress menu
	$wp_admin_bar->add_menu( array(
		'parent' => 'wp-logo',
		'id'     => 'wordpress',
		'title'  => __( 'WordPress.org' ),
		'href'   => 'https://wordpress.org',
	) );

	// Add "About WordPress" link
	$wp_admin_bar->add_menu( array(
		'parent' => 'wordpress',
		'id'     => 'wp-about',
		'title'  => __( 'About WordPress' ),
		'href'   => 'https://wordpress.org/about/',
	) );

	// Add codex link
	$wp_admin_bar->add_menu( array(
		'parent' => 'wordpress',
		'id'     => 'wp-documentation',
		'title'  => __( 'Documentation' ),
		'href'   => 'https://codex.wordpress.org/',
	) );

	// Add forums link
	$wp_admin_bar->add_menu( array(
		'parent' => 'wordpress',
		'id'     => 'wp-support-forums',
		'title'  => __( 'Support Forums' ),
		'href'   => 'https://wordpress.org/support/',
	) );

	// Add feedback link
	$wp_admin_bar->add_menu( array(
		'parent' => 'wordpress',
		'id'     => 'wp-feedback',
		'title'  => __( 'Feedback' ),
		'href'   => 'https://wordpress.org/support/forum/requests-and-feedback',
	) );

	/** WordPress Developer **/
	$wp_admin_bar->add_group( array(
		'parent' => 'wordpress',
		'id'     => 'wp-developer',
		'meta'   => array(
			'class' => 'ab-sub-secondary',
		),
	) );

	$wp_admin_bar->add_menu( array(
		'parent' => 'wp-developer',
		'id'     => 'wp-trac',
		'title'  => __( 'Developer Trac' ),
		'href'   => 'https://core.trac.wordpress.org'
	) );

	$wp_admin_bar->add_menu( array(
		'parent' => 'wp-developer',
		'id'     => 'wp-dev-blog',
		'title'  => __( 'Developer Blog' ),
		'href'   => 'https://make.wordpress.org'
	) );

	/** bbPress ***************************************************************/

	// Add bbPress menu
	$wp_admin_bar->add_menu( array(
		'parent' => 'wp-logo',
		'id'     => 'bbpress',
		'title'  => __( 'bbPress.org' ),
		'href'   => 'https://bbpress.org',
	) );

	// Add "About bbPress" link
	$wp_admin_bar->add_menu( array(
		'parent' => 'bbpress',
		'id'     => 'bbp-about-alt',
		'title'  => __( 'About bbPress' ),
		'href'   => 'https://bbpress.org/about/',
	) );

	// Add codex link
	$wp_admin_bar->add_menu( array(
		'parent' => 'bbpress',
		'id'     => 'bbp-documentation',
		'title'  => __( 'Documentation' ),
		'href'   => 'https://codex.bbpress.org/',
	) );

	// Add forums link
	$wp_admin_bar->add_menu( array(
		'parent' => 'bbpress',
		'id'     => 'bbp-support-forums',
		'title'  => __( 'Support Forums' ),
		'href'   => __( 'https://bbpress.org/forums/' ),
	) );

	// Add feedback link
	$wp_admin_bar->add_menu( array(
		'parent' => 'bbpress',
		'id'     => 'bbp-feedback',
		'title'  => __( 'Feedback' ),
		'href'   => 'https://bbpress.org/forums/forum/requests-and-feedback',
	) );

	/** bbPress Developer **/
	$wp_admin_bar->add_group( array(
		'parent' => 'bbpress',
		'id'     => 'bbp-developer',
		'meta'   => array(
			'class' => 'ab-sub-secondary',
		),
	) );

	$wp_admin_bar->add_menu( array(
		'parent' => 'bbp-developer',
		'id'     => 'bbp-trac',
		'title'  => __( 'Developer Trac' ),
		'href'   => 'https://bbpress.trac.wordpress.org'
	) );

	$wp_admin_bar->add_menu( array(
		'parent' => 'bbp-developer',
		'id'     => 'bbp-dev-blog',
		'title'  => __( 'Developer Blog' ),
		'href'   => 'https://bbpdevel.wordpress.com'
	) );

	/** BuddyPress ************************************************************/

	// Add BuddyPress menu
	$wp_admin_bar->add_menu( array(
		'parent' => 'wp-logo',
		'id'     => 'buddypress',
		'title'  => __( 'BuddyPress.org' ),
		'href'   => 'https://buddypress.org',
	) );

	// Add "About BuddyPress" link
	$wp_admin_bar->add_menu( array(
		'parent' => 'buddypress',
		'id'     => 'bp-about-alt',
		'title'  => __( 'About BuddyPress' ),
		'href'   => 'https://buddypress.org/about/',
	) );

	// Add codex link
	$wp_admin_bar->add_menu( array(
		'parent' => 'buddypress',
		'id'     => 'bp-documentation',
		'title'  => __( 'Documentation' ),
		'href'   => 'https://codex.buddypress.org/',
	) );

	// Add forums link
	$wp_admin_bar->add_menu( array(
		'parent' => 'buddypress',
		'id'     => 'bp-support-forums',
		'title'  => __( 'Support Forums' ),
		'href'   => 'https://buddypress.org/support/',
	) );

	// Add feedback link
	$wp_admin_bar->add_menu( array(
		'parent' => 'buddypress',
		'id'     => 'bp-feedback',
		'title'  => __( 'Feedback' ),
		'href'   => 'https://buddypress.org/support/forum/feedback/',
	) );

	/** BuddyPress Developer **/
	$wp_admin_bar->add_group( array(
		'parent' => 'buddypress',
		'id'     => 'bp-developer',
		'meta'   => array(
			'class' => 'ab-sub-secondary',
		),
	) );

	$wp_admin_bar->add_menu( array(
		'parent' => 'bp-developer',
		'id'     => 'bp-trac',
		'title'  => __( 'Developer Trac' ),
		'href'   => 'https://buddypress.trac.wordpress.org'
	) );

	$wp_admin_bar->add_menu( array(
		'parent' => 'bp-developer',
		'id'     => 'bp-dev-blog',
		'title'  => __( 'Developer Blog' ),
		'href'   => 'https://bpdevel.wordpress.com'
	) );
}
add_action( 'admin_bar_menu', 'bporg_new_admin_bar_wp_menu', 10 );

/**
 * Add a new "Site Name" menu with less things for average users to do
 *
 * @author johnjamesjacoby
 * @since 1.0.2
 */
function bporg_new_admin_bar_site_menu( $wp_admin_bar ) {

	// Create submenu items.
	if ( is_user_logged_in() ) {

		if ( is_main_site() ) {

			// Add a link to create a new topic.
			$wp_admin_bar->add_menu( array(
				'id'     => 'bp-new-topic',
				'parent' => 'top-secondary',
				'title'  => __( 'Create New Topic' ),
				'href'   => 'https://buddypress.org/support/new-topic/'
			) );
		}

	// Not logged in
	} else {
		$wp_admin_bar->add_menu( array(
			'id'     => 'bp-login',
			'parent' => 'top-secondary',
			'title'  => __( 'Log in' ),
			'href'   => wp_login_url(),
		) );
	}
}
add_action( 'admin_bar_menu', 'bporg_new_admin_bar_site_menu', 2 );

/**
 * Add the "My Account" menu and all submenus.
 *
 * @since BuddyPress (1.6)
 * @todo Deprecate WP 3.2 Toolbar compatibility when we drop 3.2 support
 */
function bporg_admin_bar_my_account_menu( $wp_admin_bar ) {
	global $bp;

	// Logged in user
	if ( is_user_logged_in() ) {

		// Manually include this, incase of BP maintenance mode
		if ( ! function_exists( 'bp_loggedin_user_domain' ) ) {
			require_once( WP_CONTENT_DIR . '/plugins/buddypress/bp-members/bp-members-template.php' );
		}

		// Stored in the global so we can add menus easily later on
		$bp->my_account_menu_id = 'my-account-buddypress';

		// Create the main 'My Account' menu
		$wp_admin_bar->add_menu( array(
			'id'     => $bp->my_account_menu_id,
			'group'  => true,
			'title'  => __( 'Edit My Profile', 'buddypress' ),
			'href'   => bp_loggedin_user_domain(),
			'meta'   => array(
			'class'  => 'ab-sub-secondary'
		) ) );

	// Show login and sign-up links
	} elseif ( !empty( $wp_admin_bar ) ) {
		add_filter ( 'show_admin_bar', '__return_true' );

		$wp_admin_bar->add_group( array(
			'parent' => 'my-account',
			'id'     => 'user-actions',
		) );

		$user_info  = get_avatar( 0, 64, 'mystery' );
		$user_info .= '<span class="display-name">Anonymous</span>';
		$user_info .= '<span class="username">Not Logged In</span>';

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'user-info',
			'title'  => $user_info,
			'href'   => wp_login_url(),
			'meta'   => array(
				'tabindex' => -1,
			),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'register',
			'title'  => __( 'Register' ),
			'href'   => wp_registration_url(),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'login',
			'title'  => __( 'Log In' ),
			'href'   => wp_login_url(),
		) );
	}
}
add_action( 'admin_bar_menu', 'bporg_admin_bar_my_account_menu', 4 );

/**
 * Add the "My Account" item.
 *
 * @author johnjamesjacoby
 * @since 1.0.2
 */
function bporg_admin_bar_my_account_item( $wp_admin_bar ) {
	$user_id      = get_current_user_id();
	$current_user = wp_get_current_user();

	// Logged out
	if ( empty( $user_id ) ) {
		$howdy  = __( 'Anonymous' );
		$avatar = get_avatar( 0, 16, 'mystery' );
		$class  = empty( $avatar ) ? '' : 'with-avatar';

		$wp_admin_bar->add_menu( array(
			'id'     => 'my-account',
			'parent' => 'top-secondary',
			'title'  => $howdy . $avatar,
			'href'   => wp_login_url(),
			'meta'   => array(
				'class' => $class,
				'title' => __( 'My Account' ),
			),
		) );

	// Logged in
	} else {
		if ( current_user_can( 'read' ) ) {
			$profile_url = get_edit_profile_url( $user_id );
		} elseif ( is_multisite() ) {
			$profile_url = get_dashboard_url( $user_id, 'profile.php' );
		} else {
			$profile_url = false;
		}

		$howdy  = '<span class="display-name">' . $current_user->display_name . '</span>';
		$avatar = get_avatar( $user_id, 26 );
		$class  = empty( $avatar ) ? '' : 'with-avatar';

		$wp_admin_bar->add_node(
			array(
				'id'     => 'my-account',
				'parent' => 'top-secondary',
				'title'  => $howdy . $avatar,
				'href'   => $profile_url,
				'meta'   => array(
					'class'      => $class,
					'menu_title' => $current_user->display_name,
					'tabindex'   => ( false !== $profile_url ) ? '' : 0,
				),
			)
		);
	}
}
add_action( 'admin_bar_menu', 'bporg_admin_bar_my_account_item', 9991 );

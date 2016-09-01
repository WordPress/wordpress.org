<?php

/**
 * Remove a few toolbar items we do not need
 *
 * @author johnjamesjacoby
 * @since 1.0.1
 */
function bborg_toolbar_tweaks() {
	remove_action( 'admin_bar_menu', 'wp_admin_bar_my_account_menu',  0  );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_search_menu',      4  );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_my_account_item',  7  );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_wp_menu',          10 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_site_menu',        30 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu',    60 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_new_content_menu', 70 );
}
add_action( 'add_admin_bar_menus', 'bborg_toolbar_tweaks', 11 );

/**
 * Remove the BuddyPress and bbPress about menus
 *
 * @author johnjamesjacoby
 * @global object $wp_admin_bar
 */
function bbporg_remove_about_pages( $wp_admin_bar ) {
	$wp_admin_bar->remove_menu( 'bp-about'  );
	$wp_admin_bar->remove_menu( 'bbp-about' );
}
add_action( 'admin_bar_menu', 'bbporg_remove_about_pages', 99 );

/**
 * Add a new main top-left menu with links for each project.
 *
 * @todo GlotPress/BackPress
 *
 * @author johnjamesjacoby
 * @since 1.0.1
 */
function bbporg_new_admin_bar_wp_menu( $wp_admin_bar ) {
	$wp_admin_bar->add_menu( array(
		'id'    => 'wp-logo',
		'title' => '<span class="ab-icon"></span>',
		'href'  => 'https://bbpress.org',
		'meta'  => array(
			'title' => __( 'bbPress.org' ),
		),
	) );

	/** WordPress *************************************************************/

	// Add WordPress menu
	$wp_admin_bar->add_menu( array(
		'parent' => 'wp-logo',
		'id'     => 'wordpress',
		'title'  => __( 'WordPress.org' ),
		'href'  => 'https://wordpress.org',
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
		'parent'    => 'wordpress',
		'id'        => 'wp-documentation',
		'title'     => __('Documentation'),
		'href'      => 'https://codex.wordpress.org/',
	) );

	// Add forums link
	$wp_admin_bar->add_menu( array(
		'parent'    => 'wordpress',
		'id'        => 'wp-support-forums',
		'title'     => __('Support Forums'),
		'href'      => 'https://wordpress.org/support/',
	) );

	// Add feedback link
	$wp_admin_bar->add_menu( array(
		'parent'    => 'wordpress',
		'id'        => 'wp-feedback',
		'title'     => __('Feedback'),
		'href'      => 'https://wordpress.org/support/forum/requests-and-feedback',
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
		'href'  => 'https://bbpress.org',
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
		'parent'    => 'bbpress',
		'id'        => 'bbp-documentation',
		'title'     => __( 'Documentation' ),
		'href'      => 'https://codex.bbpress.org/',
	) );

	// Add forums link
	$wp_admin_bar->add_menu( array(
		'parent'    => 'bbpress',
		'id'        => 'bbp-support-forums',
		'title'     => __( 'Support Forums' ),
		'href'      => 'https://bbpress.org/forums/',
	) );

	// Add feedback link
	$wp_admin_bar->add_menu( array(
		'parent'    => 'bbpress',
		'id'        => 'bbp-feedback',
		'title'     => __( 'Feedback' ),
		'href'      => 'https://bbpress.org/forums/forum/requests-and-feedback',
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
		'href'  => 'https://buddypress.org',
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
		'parent'    => 'buddypress',
		'id'        => 'bp-documentation',
		'title'     => __( 'Documentation' ),
		'href'      => 'https://codex.buddypress.org/',
	) );

	// Add forums link
	$wp_admin_bar->add_menu( array(
		'parent'    => 'buddypress',
		'id'        => 'bp-support-forums',
		'title'     => __( 'Support Forums' ),
		'href'      => 'https://buddypress.org/support/',
	) );

	// Add feedback link
	$wp_admin_bar->add_menu( array(
		'parent'    => 'buddypress',
		'id'        => 'bp-feedback',
		'title'     => __( 'Feedback' ),
		'href'      => 'https://buddypress.org/support/forum/feedback/',
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
add_action( 'admin_bar_menu', 'bbporg_new_admin_bar_wp_menu', 10 );

/**
 * Add a new "Site Name" menu with less things for average users to do
 *
 * @author johnjamesjacoby
 * @since 1.0.2
 */
function bbporg_new_admin_bar_site_menu( $wp_admin_bar ) {

	$wp_admin_bar->add_menu( array(
		'id'    => 'bbp-site-name',
		'title' => __( 'bbPress.org' ),
		'href'  => home_url( '/' )
	) );

	// Create submenu items.

	if ( is_user_logged_in() ) {

		// Add a link to create a new topic.
		$wp_admin_bar->add_menu( array(
			'id'     => 'bbp-new-topic',
			'title'  => __( 'Create New Topic' ),
			'href'   => 'https://bbpress.org/forums/new-topic/'
		) );

		// Add an option to visit the admin dashboard
		if ( is_super_admin() ) {

			$wp_admin_bar->add_group( array(
				'parent' => 'bbp-site-name',
				'id'     => 'bbp-site-name-super-admin',
				'meta'   => array(
					'class' => 'ab-sub-secondary',
				),
			) );

			$wp_admin_bar->add_menu( array(
				'parent' => 'bbp-site-name-super-admin',
				'id'     => 'bbp-admin-link',
				'title'  => __( 'Admin Dashbooard' ),
				'href'   => get_admin_url()
			) );
		}

	// Not logged in
	} else {
		$wp_admin_bar->add_menu( array(
			'id'     => 'bbp-login',
			'title'  => __( 'Log in' ),
			'href'   => 'https://bbpress.org/login/'
		) );
	}
}
add_action( 'admin_bar_menu', 'bbporg_new_admin_bar_site_menu', 20 );

/**
 * Add the "My Account" item.
 *
 * @author johnjamesjacoby
 * @since 1.0.2
 */
function bbporg_admin_bar_my_account_item( $wp_admin_bar ) {

	if ( is_user_logged_in() ) {
		$user_id      = get_current_user_id();
		$current_user = wp_get_current_user();
		$profile_url  = get_edit_profile_url( $user_id );
		$avatar       = get_avatar( $user_id, 16 );
		$howdy        = $current_user->display_name;
		$class        = empty( $avatar ) ? '' : 'with-avatar';

		$wp_admin_bar->add_menu( array(
			'id'        => 'my-account',
			'parent'    => 'top-secondary',
			'title'     => $howdy . $avatar,
			'href'      => $profile_url,
			'meta'      => array(
				'class'     => $class,
				'title'     => __('My Account'),
			),
		) );
	} else {
		$avatar = get_avatar( 0, 16, 'mystery' );
		$howdy  = __( 'Anonymous' );
		$class  = empty( $avatar ) ? '' : 'with-avatar';

		$wp_admin_bar->add_menu( array(
			'id'        => 'my-account',
			'parent'    => 'top-secondary',
			'title'     => $howdy . $avatar,
			'href'      => 'https://bbpress.org/login/',
			'meta'      => array(
				'class'     => $class,
				'title'     => __('My Account'),
			),
		) );
	}
}
add_action( 'admin_bar_menu', 'bbporg_admin_bar_my_account_item', 0 );

/**
 * Add the "My Account" submenu items.
 *
 * @author johnjamesjacoby
 * @since 1.0.2
 */
function bbporg_admin_bar_my_account_menu( $wp_admin_bar ) {

	// Logged in
	if ( is_user_logged_in() ) {
		$user_id      = get_current_user_id();
		$current_user = wp_get_current_user();
		$profile_url  = get_edit_profile_url( $user_id );

		$wp_admin_bar->add_group( array(
			'parent' => 'my-account',
			'id'     => 'user-actions',
		) );

		$user_info  = get_avatar( $user_id, 64 );
		$user_info .= "<span class='display-name'>{$current_user->display_name}</span>";

		if ( $current_user->display_name !== $current_user->user_nicename )
			$user_info .= "<span class='username'>{$current_user->user_nicename}</span>";

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'user-info',
			'title'  => $user_info,
			'href'   => $profile_url,
			'meta'   => array(
				'tabindex' => -1,
			),
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'edit-profile',
			'title'  => __( 'Edit My Profile' ),
			'href' => $profile_url,
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'logout',
			'title'  => __( 'Log Out' ),
			'href'   => wp_logout_url(),
		) );

		// User topics
		$wp_admin_bar->add_group( array(
			'parent' => 'my-account',
			'id'     => 'user-topics',
			'meta'   => array(
				'class' => 'ab-sub-secondary',
			),
		) );

		// Add bbPress menus if bbPress is enabled
		if ( function_exists( 'bbpress' ) ) {
			$wp_admin_bar->add_menu( array(
				'parent' => 'user-topics',
				'id'     => 'topics-started',
				'title'  => __( 'Topics Started' ),
				'href'   => bbp_get_user_topics_created_url( bbp_get_current_user_id() ),
			) );
			$wp_admin_bar->add_menu( array(
				'parent' => 'user-topics',
				'id'     => 'replies-created',
				'title'  => __( 'Replies Created' ),
				'href'   => bbp_get_user_replies_created_url( bbp_get_current_user_id() ),
			) );
			$wp_admin_bar->add_menu( array(
				'parent' => 'user-topics',
				'id'     => 'subscriptions',
				'title'  => __( 'Subscribed Topics' ),
				'href'   => bbp_get_subscriptions_permalink( bbp_get_current_user_id() ),
			) );
			$wp_admin_bar->add_menu( array(
				'parent' => 'user-topics',
				'id'     => 'favorites',
				'title'  => __( 'Favorite Topics' ),
				'href'   => bbp_get_favorites_permalink( bbp_get_current_user_id() ),
			) );
		}

	// Anonymous
	} else {
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
			'href'   => 'https://bbpress.org/login/',
			'meta'   => array(
				'tabindex' => -1,
			),
		) );

		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'register',
			'title'  => __( 'Register' ),
			'href'   => 'https://bbpress.org/register/'
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'lost-pass',
			'title'  => __( 'Lost Password' ),
			'href'   => 'https://bbpress.org/lost-password/'
		) );
		$wp_admin_bar->add_menu( array(
			'parent' => 'user-actions',
			'id'     => 'login',
			'title'  => __( 'Log In' ),
			'href'   => 'https://bbpress.org/login/'
		) );
	}
}
add_action( 'admin_bar_menu', 'bbporg_admin_bar_my_account_menu', 7 );

/**
 * Force toolbar styling into looking like sub-navigation
 *
 * Note: slightly experimental - kind of a hack
 *
 * @author johnjamesjacoby
 * @since 1.0.3
 */
function bbporg_toolbar_css_overrides() {
?>
	<style type="text/css">
		/* Admin Bar */
		<?php if ( is_main_site() && is_front_page() ) : ?>
			#wpadminbar { display: none !important; }
		<?php else : ?>
			#wpadminbar { color: #555; background: #eee; top: 81px; border-bottom: 1px solid #ddd; height: 42px; }
			#wpadminbar #wp-toolbar a.ab-item, #wpadminbar .quicklinks li#wp-admin-bar-bp-notifications > a { padding: 5px; }
			#wpadminbar #wp-toolbar ul.ab-submenu a.ab-item { padding: 0 2em 0 1em; }
			#wpadminbar #wp-toolbar .ab-top-secondary .menupop .menupop > a.ab-item  { padding: 0 1em 0 2em; }
			#wpadminbar a.ab-item, #wpadminbar > #wp-toolbar span.ab-label, #wpadminbar > #wp-toolbar span.noticon { color: #555; }
			#wpadminbar .ab-icon, #wpadminbar .ab-icon:before, #wpadminbar .ab-item:before, #wpadminbar .ab-item:after { color: #d84800; }
			#wpadminbar .ab-top-menu > li:hover > .ab-item,
			#wpadminbar .ab-top-menu > li.hover > .ab-item,
			#wpadminbar .ab-top-menu > li > .ab-item:focus,
			#wpadminbar.nojq .quicklinks .ab-top-menu > li > .ab-item:focus,
			#wpadminbar-nojs .ab-top-menu > li.menupop:hover > .ab-item,
			#wpadminbar .ab-top-menu > li.menupop.hover > .ab-item { color: #555; background: #eee; }
			#wpadminbar > #wp-toolbar li:hover span.ab-label, #wpadminbar > #wp-toolbar li.hover span.ab-label, #wpadminbar > #wp-toolbar a:focus span.ab-label { color: #555; }
			#wpadminbar li:hover .ab-icon:before, #wpadminbar li:hover .ab-item:before, #wpadminbar li:hover .ab-item:after, #wpadminbar li:hover #adminbarsearch:before { color: #999; }
			#wp-toolbar { width: 960px; margin: 0 auto; }

			/* Admin Bar: submenu */
			#wpadminbar .menupop .ab-sub-wrapper { background: #eee; }
			#wpadminbar .quicklinks .menupop .ab-submenu { background: #eee; }
			#wpadminbar .quicklinks .menupop ul.ab-sub-secondary, #wpadminbar .quicklinks .menupop ul.ab-sub-secondary .ab-submenu { background: #ddd; }
			#wpadminbar .ab-submenu .ab-item, #wpadminbar .quicklinks .menupop ul li a, #wpadminbar .quicklinks .menupop.hover ul li a, #wpadminbar-nojs .quicklinks .menupop:hover ul li a { color: #555; }
			#wpadminbar .quicklinks li .blavatar, #wpadminbar .menupop .menupop > .ab-item:before { color: #f1f1f3; }
			#wpadminbar .quicklinks .menupop ul li a:hover,
			#wpadminbar .quicklinks .menupop ul li a:focus,
			#wpadminbar .quicklinks .menupop ul li a:hover strong,
			#wpadminbar .quicklinks .menupop ul li a:focus strong,
			#wpadminbar .quicklinks .menupop.hover ul li a:hover,
			#wpadminbar .quicklinks .menupop.hover ul li a:focus,
			#wpadminbar.nojs .quicklinks .menupop:hover ul li a:hover,
			#wpadminbar.nojs .quicklinks .menupop:hover ul li a:focus,
			#wpadminbar li:hover .ab-icon:before,
			#wpadminbar li:hover .ab-item:before,
			#wpadminbar li a:focus .ab-icon:before,
			#wpadminbar li .ab-item:focus:before,
			#wpadminbar li.hover .ab-icon:before,
			wpadminbar li.hover .ab-item:before,
			#wpadminbar li:hover .ab-item:after,
			#wpadminbar li.hover .ab-item:after,
			#wpadminbar li:hover #adminbarsearch:before { color: #d84800; }
			#wpadminbar .quicklinks li a:hover .blavatar, #wpadminbar .menupop .menupop > .ab-item:hover:before { color: #d84800; }

			/* Admin Bar: my account */
			#wpadminbar .quicklinks li#wp-admin-bar-my-account.with-avatar > a img { border-color: #eee; background-color: #eee; }
			#wpadminbar #wp-admin-bar-user-info .display-name { color: #555; }
			#wpadminbar #wp-admin-bar-user-info a:hover .display-name { color: #d84800; }
			#wpadminbar #wp-admin-bar-user-info .username { color: #000; }

			/* Some responsive'ish tweaks */
			@media screen and ( max-width: 460px ) {
				#wpadminbar {
					top: 140px;
				}
				#wp-toolbar {
					width: 100%;
				}
				#wpadminbar #wp-toolbar a.ab-item,
				#wpadminbar .quicklinks li#wp-admin-bar-bp-notifications > a { padding: 0; }
			}
		<?php endif; ?>
	</style>
<?php
}
add_theme_support( 'admin-bar', array( 'callback' => 'bbporg_toolbar_css_overrides' ) );

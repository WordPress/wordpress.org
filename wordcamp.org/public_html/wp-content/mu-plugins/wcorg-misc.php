<?php

/*
 * Miscellaneous snippets that don't warrant their own file
 */


/*
 * Prevents 'index.php' from being prepended to permalink options.
 *
 * is_nginx() returns false on WordCamp.org because $_SERVER['SERVER_SOFTWARE'] is empty.
 */
add_filter( 'got_url_rewrite', '__return_true' );

/*
 * Include pages in the list of posts types that can have comments closed automatically
 */
function wcorg_close_comments_for_post_types( $post_types ) {
	$post_types[] = 'page';
	return $post_types;
}
add_filter( 'close_comments_for_post_types', 'wcorg_close_comments_for_post_types' );

/*
 * Determine which Jetpack modules should be automatically activated when new sites are created
 */
function wcorg_default_jetpack_modules( $modules ) {
	$modules = array_diff( $modules, array( 'widget-visibility' ) );
	array_push( $modules, 'contact-form', 'shortcodes', 'custom-css', 'subscriptions' );

	return $modules;
}
add_filter( 'jetpack_get_default_modules', 'wcorg_default_jetpack_modules' );

/*
 * We want to let organizers use shortcodes inside Text widgets
 */
add_filter( 'widget_text', 'do_shortcode' );

/**
 * Disable certain network-activate plugins on specific sites.
 *
 * @param array $plugins
 *
 * @return array
 */
function wcorg_disable_network_activated_plugins_on_sites( $plugins ) {

	/*
	 * central.wordcamp.org, plan.wordcamp.org
     *
	 * These are plugins for individual WordCamp sites, so they aren't relevant for Central and Plan.
	 * They clutter the admin menu and slow down page loads.
	 */
	if ( in_array( get_current_blog_id(), array( BLOG_ID_CURRENT_SITE, 63 ) ) ) {
		unset( $plugins['camptix-extras/camptix-extras.php'] );
		unset( $plugins['camptix-network-tools/camptix-network-tools.php'] );
		unset( $plugins['tagregator/bootstrap.php'] );
		unset( $plugins['wc-canonical-years/wc-canonical-years.php'] );
		unset( $plugins['wordcamp-organizer-nags/wordcamp-organizer-nags.php'] );
		unset( $plugins['wordcamp-payments/bootstrap.php'] );
	}

	return $plugins;
}
add_filter( 'site_option_active_sitewide_plugins', 'wcorg_disable_network_activated_plugins_on_sites' );

/*
 * Show Tagregator's log to network admins
 */
function wcorg_show_tagregator_log() {
	if ( current_user_can( 'manage_network' ) ) {
		add_filter( 'tggr_show_log', '__return_true' );
	}
}
add_action( 'init', 'wcorg_show_tagregator_log' );

/**
 * Prepend a unique string to contact form subjects.
 *
 * Otherwise some e-mail clients and management systems -- *cough* SupportPress *cough* -- will incorrectly group
 * separate messages into the same thread.
 *
 * It'd be better to have the key appended rather than prepended, but SupportPress won't always recognize the
 * subject as unique if we do that :|
 *
 * @param string $subject
 *
 * @return string
 */
function wcorg_grunion_unique_subject( $subject ) {
	return sprintf( '[%s] %s', wp_generate_password( 8, false ), $subject );
}
add_filter( 'contact_form_subject', 'wcorg_grunion_unique_subject' );

/**
 * Modify the space allocation on a per-size basis.
 *
 * @param int $size
 *
 * @return int
 */
function wcorg_modify_default_space_allotment( $size ) {
	switch ( get_current_blog_id() ) {
		case '364': // 2014.sf
			$size = 750;
			break;
	}

	return $size;
}
add_filter( 'get_space_allowed', 'wcorg_modify_default_space_allotment' );

<?php
/*
Plugin Name: Theme Repository
Plugin URI:
Description: Transforms a WordPress site in The Official Theme Directory.
Version: 0.1
Author: wordpressdotorg
Author URI: http://wordpress.org/
Text Domain: wporg-themes
License: GPLv2
License URI: http://opensource.org/licenses/gpl-2.0.php
*/

// Load base repo package.
include_once plugin_dir_path( __FILE__ ) . 'class-repo-package.php';

// Load theme repo package.
include_once plugin_dir_path( __FILE__ ) . 'class-wporg-themes-repo-package.php';

// Load uploader.
include_once plugin_dir_path( __FILE__ ) . 'upload.php';

// Load Themes API adjustments.
include_once plugin_dir_path( __FILE__ ) . 'themes-api.php';

// Load adjustments to the edit.php screen for repopackage posts.
include_once plugin_dir_path( __FILE__ ) . 'admin-edit.php';


/**
 * Things to change on activation.
 */
function wporg_themes_activate() {
	wporg_themes_init();
	flush_rewrite_rules();

	do_action( 'wporg_themes_activation' );
}
register_activation_hook( __FILE__, 'wporg_themes_activate' );

/**
 * Things to change on deactivation.
 */
function wporg_themes_deactivate() {
	flush_rewrite_rules();

	do_action( 'wporg_themes_deactivation' );
}
register_deactivation_hook( __FILE__, 'wporg_themes_deactivate' );

/**
 * Initialize.
 */
function wporg_themes_init() {
	load_plugin_textdomain( 'wporg-themes' );

	// This is the base generic type for repo plugins.
	if ( ! post_type_exists( 'repopackage' ) ) {
		register_post_type( 'repopackage', array(
			'labels'              => array(
				'name'               => __( 'Packages', 'wporg-themes' ),
				'singular_name'      => __( 'Package', 'wporg-themes' ),
				'add_new'            => __( 'Add New', 'wporg-themes' ),
				'add_new_item'       => __( 'Add New Package', 'wporg-themes' ),
				'edit_item'          => __( 'Edit Package', 'wporg-themes' ),
				'new_item'           => __( 'New Package', 'wporg-themes' ),
				'view_item'          => __( 'View Package', 'wporg-themes' ),
				'search_items'       => __( 'Search Packages', 'wporg-themes' ),
				'not_found'          => __( 'No packages found', 'wporg-themes' ),
				'not_found_in_trash' => __( 'No packages found in Trash', 'wporg-themes' ),
				'parent_item_colon'  => __( 'Parent Package:', 'wporg-themes' ),
				'menu_name'          => __( 'Packages', 'wporg-themes' ),
			),
			'description'         => __( 'A package', 'wporg-themes' ),
			'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'page-attributes' ),
			'taxonomies'          => array( 'category', 'post_tag', 'type' ),
			'public'              => true,
			'show_in_nav_menus'   => false,
			'has_archive'         => true,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-art',
		) );
	}

	if ( ! post_type_exists( 'theme_shop' ) ) {
		register_post_type( 'theme_shop', array(
			'labels'              => array(
				'name'               => __( 'Theme Shops', 'wporg-themes' ),
				'singular_name'      => __( 'Theme Shop', 'wporg-themes' ),
				'add_new'            => __( 'Add New', 'wporg-themes' ),
				'add_new_item'       => __( 'Add New Theme Shop', 'wporg-themes' ),
				'edit_item'          => __( 'Edit Theme Shop', 'wporg-themes' ),
				'new_item'           => __( 'New Theme Shop', 'wporg-themes' ),
				'view_item'          => __( 'View Theme Shop', 'wporg-themes' ),
				'search_items'       => __( 'Search Theme Shops', 'wporg-themes' ),
				'not_found'          => __( 'No Theme Shops found', 'wporg-themes' ),
				'not_found_in_trash' => __( 'No Theme Shops found in Trash', 'wporg-themes' ),
				'parent_item_colon'  => __( 'Parent Theme Shop:', 'wporg-themes' ),
				'menu_name'          => __( 'Theme Shops', 'wporg-themes' ),
			),
			'supports'            => array( 'title', 'editor', 'author', 'custom-fields' ),
			'public'              => false,
			'show_ui'             => true,
			'show_in_nav_menus'   => false,
			'exclude_from_search' => true,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-businessman',
		) );
	}

	// Add the browse/* views
	add_rewrite_tag( '%browse%', '(featured|popular|new)' );
	add_permastruct( 'browse', 'browse/%browse%' );

}
add_action( 'init', 'wporg_themes_init' );

/**
 * Adjusts query to account for custom views.
 *
 * @param WP_Query $wp_query
 * @return WP_Query
 */
function wporg_themes_set_up_query( $wp_query ) {
	if ( is_admin() || in_array( $wp_query->get( 'pagename' ), array( 'upload', 'commercial', 'getting-started' ) ) || in_array( $wp_query->get( 'post_type' ), array( 'nav_menu_item', 'theme_shop' ) ) ) {
		return $wp_query;
	}

	$wp_query->set( 'post_type', 'repopackage' );

	if ( $wp_query->is_home() && ! $wp_query->get( 'browse' ) ) {
		$wp_query->set( 'browse', 'featured' );
	}

	if ( $wp_query->get( 'browse' ) ) {
		switch ( $wp_query->get( 'browse' ) ) {
			case 'featured':
				$wp_query->set( 'paged', 1 );
				$wp_query->set( 'posts_per_page', 15 );
				$wp_query->set( 'post__in', (array) wp_cache_get( 'browse-featured', 'theme-info' ) );
				break;

			case 'popular':
				add_filter( 'posts_clauses', 'wporg_themes_popular_posts_clauses' );
				break;

			case 'new':
				// Nothing to do here.
				break;

		}

		// Only return themes that were updated in the last two years for all 'browse' requests.
		$wp_query->set( 'date_query', array(
			array(
				'column' => 'post_modified_gmt',
				'after'  => '-2 years',
			),
		) );

	}

	return $wp_query;
}
add_filter( 'pre_get_posts', 'wporg_themes_set_up_query' );

/**
 * Filter the permalink for the Packages to be /post_name/
 *
 * @param string $link The generated permalink
 * @param string $post The package object
 * @return string
 */
function wporg_themes_package_link( $link, $post ) {
	if ( 'repopackage' != $post->post_type ) {
		return $link;
	}

	return trailingslashit( home_url( $post->post_name ) );
}
add_filter( 'post_type_link', 'wporg_themes_package_link', 10, 2 );

/**
 * Adjusts the amount of found posts when browsing featured themes.
 *
 * @param string   $found_posts
 * @param WP_Query $wp_query
 * @return string
 */
function wporg_themes_found_posts( $found_posts, $wp_query ) {
	if ( $wp_query->is_main_query() && 'featured' === $wp_query->get( 'browse' ) ) {
		$found_posts = $wp_query->get( 'posts_per_page' );
	}

	return $found_posts;
}
add_filter( 'found_posts', 'wporg_themes_found_posts', 10, 2 );

/**
 * Filters SQL clauses, to set up a query for the most popular themes based on downloads.
 *
 * @param array $clauses
 * @return array
 */
function wporg_themes_popular_posts_clauses( $clauses ) {
	global $wpdb;

	$week = gmdate( 'Y-m-d', strtotime( 'last week' ) );
	$clauses['where']  .= " AND s.stamp >= '{$week}'";
	$clauses['groupby'] = "{$wpdb->posts}.ID";
	$clauses['join']    = "JOIN bb_themes_stats AS s ON ( {$wpdb->posts}.post_name = s.slug )";
	$clauses['orderby'] = 'week_downloads DESC';
	$clauses['fields'] .= ', SUM(s.downloads) AS week_downloads';

	return $clauses;
}

/**
 * Checks if ther current users is a super admin before allowing terms to be added.
 *
 * @param string           $term The term to add or update.
 * @return string|WP_Error The term to add or update or WP_Error on failure.
 */
function wporg_themes_pre_insert_term( $term ) {
	if ( ! is_super_admin() ) {
		$term = new WP_Error( 'not-allowed', __( 'You are not allowed to add terms.', 'wporg-themes' ) );
	}

	return $term;
}
add_filter( 'pre_insert_term', 'wporg_themes_pre_insert_term' );

/**
 * Returns the specified meta value for a version of a theme.
 *
 * @param int          $post_id Post ID.
 * @param string       $meta_key Post meta key.
 * @param string       $version Optional. The theme version to get the meta value for. Default: 'latest'.
 * @return bool|string The version-specific meta value or False on failure.
 */
function wporg_themes_get_version_meta( $post_id, $meta_key, $version = 'latest' ) {
	$value = false;
	$meta  = (array) get_post_meta( $post_id, $meta_key, true );

	if ( 'latest' == $version ) {
		$package = new WPORG_Themes_Repo_Package( $post_id );
		$version = $package->latest_version();
	}

	if ( ! empty( $meta[ $version ] ) ) {
		$value = $meta[ $version ];
	}

	return $value;
}

/**
 * Returns the status of a theme's version.
 *
 * @param int          $post_id Post ID.
 * @param string       $version The theme version to get the status for.
 * @return bool|string The version-specific meta value or False on failure.
 */
function wporg_themes_get_version_status( $post_id, $version ) {
	return wporg_themes_get_version_meta( $post_id, '_status', $version );
}

/* UPDATING THEME VERSIONS */

/**
 * Handles updating the status of theme versions.
 *
 * @param int       $post_id         Post ID.
 * @param string    $current_version The theme version to update.
 * @param string    $new_status      The status to update the current version to.
 * @return int|bool Meta ID if the key didn't exist, true on successful update,
 *                  false on failure.
 */
function wporg_themes_update_version_status( $post_id, $current_version, $new_status ) {
	$meta       = (array) get_post_meta( $post_id, '_status', true );
	$old_status = isset( $meta[ $current_version ] ) ? $meta[ $current_version ] : false;

	// Don't do anything when the status hasn't changed.
	if ( $new_status == $old_status ) {
		return;
	}

	switch ( $new_status ) {
		// There can only be one version with these statuses:
		case 'new':
		case 'live':
			// Discard all previous versions with that status.
			foreach ( array_keys( $meta, $new_status ) as $version ) {
				if ( version_compare( $version, $current_version, '<' ) ) {
					$meta[ $version ] = 'old';
				}
			}

			// Mark the current version appropriately.
			$meta[ $current_version ] = $new_status;
			break;

		// Marking a version as Old, does not have repercussions on other versions.
		case 'old':
			$meta[ $current_version ] = $new_status;
			break;
	}

	/**
	 * @param int    $post_id         Post ID.
	 * @param string $current_version The theme version that was updated.
	 * @param string $new_status      The new status for that theme version.
	 * @param string $old_status      The old status for that theme version.
	 */
	do_action( 'wporg_themes_update_version_status', $post_id, $current_version, $new_status, $old_status );

	/**
	 * The dynamic portion of the hook name, `$new_status`, refers to the new
	 * status of that theme version.
	 *
	 * @param int    $post_id         Post ID.
	 * @param string $current_version The theme version that was updated.
	 * @param string $old_status      The old status for that theme version.
	 */
	do_action( "wporg_themes_update_version_{$new_status}", $post_id, $current_version, $old_status );

	return update_post_meta( $post_id, '_status', $meta );
}

/**
 * Approves a theme.
 *
 * Sets theme version to live, publishes a theme if initially approved, and notifies the theme author.
 *
 * @param int    $post_id
 * @param string $version
 * @param string $old_status
 */
function wporg_themes_approve_version( $post_id, $version, $old_status ) {
	$post = get_post( $post_id );

	wporg_themes_update_wpthemescom( $post->post_name, $version );

	// Update the post with this version's description and tags.
	$theme_data = wporg_themes_get_header_data( sprintf( 'https://themes.svn.wordpress.org/%1$s/%2$s/style.css', $post->post_name, $version ) );
	wp_update_post( array(
		'ID'           => $post_id,
		'post_content' => $theme_data['Description'],
		'tags_input'   => $theme_data['Tags'],
	) );

	/*
	 * Bail if we're activating an old version, the author does not need to be
	 * notified about that.
	 */
	if ( 'old' == $old_status ) {
		return;
	}

	$ticket_id = wporg_themes_get_version_meta( $post_id, '_ticket_id', $version );
	$subject = $content = '';

	// TODO: Set locale to theme author language.

	// Congratulate theme author!
	if ( 'publish' == $post->post_status ) {
		$subject = sprintf( __( '[WordPress Themes] %1$s %2$s is now live', 'wporg-themes' ), $post->post_title, $version );
		// Translators: 1: Theme version number; 2: Theme name; 3: Theme URL.
		$content = sprintf( __( 'Version %1$s of %2$s is now live at %3$s.', 'wporg-themes' ), $version, $post->post_title, "https://wordpress.org/themes/{$post->post_name}" ) . "\n\n";

	} else {
		$subject = sprintf( __( '[WordPress Themes] %s has been approved!', 'wporg-themes' ), $post->post_title );
		// Translators: 1: Theme name; 2: Theme URL.
		$content = sprintf( __( 'Congratulations, your new theme %1$s is now available to the public at %2$s.', 'wporg-themes' ), $post->post_title, "https://wordpress.org/themes/{$post->post_name}" ) . "\n\n";

		/*
		 * First time approval: Publish the theme.
		 *
		 * Uses `wp_update_post()` to also update post_time.
		 */
		$post_date = current_time( 'mysql' );
		wp_update_post( array(
			'ID'            => $post_id,
			'post_status'   => 'publish',
			'post_date'     => $post_date,
			'post_date_gmt' => $post_date,
		) );
	}

	$content .= sprintf( __( 'Any feedback items are at %s.', 'wporg-themes' ), "https://themes.trac.wordpress.org/ticket/$ticket_id" ) . "\n\n--\n";
	$content .= __( 'The WordPress.org Themes Team', 'wporg-themes' ) . "\n";
	$content .= 'https://make.wordpress.org/themes';

	wp_mail( get_user_by( 'id', $post->post_author )->user_email, $subject, $content, 'From: themes@wordpress.org' );
}
add_action( 'wporg_themes_update_version_live', 'wporg_themes_approve_version', 10, 3 );

/**
 * Closes a theme.
 *
 * Sets theme version to old and notifies the theme author.
 *
 * @param int    $post_id
 * @param string $version
 */
function wporg_themes_close_version( $post_id, $version ) {
	$post      = get_post( $post_id );
	$ticket_id = wporg_themes_get_version_meta( $post_id, '_ticket_id', $version );

	// TODO: Set locale to theme author language.

	// Notify theme author.
	$subject  = sprintf( __( '[WordPress Themes] %s - feedback', 'wporg-themes' ), $post->post_title );
	// Translators: 1: Theme name; 2: Ticket URL.
	$content  = sprintf( __( 'Feedback for the %1$s theme is at %2$s', 'wporg' ) . "\n\n--\n", $post->post_title, "https://themes.trac.wordpress.org/ticket/{$ticket_id}" );
	$content .= __( 'The WordPress.org Themes Team', 'wporg-themes' ) . "\n";
	$content .= 'https://make.wordpress.org/themes';

	wp_mail( get_user_by( 'id', $post->post_author )->user_email, $subject, $content, 'From: themes@wordpress.org' );
}
add_action( 'wporg_themes_update_version_old', 'wporg_themes_close_version', 10, 2 );

/**
 * Rolls back a live theme version.
 *
 * If the rolledback version was live, it finds the previous live version and
 * sets it live again.
 *
 * @param int    $post_id
 * @param string $version
 * @param string $old_status
 */
function wporg_themes_rollback_version( $post_id, $current_version, $old_status ) {
	// If the version wasn't live, there's nothing for us to do.
	if ( 'live' != $old_status ) {
		return;
	}

	if ( ! class_exists( 'Trac' ) ) {
		require_once ABSPATH . WPINC . '/class-IXR.php';
		require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
		require_once WPORGPATH . 'bb-theme/themes/lib/class-trac.php';
	}

	// Check for tickets that were set to live previously.
	$trac    = new Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );
	$tickets = (array) $trac->ticket_query( add_query_arg( array(
		'status'     => 'closed',
		'resolution' => 'live',
		'keywords'   => '~theme-' . get_post( $post_id )->post_name,
		'order'      => 'changetime',
		'desc'       => 1,
	) ) );
	$ticket = next( $tickets );

	// Bail if there is no prior live versions.
	if ( ! $ticket ) {
		return;
	}

	// Find the version number associated with the approved ticket.
	$ticket_ids   = get_post_meta( $post_id, '_ticket_id', true );
	$prev_version = array_search( $ticket, $ticket_ids );

	wporg_themes_update_version_status( $post_id, $prev_version, 'live' );
}
add_action( 'wporg_themes_update_version_new', 'wporg_themes_rollback_version', 10, 3 );

/**
 * Updates wp-themes.com with the latest version of a theme.
 *
 * @param string $theme_slug
 * @param string $theme_version
 */
function wporg_themes_update_wpthemescom( $theme_slug, $theme_version ) {
	global $wporg_webs;
	if ( ! $wporg_webs ) {
		return;
	}

	foreach ( $wporg_webs as $server ) {
		wp_remote_post( "http://$server/", array(
			'body'    => array(
				'theme_update'        => $theme_slug,
				'theme_version'       => $theme_version,
				'theme_action'        => 'update',
				'theme_update_secret' => THEME_PREVIEWS_SYNC_SECRET,
			),
			'headers' => array(
				'Host' => 'wp-themes.com',
			),
		) );
	}
}

/**
 * Completely removes a theme from wp-themes.com.
 *
 * This method is currently not in use.
 *
 * @param string $theme_slug
 */
function wporg_themes_remove_wpthemescom( $theme_slug ) {
	global $wporg_webs;
	if ( ! $wporg_webs ) {
		return;
	}

	foreach ( $wporg_webs as $server ) {
		wp_remote_post( "http://$server/", array(
			'body'    => array(
				'theme_update'        => $theme_slug,
				'theme_action'        => 'remove',
				'theme_update_secret' => THEME_PREVIEWS_SYNC_SECRET,
			),
			'headers' => array(
				'Host' => 'wp-themes.com',
			),
		) );
	}
}

/**
 * Custom version of core's deprecated `get_theme_data()` function.
 *
 * @param string $theme_file Path to the file.
 * @return array File headers.
 */
function wporg_themes_get_header_data( $theme_file ) {
	$themes_allowed_tags = array(
		'a'       => array(
			'href'  => array(),
			'title' => array(),
		),
		'abbr'    => array(
			'title' => array(),
		),
		'acronym' => array(
			'title' => array(),
		),
		'code'    => array(),
		'em'      => array(),
		'strong'  => array(),
	);

	$theme_data = implode( '', file( $theme_file ) );
	$theme_data = str_replace( '\r', '\n', $theme_data );
	preg_match( '|^[ \t\/*#@]*Theme Name:(.*)$|mi', $theme_data, $theme_name );
	preg_match( '|^[ \t\/*#@]*Theme URI:(.*)$|mi', $theme_data, $theme_uri );
	preg_match( '|^[ \t\/*#@]*Description:(.*)$|mi', $theme_data, $description );

	if ( preg_match( '|^[ \t\/*#@]*Author URI:(.*)$|mi', $theme_data, $author_uri ) ) {
		$author_uri = esc_url( trim( $author_uri[1] ) );
	} else {
		$author_uri = '';
	}

	if ( preg_match( '|^[ \t\/*#@]*Template:(.*)$|mi', $theme_data, $template ) ) {
		$template = wp_kses( trim( $template[1] ), $themes_allowed_tags );
	} else {
		$template = '';
	}

	if ( preg_match( '|^[ \t\/*#@]*Version:(.*)$|mi', $theme_data, $version ) ) {
		$version = wp_kses( trim( $version[1] ), $themes_allowed_tags );
	} else {
		$version = '';
	}

	if ( preg_match( '|^[ \t\/*#@]*Status:(.*)$|mi', $theme_data, $status ) ) {
		$status = wp_kses( trim( $status[1] ), $themes_allowed_tags );
	} else {
		$status = 'publish';
	}

	if ( preg_match( '|^[ \t\/*#@]*Tags:(.*)$|mi', $theme_data, $tags ) ) {
		$tags = array_map( 'trim', explode( ',', wp_kses( trim( $tags[1] ), array() ) ) );
	} else {
		$tags = array();
	}

	if ( preg_match( '|^[ \t\/*#@]*Author:(.*)$|mi', $theme_data, $author_name ) ) {
		$author = wp_kses( trim( $author_name[1] ), $themes_allowed_tags );
	} else {
		$author = 'Anonymous';
	}

	$name        = $theme = wp_kses( trim( $theme_name[1] ), $themes_allowed_tags );
	$theme_uri   = esc_url( trim( $theme_uri[1] ) );
	$description = wp_kses( trim( $description[1] ), $themes_allowed_tags );

	return array(
		'Name'        => $name,
		'Title'       => $theme,
		'URI'         => $theme_uri,
		'Description' => $description,
		'Author'      => $author,
		'Author_URI'  => $author_uri,
		'Version'     => $version,
		'Template'    => $template,
		'Status'      => $status,
		'Tags'        => $tags,
	);
}


/**
 * Bootstraps found themes for the frontend JS handler.
 *
 * @return array
 */
function wporg_themes_prepare_themes_for_js() {
	global $wp_query;

	include_once API_WPORGPATH . 'themes/info/1.0/class-themes-api.php';
	$api = new Themes_API( 'get_result' );
	$api->fields = array_merge( $api->fields, array(
		'description'  => true,
		'sections'     => false,
		'tested'       => true,
		'requires'     => true,
		'rating'       => true,
		'ratings'      => true,
		'downloaded'   => true,
		'downloadlink' => true,
		'last_updated' => true,
		'homepage'     => true,
		'tags'         => true,
		'num_ratings'  => true,
		'parent'       => true,
		'theme_url'    => true,
	) );

	$themes = array_map( array( $api, 'fill_theme' ), $wp_query->posts );
	$themes = array_map( 'wporg_themes_ajax_prepare_theme', $themes );

	$request = array();
	if ( get_query_var( 'browse' ) ) {
		$request['browse'] = get_query_var( 'browse' );
	} else if ( $wp_query->is_tag() ) {
		$request['tag'] = (array) explode( '+', get_query_var( 'tag' ) );
	}
	else if ( $wp_query->is_search() ) {
		$request['search'] = get_query_var( 's' );
	}
	else if ( $wp_query->is_author() ) {
		$request['author'] = get_user_by( 'id', get_query_var( 'author' ) )->user_nicename;
	}
	else if ( $wp_query->is_singular( 'repopackage' ) ) {
		$request['theme'] = get_query_var( 'name' );
	}

	return array(
		'themes'  => $themes,
		'request' => $request,
		'total'   => (int) $wp_query->found_posts,
	);
 }


/**
 * Removes Core's built-in query-themes handler, so we can safely add ours later on.
 */
function wporg_themes_remove_ajax_action() {
	remove_action( 'wp_ajax_query-themes', 'wp_ajax_query_themes', 1 );
}
add_action( 'wp_ajax_query-themes', 'wporg_themes_remove_ajax_action', -1 );

/**
 * A recreation of Core's implementation without capability check, since there is nothing to install.
 */
function wporg_themes_query_themes() {
	$request = wp_unslash( $_REQUEST['request'] );
	$request['fields'] = wp_parse_args( $request['fields'], array(
		'description'  => true,
		'sections'     => false,
		'tested'       => true,
		'requires'     => true,
		'rating'       => true,
		'ratings'      => true,
		'downloaded'   => true,
		'downloadlink' => true,
		'last_updated' => true,
		'homepage'     => true,
		'tags'         => true,
		'num_ratings'  => true,
		'parent'       => true,
		'theme_url'    => true,
	) );
	$args = wp_parse_args( array(
		'per_page' => 15,
	), $request);

	include_once API_WPORGPATH . 'themes/info/1.0/class-themes-api.php';
	$api = new Themes_API( 'query_themes', $args );
	$api = $api->response;

	if ( is_wp_error( $api ) ) {
		wp_send_json_error();
	}

	foreach ( $api->themes as $key => $theme ) {
		$api->themes[ $key ] = wporg_themes_ajax_prepare_theme( $theme );
	}

	wp_send_json_success( $api );
}
add_action( 'wp_ajax_query-themes',        'wporg_themes_query_themes' );
add_action( 'wp_ajax_nopriv_query-themes', 'wporg_themes_query_themes' );

function wporg_themes_theme_info() {
	$request = wp_unslash( $_REQUEST['request'] );
	$request['fields'] = wp_parse_args( $request['fields'], array(
		'description'  => true,
		'sections'     => false,
		'tested'       => true,
		'requires'     => true,
		'rating'       => true,
		'ratings'      => true,
		'downloaded'   => true,
		'downloadlink' => true,
		'last_updated' => true,
		'homepage'     => true,
		'tags'         => true,
		'num_ratings'  => true,
		'parent'       => true,
		'theme_url'    => true,
	) );

	include_once API_WPORGPATH . 'themes/info/1.0/class-themes-api.php';
	$api = new Themes_API( 'theme_information', $request );
	$api = $api->response;

	if ( empty( $api ) ) {
		wp_send_json_error();
	}

	$api = wporg_themes_ajax_prepare_theme( $api );

	wp_send_json_success( $api );
}
add_action( 'wp_ajax_theme-info',        'wporg_themes_theme_info' );
add_action( 'wp_ajax_nopriv_theme-info', 'wporg_themes_theme_info' );

function wporg_themes_ajax_prepare_theme( $theme ) {
	global $themes_allowedtags;
	if ( empty( $themes_allowedtags ) ) {
		$themes_allowedtags = array(
			'a'       => array( 'href' => array(), 'title' => array(), 'target' => array() ),
			'abbr'    => array( 'title' => array() ),
			'acronym' => array( 'title' => array() ),
			'code'    => array(),
			'pre'     => array(),
			'em'      => array(),
			'strong'  => array(),
			'div'     => array(),
			'p'       => array(),
			'ul'      => array(),
			'ol'      => array(),
			'li'      => array(),
			'h1'      => array(),
			'h2'      => array(),
			'h3'      => array(),
			'h4'      => array(),
			'h5'      => array(),
			'h6'      => array(),
			'img'     => array( 'src' => array(), 'class' => array(), 'alt' => array() ),
		);
	}

	$author        = get_user_by( 'slug', $theme->author );
	$theme->author = new StdClass;
	foreach ( array( 'user_nicename', 'display_name' ) as $property ) {
		$theme->author->$property = get_the_author_meta( $property, $author->ID );
	}

	$theme->name        = wp_kses( $theme->name, $themes_allowedtags );
	$theme->version     = wp_kses( $theme->version, $themes_allowedtags );
	$theme->description = wp_kses( $theme->description, $themes_allowedtags );
	$theme->downloaded  = number_format_i18n( $theme->downloaded );
	$theme->rating_text = sprintf( _n( '(based on %s rating)', '(based on %s ratings)', $theme->num_ratings ), number_format_i18n( $theme->num_ratings ) );
	$theme->num_ratings = number_format_i18n( $theme->num_ratings );
	$theme->preview_url = set_url_scheme( $theme->preview_url );

	if ( preg_match( '/screenshot.(jpg|jpeg|png|gif)/', $theme->screenshot_url, $match ) ) {
		$theme->screenshot_url = sprintf( 'https://i0.wp.com/themes.svn.wordpress.org/%1$s/%2$s/%3$s',
			$theme->slug,
			$theme->version,
			$match[0]
		);
	}

	return $theme;
}

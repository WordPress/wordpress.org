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
	global $wp_rewrite;

	// Setup the environment
	wporg_themes_init();

	// %postname% is required
	$wp_rewrite->set_permalink_structure( '/%postname%/' );

	// /tags/%slug% is required for tags
	$wp_rewrite->set_tag_base( '/tags' );

	/*
	 * Create the `commercial`, `getting-started` and `upload` pages
	 * These titles are not translated, as they're not displayed anywhere.
	 * The theme has specific templates for these slugs.
	 */
	foreach ( array( 'commercial', 'getting-started', 'upload' ) as $page_slug ) {
		if ( get_page_by_path( $page_slug ) ) {
			continue;
		}
		wp_insert_post( array(
			'post_type'   => 'page',
			'post_title'  => $page_slug,
			'post_name'   => $page_slug,
			'post_status' => 'publish'
		) );
	}

	// We require the WordPress.org Ratings plugin also be active
	if ( ! is_plugin_active( 'wporg-ratings/wporg-ratings.php' ) ) {
		activate_plugin( 'wporg-ratings/wporg-ratings.php' );
	}

	// Enable the WordPress.org Theme Repo Theme
	foreach ( wp_get_themes() as $theme ) {
		if ( $theme->get( 'Name' ) === 'WordPress.org Themes' ) {
			switch_theme( $theme->get_stylesheet() );
			break;
		}
	}

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
			'labels'      => array(
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
			'description' => __( 'A package', 'wporg-themes' ),
			'supports'    => array( 'title', 'editor', 'author', 'custom-fields', 'page-attributes' ),
			'taxonomies'  => array( 'category', 'post_tag', 'type' ),
			'public'      => false,
			'show_ui'     => true,
			'has_archive' => true,
			'rewrite'     => false,
			'menu_icon'   => 'dashicons-art',
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
			'exclude_from_search' => true,
			'rewrite'             => false,
			'menu_icon'           => 'dashicons-businessman',
		) );
	}

	// Add the browse/* views
	add_rewrite_tag( '%browse%', '(featured|popular|new|favorites)' );
	add_permastruct( 'browse', 'browse/%browse%' );

	if ( ! defined( 'WPORG_THEME_DIRECTORY_BLOGID' ) ) {
		define( 'WPORG_THEME_DIRECTORY_BLOGID', get_current_blog_id() );
	}
}
add_action( 'init', 'wporg_themes_init' );

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


add_action( 'add_meta_boxes', 'wporg_themes_author_metabox_override', 10, 2 );
function wporg_themes_author_metabox_override( $post_type, $post ) {
	if ( $post_type != 'repopackage' ) {
		return;
	}
	if ( post_type_supports($post_type, 'author') ) {
		if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) ) {
			remove_meta_box( 'authordiv', null, 'normal' );
			add_meta_box('authordiv', __('Author'), 'wporg_themes_post_author_meta_box', null, 'normal');
		}
	}
}

// Replacement for the core function post_author_meta_box
function wporg_themes_post_author_meta_box( $post ) {
	global $user_ID;
?>
<label class="screen-reader-text" for="post_author_override"><?php _e('Author'); ?></label>
<?php
/*
	wp_dropdown_users( array(
		'who' => 'authors',
		'name' => 'post_author_override',
		'selected' => empty($post->ID) ? $user_ID : $post->post_author,
		'include_selected' => true
	) );
*/
	$value = empty($post->ID) ? $user_ID : $post->post_author;
	echo "<input type='text' name='post_author_override' value='{$value}' />";
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

		// First time approval: Publish the theme.
		$post_args = array(
			'ID'          => $post_id,
			'post_status' => 'publish',
		);

		// Update post_time if it wasn't suspended before.
		if ( get_post_meta( $post_id, '_wporg_themes_reinstated', true ) ) {
			delete_post_meta( $post_id, '_wporg_themes_reinstated' );
		} else {
			$post_date = current_time( 'mysql' );

			$post_args['post_date']     = $post_date;
			$post_args['post_date_gmt'] = $post_date;
		}

		wp_update_post( $post_args );
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
	$content  = sprintf( __( 'Feedback for the %1$s theme is at %2$s', 'wporg-themes' ) . "\n\n--\n", $post->post_title, "https://themes.trac.wordpress.org/ticket/{$ticket_id}" );
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
function wporg_themes_get_themes_for_query() {
	static $result = null;
	if ( $result ) {
		return $result;
	}

	$request = array();
	if ( get_query_var( 'browse' ) ) {
		$request['browse'] = get_query_var( 'browse' );

		if ( 'favorites' === $request['browse'] ) {
			$request['user'] = wp_get_current_user()->user_login;
		}

	} else if ( get_query_var( 'tag' ) ) {
		$request['tag'] = (array) explode( '+', get_query_var( 'tag' ) );

	} else if ( get_query_var( 's' ) ) {
		$request['search'] = get_query_var( 's' );

	} else if ( get_query_var( 'author' ) ) {
		$request['author'] = get_user_by( 'id', get_query_var( 'author' ) )->user_nicename;

	} else if ( get_query_var( 'name' ) || get_query_var( 'pagename' ) ) {
		$request['theme'] = basename( get_query_var( 'name' ) ?: get_query_var( 'pagename' ) );
	}

	if ( get_query_var( 'paged' ) ) {
		$request['page'] = (int)get_query_var( 'paged' );
	}

	if ( empty( $request ) ) {
		$request['browse'] = 'featured';
	}

	$request['locale'] = get_locale();

	$request['fields'] = array(
		'description' => true,
		'sections' => false,
		'tested' => true,
		'requires' => true,
		'downloaded' => true,
		'downloadlink' => true,
		'last_updated' => true,
		'homepage' => true,
		'theme_url' => true,
		'parent' => true,
		'tags' => true,
		'rating' => true,
		'ratings' => true,
		'num_ratings' => true,
		'extended_author' => true,
		'photon_screenshots' => true,
	);

	$api_result = wporg_themes_query_api( 'query_themes', $request );

	unset( $request['fields'], $request['locale'] );

	return $result = array(
		'themes'  => $api_result->themes,
		'request' => $request,
		'total'   => (int) $api_result->info['results'],
		'pages'   => (int) $api_result->info['pages'],
	);
}
function wporg_themes_prepare_themes_for_js() {
	return wporg_themes_get_themes_for_query();
}

/**
 * Makes a query against api.wordpress.org/themes/info/1.0/ without making a HTTP call
 * Switches to the appropriate blog for the query.
 */
function wporg_themes_query_api( $method, $args = array() ) {
	include_once API_WPORGPATH . 'themes/info/1.0/class-themes-api.php';

	switch_to_blog( WPORG_THEME_DIRECTORY_BLOGID );
	$api = new Themes_API( $method, $args );
	restore_current_blog();

	return $api->response;
}


/**
 * Returns if the current theme is favourited by the current user.
 */
function wporg_themes_is_favourited( $theme_slug ) {
	return in_array( $theme_slug, wporg_themes_get_user_favorites() );
}

/**
 * Returns the current themes favorited by the user.
 *
 * @param int $user_id The user to get the favorites of. Default: current user
 *
 * @return array
 */
function wporg_themes_get_user_favorites( $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return array();
	}

	$favorites = get_user_meta( $user_id, 'theme_favorites', true );

	return $favorites ? array_values( $favorites ) : array();
}

/**
 * Sets current themes favorited by the user.
 *
 * @param array $favorites An array of theme slugs to mark as favorited.
 * @param int   $user_id   The user to get the favorites of. Default: current user
 *
 * @return bool
 */
function wporg_themes_set_user_favorites( $favorites, $user_id = 0 ) {
	if ( ! $user_id ) {
		$user_id = get_current_user_id();
	}
	if ( ! $user_id ) {
		return false;
	}

	return update_user_meta( $user_id, 'theme_favorites', (array) $favorites );
}

/**
 * Favorite a theme for the current user
 *
 * @param int $theme_slug The theme to favorite.
 *
 * @return bool|WP_Error
 */
function wporg_themes_add_favorite( $theme_slug ) {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'not_logged_in' );
	}

	$favorites = wporg_themes_get_user_favorites();
	if ( ! in_array( $theme_slug, $favorites ) ) {
		$favorites[] = $theme_slug;
		return wporg_themes_set_user_favorites( $favorites );
	}
	return true;
}

/**
 * Remove a favorited theme for the current user
 *
 * @param int $theme_slug The theme to favorite.
 *
 * @return bool|WP_Error
 */
function wporg_themes_remove_favorite( $theme_slug ) {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'not_logged_in' );
	}

	$favorites = wporg_themes_get_user_favorites();
	if ( in_array( $theme_slug, $favorites ) ) {
		unset( $favorites[ array_search( $theme_slug, $favorites, true ) ] );
		return wporg_themes_set_user_favorites( $favorites );
	}
	return true;
}

/**
 * Import theme strings to GlotPress on upload for previously-live themes.
 *
 * @param object  $theme      The WP_Theme instance of the uploaded theme.
 * @param WP_post $theme_post The WP_Post representing the theme.
 */
function wporg_themes_glotpress_import_on_update( $theme, $theme_post ) {
	$status = (array) get_post_meta( $theme_post->ID, '_status', true );
	if ( array_search( 'live', $status ) ) {
		// Previously live, import updated strings immediately.
		$version = $theme->get( 'Version' );
		wporg_themes_glotpress_import( $theme_post, $version );
	}
}
add_action( 'theme_upload', 'wporg_themes_glotpress_import_on_update', 100, 2 );

/**
 * Import theme strings to GlotPress on approval.
 *
 * @param WP_Post|int $theme_post The WP_Post (or post_id) representing the theme.
 * @param string      $version    The version of the theme to import.
 */
function wporg_themes_glotpress_import( $theme_post, $version ) {
	$theme_post = get_post( $theme_post );

	if ( ! $theme_post || ! $version ) {
		return;
	}

	$cmd = '/usr/local/bin/php ' . WPORGPATH . 'translate/bin/projects/add-wp-themes-project.php ' . escapeshellarg( $theme_post->post_name ) . ' ' . escapeshellarg( $version );

	shell_exec( $cmd );
}
add_action( 'wporg_themes_update_version_live', 'wporg_themes_glotpress_import', 100, 2 );


<?php
/*
 * Plugin Name: Theme Repository
 * Plugin URI: https://wordpress.org/themes/
 * Description: Transforms a WordPress site in The Official Theme Directory.
 * Version: 1.0
 * Author: wordpressdotorg
 * Author URI: http://wordpress.org/
 * Text Domain: wporg-themes
 * License: GPLv2
 * License URI: http://opensource.org/licenses/gpl-2.0.php
 */

// Load theme repo package.
include __DIR__ . '/class-wporg-themes-repo-package.php';

// Load uploader.
include __DIR__ . '/upload.php';

// Load Themes API adjustments.
include __DIR__ . '/themes-api.php';

// Load adjustments to the edit.php screen for repopackage posts.
include __DIR__ . '/admin-edit.php';

// Load the query modifications needed for the directory.
include __DIR__ . '/query-modifications.php';

// Load the GitHub API client.
include __DIR__ . '/lib/class-github.php';
include __DIR__ . '/lib/class-exec-with-logging.php';

// Load repo jobs.
include __DIR__ . '/jobs/class-manager.php';
include __DIR__ . '/jobs/class-trac-sync.php';
include __DIR__ . '/jobs/class-svn-import.php';
new WordPressdotorg\Theme_Directory\Jobs\Manager();

// Load the Rest API Endpoints.
include __DIR__ . '/rest-api.php';

define( 'WPORG_THEMES_DEFAULT_BROWSE', 'popular' );

define( 'WPORG_THEMES_E2E_REPO', 'WordPress/theme-review-e2e' );

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
			'post_type'    => 'page',
			'post_title'   => ucwords( str_replace( '-', ' ', $page_slug ) ),
			'post_name'    => $page_slug,
			'post_status'  => 'publish',
			'post_content' => ( 'upload' == $page_slug ? '[wporg-themes-upload]' : '' )
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
			'public'      => true,
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
	add_rewrite_tag( '%browse%', '([^/]+)' );
	add_permastruct( 'browse', 'browse/%browse%' );
	add_rewrite_tag( '%favorites_user%', '([^/]+)' );
	//add_permastruct( 'favorites_user', 'browse/favorites/%favorites_user%' ); // TODO: Implement in JS before enabling

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
 * The array of post types to be included in the sitemap.
 *
 * @param array $post_types List of included post types.
 * @return array
 */
function wporg_themes_sitemap_post_types( $post_types ) {
	$post_types[] = 'repopackage';

	return $post_types;
}
add_filter( 'jetpack_sitemap_post_types', 'wporg_themes_sitemap_post_types' );

/**
 * Disable the Jetpack Sitemap feature when running on rosetta.
 */
function wporg_themes_disable_sitemap_for_rosetta( $modules ) {
	if ( !empty( $GLOBALS['rosetta'] ) ) {
		if ( false !== ( $i = array_search( 'sitemaps', $modules ) ) ) {
			unset( $modules[$i] );
		}
	}

	return $modules;
}
add_filter( 'jetpack_active_modules', 'wporg_themes_disable_sitemap_for_rosetta' );

/**
 * Skip outdated themes in Jetpack Sitemaps.
 *
 * @param bool $skip If this post should be excluded from Sitemaps.
 * @param object $plugin_db_row A row from the wp_posts table.
 * @return bool
 */
function wporg_themes_jetpack_sitemap_skip_post( $skip, $theme_db_row ) {
	// If it's outdated, don't include in Jetpack Sitemap.
	if ( time() - strtotime( $theme_db_row->post_modified_gmt ) > 2 * YEAR_IN_SECONDS ) {
		$skip = true;
	}

	return $skip;
}
add_filter( 'jetpack_sitemap_skip_post', 'wporg_themes_jetpack_sitemap_skip_post', 10, 2 );

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

/**
 * Replacement for the Author meta box on theme pages
 */
add_action( 'add_meta_boxes', 'wporg_themes_author_metabox_override', 10, 2 );
function wporg_themes_author_metabox_override( $post_type, $post ) {
	if ( $post_type != 'repopackage' ) {
		return;
	}

	$post_type_object = get_post_type_object($post_type);
	if ( post_type_supports($post_type, 'author') ) {
		if ( is_super_admin() || current_user_can( $post_type_object->cap->edit_others_posts ) ) {
			remove_meta_box( 'authordiv', null, 'normal' );
			add_meta_box('authordiv', __('Author'), 'wporg_themes_post_author_meta_box', null, 'normal');
		}
	}
}

/**
 * Replacement for the core function post_author_meta_box on theme edit pages
 *
 * Uses javascript for username autocompletion and to adjust the hidden id field
 */
function wporg_themes_post_author_meta_box( $post ) {
	global $user_ID;
?>
<label class="screen-reader-text" for="post_author_override"><?php _e('Author'); ?></label>
<?php
	$value = empty($post->ID) ? $user_ID : $post->post_author;

	$user = new WP_User($value);

	echo "<input type='text' id='post_author_username' value='{$user->user_login}' />";
	echo "<input type='hidden' id='post_author_override' name='post_author_override' value='{$value}' />";
?>
	<script>
	jQuery( document ).ready( function( $ ) {
		$( "#post_author_username" ).autocomplete( {
			source: ajaxurl + '?action=author-lookup&_ajax_nonce=<?php echo wp_create_nonce( 'wporg_themes_author_lookup' ); ?>',
			minLength: 2,
			delay: 700,
			autoFocus: true,
			select: function( event, ui ) {
				$( "#post_author_override" ).val( ui.item.value );
				$( "#post_author_username" ).val( ui.item.label );
				return false;
			}
		}).keydown( function( event ) {
			if( event.keyCode == 13 ) {
				event.preventDefault();
				return false;
			}
		});
	});
	</script>
<?php
}

/**
 * Admin ajax function to lookup a username for author autocompletion on theme edit pages
 *
 * Note: nonce protected, only available to logged in users
 *
 * While this user search is a bit heavy because of the SQL to search the whole users table,
 * it's not one that we will actually run a lot. This only occurs when a theme directory admin
 * is changing the "author" of a theme. This is fairly rare. If the query causes too many issues,
 * then we can refine it to limit it more.
 */
function wporg_themes_author_lookup() {
	check_ajax_referer( 'wporg_themes_author_lookup' );
	$term = $_REQUEST['term'];
	$args = array(
		'search' => $term.'*',
		'search_columns' => array( 'user_login', 'user_nicename' ),
		'fields' => array( 'ID', 'user_login' ),
		'number' => 8,
		'blog_id' => 0, // ID zero here allows it to search all users, not just those with roles in the theme directory
	);
	$user_query = new WP_User_Query( $args );

	if ( $user_query->results ) {
		$resp = array();
		foreach ( $user_query->results as $result ) {
			$user['label'] = $result->user_login;
			$user['value'] = $result->ID;
			$resp[] = $user;
		}
		echo json_encode($resp);
	}
	exit;
}
add_action('wp_ajax_author-lookup', 'wporg_themes_author_lookup');


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
	$meta = get_post_meta( $post_id, '_status', true ) ?: array();

	$old_status = false;
	if ( isset( $meta[ $current_version ] ) ) {
		$old_status = $meta[ $current_version ];
	}

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
	if ( ! $post ) {
		// Should never happen.
		return;
	}

	// Update wp-themes.com with this version.
	wporg_themes_update_wpthemescom( $post->post_name, $version );

	// Update the post with this version's name, parent theme, description, and tags.
	$theme_data = wporg_themes_get_header_data( sprintf(
		'https://themes.svn.wordpress.org/%1$s/%2$s/style.css',
		$post->post_name,
		$version
	) );

	if ( $theme_data ) {

		// Find the parent theme for this version.
		$theme_parent_post_id = $post->post_parent;
		if ( ! $theme_data['Template'] ) {
			// No parent theme.
			$theme_parent_post_id = 0;

		} else if (
			$post->post_parent &&
			$theme_data['Template'] === get_post( $post->post_parent )->post_name
		) {
			// The post_parent field is currently set correctly, since that post_id matches the template header.
			$theme_parent_post_id = $post->post_parent;

		} else {
			// Theme headers say it has a parent, but we don't have the correct one set, search for it.
			$parent_theme = get_posts( array(
				'name'             => $theme_data['Template'],
				'post_type'        => 'repopackage',
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'orderby'          => 'ID',
				'suppress_filters' => false,
				'fields'           => 'ids',
			) );

			if ( $parent_theme ) {
				$theme_parent_post_id = $parent_theme[0];
			} else {
				// We don't host the theme? Temporary problem? Assume it's right for now.
			}
		}

		$theme_post_name = $post->post_title;
		// Allow theme titles to change in case or accent: `ThemeName` => `Themename` + `ThemeName` => `ThemèName`
		if ( $theme_post_name !== $theme_data['Name'] ) {
			// Theme name has been updated. Make sure it still sanitizes to the same post.
			$name_slugified = remove_accents( $theme_data['Name'] );
			$name_slugified = preg_replace( '/%[a-f0-9]{2}/i', '', $name_slugified );
			$name_slugified = sanitize_title_with_dashes( $name_slugified );

			if ( $name_slugified === $post->post_name ) {
				// The new name still ends up at the same post_name slug value, let them have it.
				$theme_post_name = $theme_data['Name'];
			}
		}

		// Filter the tags to those that exist on the site already.
		$tags = array_intersect(
			$theme_data['Tags'],
			get_terms( array(
				'hide_empty' => false,
				'taxonomy' => 'post_tag',
				'fields' => 'slugs'
			) )
		);

		wp_update_post( array(
			'ID'           => $post_id,
			'post_title'   => $theme_post_name,
			'post_content' => $theme_data['Description'],
			'post_parent'  => $theme_parent_post_id,
			'tags_input'   => $tags,
		) );

		// Refresh the $post object for notifications.
		$post = get_post( $post_id );
	}

	// Keep track of the last live version.
	update_post_meta( $post_id, '_last_live_version', get_post_meta( $post_id, '_live_version', true ) );

	// Update current version. Used to prioritize localized themes.
	update_post_meta( $post_id, '_live_version', $version );

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
	if (
		'publish' == $post->post_status ||
		'delist' == $post->post_status
	) {
		$subject = sprintf( __( '[WordPress Themes] %1$s %2$s is now live', 'wporg-themes' ), $post->post_title, $version );
		// Translators: 1: Theme version number; 2: Theme name; 3: Theme URL.
		$content = sprintf( __( 'Version %1$s of %2$s is now live at %3$s.', 'wporg-themes' ), $version, $post->post_title, "https://wordpress.org/themes/{$post->post_name}/" ) . "\n\n";

	} else {
		$subject = sprintf( __( '[WordPress Themes] %s has been approved!', 'wporg-themes' ), $post->post_title );
		// Translators: 1: Theme name; 2: Theme URL.
		$content = sprintf( __( 'Congratulations, your new theme %1$s is now available to the public at %2$s.', 'wporg-themes' ), $post->post_title, "https://wordpress.org/themes/{$post->post_name}/" ) . "\n\n";

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

	wp_mail( get_user_by( 'id', $post->post_author )->user_email, $subject, $content, 'From: "WordPress Theme Directory" <themes@wordpress.org>' );
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

	wp_mail( get_user_by( 'id', $post->post_author )->user_email, $subject, $content, 'From: "WordPress Theme Directory" <themes@wordpress.org>' );
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
		require_once __DIR__ . '/lib/class-trac.php';
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
	if ( empty( $wporg_webs ) || ! defined( 'THEME_PREVIEWS_SYNC_SECRET' ) ) {
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
	if ( empty( $wporg_webs ) || ! defined( 'THEME_PREVIEWS_SYNC_SECRET' ) ) {
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
 * @return array|false File headers, or false on failure.
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

	$context = stream_context_create( array(
		'http' => array(
			'user_agent' => 'WordPress.org Theme Directory'
		)
	) );

	$theme_data = file_get_contents( $theme_file, false, $context );
	if ( ! $theme_data ) {
		// Failure reading, or empty style.css file.
		return false;
	}

	$theme_data = str_replace( '\r', '\n', $theme_data );

	// Set defaults.
	$author_uri  = '';
	$template    = '';
	$version     = '';
	$status      = 'publish';
	$tags        = array();
	$author      = 'Anonymous';
	$name        = '';
	$theme_uri   = '';
	$description = '';

	if ( preg_match( '|^[ \t\/*#@]*Theme Name:(.*)$|mi', $theme_data, $m ) ) {
		$name = wp_strip_all_tags( trim( $m[1] ) );
	}

	if ( preg_match( '|^[ \t\/*#@]*Theme URI:(.*)$|mi', $theme_data, $m ) ) {
		$theme_uri = esc_url( trim( $m[1] ) );
	}

	if ( preg_match( '|^[ \t\/*#@]*Description:(.*)$|mi', $theme_data, $m ) ) {
		$description = wp_kses( trim( $m[1] ), $themes_allowed_tags );
	}

	if ( preg_match( '|^[ \t\/*#@]*Author:(.*)$|mi', $theme_data, $m ) ) {
		$author = wp_kses( trim( $m[1] ), $themes_allowed_tags );
	}

	if ( preg_match( '|^[ \t\/*#@]*Author URI:(.*)$|mi', $theme_data, $m ) ) {
		$author_uri = esc_url( trim( $m[1] ) );
	}

	if ( preg_match( '|^[ \t\/*#@]*Version:(.*)$|mi', $theme_data, $m ) ) {
		$version = wp_strip_all_tags( trim( $m[1] ) );
	}

	if ( preg_match( '|^[ \t\/*#@]*Template:(.*)$|mi', $theme_data, $m ) ) {
		$template = wp_strip_all_tags( trim( $m[1] ) );
	}

	if ( preg_match( '|^[ \t\/*#@]*Status:(.*)$|mi', $theme_data, $m ) ) {
		$status = wp_strip_all_tags( trim( $meta_key[1] ) );
	}

	if ( preg_match( '|^[ \t\/*#@]*Tags:(.*)$|mi', $theme_data, $m ) ) {
		$tags = array_map( 'trim', explode( ',', wp_strip_all_tags( trim( $m[1] ) ) ) );
	}

	return array(
		'Name'        => $name,
		'Title'       => $name,
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
		if ( defined( 'WPORG_IS_API' ) && WPORG_IS_API ) {
			$request['browse'] = 'featured';
		} else {
			$request['browse'] = 'popular';
		}
	}

	$request['locale'] = get_locale();

	$request['fields'] = array(
		'description' => true,
		'sections' => false,
		'tested' => true,
		'requires' => true,
		'downloaded' => false,
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
		'active_installs' => true,
		'requires' => true,
		'requires_php' => true,
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

function wporg_themes_theme_information( $slug ) {
	return wporg_themes_query_api( 'theme_information', array(
		'slug' => $slug,
		'fields' => array(
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
			'active_installs' => true,
			'requires' => true,
			'requires_php' => true,
		)
	) );
}

/**
 * Make a query against the api.wordpress.org/themes/info/ API
 * This function can be used to access the API without making an external HTTP call.
 *
 * NOTE: The API also calls this function.
 *
 * @param $method string The Method being called. Valid values: 'query_themes', 'theme_information', 'hot_tags', 'feature_list', and 'get_commercial_shops'
 * @param $args   array  The arguements for the call.
 * @param $format string The format to return the data in. Valid values: 'json', 'php', 'api_object', 'raw' (default)
 */
function wporg_themes_query_api( $method, $args = array(), $format = 'raw' ) {
	if ( ! class_exists( 'Themes_API' ) ) {
		include_once __DIR__ . '/class-themes-api.php';
	}

	$api = new Themes_API( $method, $args );

	return $api->get_result( $format );
}

/**
 * Extends repopackage searches to include theme slugs.
 *
 * @param string   $search   Search SQL for WHERE clause.
 * @param WP_Query $wp_query The current WP_Query object.
 * @return string
 */
function wporg_themes_search_slug( $search, $wp_query ) {
	if ( empty( $search ) || 'repopackage' !== $wp_query->query_vars['post_type'] || ! $wp_query->is_search() ) {
		return $search;
	}

	global $wpdb;
	$n = empty( $wp_query->query_vars['exact'] ) ? '%' : '';
	$search = $searchand = '';

	foreach ( $wp_query->query_vars['search_terms'] as $term ) {
		$like    = $n . $wpdb->esc_like( $term ) . $n;
		$search .= $wpdb->prepare( "{$searchand}(($wpdb->posts.post_title LIKE %s) OR ($wpdb->posts.post_name LIKE %s) OR ($wpdb->posts.post_content LIKE %s))", $like, $like, $like );
		$searchand = ' AND ';
	}

	if ( ! empty( $search ) ) {
		$search = " AND ({$search}) ";
		if ( ! is_user_logged_in() ) {
			$search .= " AND ($wpdb->posts.post_password = '') ";
		}
	}

	return $search;
}
add_filter( 'posts_search', 'wporg_themes_search_slug', 10, 2 );

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

	if ( $favorites ) {
		sort( $favorites );
	}

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
	// Newly loaded themes don't have a theme post.
	if ( ! $theme_post ) {
		return;
	}

	$status = (array) get_post_meta( $theme_post->ID, '_status', true );
	if ( array_search( 'live', $status ) ) {
		// Previously live, import updated strings immediately.
		$version = $theme->get( 'Version' );
		wporg_themes_glotpress_import( $theme_post, $version );
	}
}
add_action( 'theme_upload', 'wporg_themes_glotpress_import_on_update', 100, 2 );

/**
 * Hooks into the Suspend process to mark a theme as inactive in GlotPress.
 *
 * @param int  $post_id The post ID being suspended
 */
function wporg_themes_glotpress_mark_as_inactive_on_suspend( $post_id ) {
	$post = get_post( $post_id );
	if ( ! $post || 'repopackage' != $post->post_type ) {
		return;
	}

	wporg_themes_glotpress_import( $post, 'inactive' );
}
add_action( 'suspend_repopackage', 'wporg_themes_glotpress_mark_as_inactive_on_suspend' );

/**
 * Import theme strings to GlotPress on approval.
 *
 * @param WP_Post|int $theme_post The WP_Post (or post_id) representing the theme.
 * @param string      $version    The version of the theme to import, or 'inactive' to mark the translation project inactive.
 */
function wporg_themes_glotpress_import( $theme_post, $version ) {
	if ( ! defined( 'TRANSLATE_API_INTERNAL_BEARER_TOKEN' ) ) {
		return;
	}

	$theme_post = get_post( $theme_post );

	if ( ! $theme_post || ! $version ) {
		return;
	}

	wp_remote_post( 'https://translate.wordpress.org/wp-json/translate/v1/jobs', array(
		'body'       => json_encode(
			array(
				'timestamp'  => time() + 2 * 60,
				'recurrence' => 'once',
				'hook'       => 'wporg_translate_import_or_update_theme',
				'args'       => array(
					array(
						'theme'   => $theme_post->post_name,
						'version' => $version,
					),
				),
			)
		),
		'headers'    => array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . TRANSLATE_API_INTERNAL_BEARER_TOKEN,
		),
		'blocking'   => false,
		'user-agent' => 'WordPress.org Themes Import',
	) );
}
add_action( 'wporg_themes_update_version_live', 'wporg_themes_glotpress_import', 100, 2 );

/**
 * A Daily cron task to mark any themes which are now older than 2 years inactive in GlotPress.
 */
function wporg_themes_check_for_old_themes() {
	$too_old = strtotime( '-2 years' );

	$query = new WP_Query( array(
		'post_type' => 'repopackage',
		'post_status' => 'publish',
		'date_query' => array(
			'column' => 'post_modified',
			'before' => date( 'Y-m-d 00:00:00', $too_old ),
			'after' => date( 'Y-m-d 00:00:00', $too_old - DAY_IN_SECONDS ),
			'inclusive' => true
		)
	) );
	$posts = $query->get_posts();

	foreach ( $posts as $post ) {
		wporg_themes_glotpress_import( $post, 'inactive' );
	}
}
add_action( 'wporg_themes_check_for_old_themes', 'wporg_themes_check_for_old_themes' );

/**
 * Sets up the daily cron tasks for the Themes Directory
 */
function wporg_themes_maybe_schedule_daily_job() {
	if ( ! wp_next_scheduled( 'wporg_themes_check_for_old_themes' ) ) {
		wp_schedule_event( time(), 'daily', 'wporg_themes_check_for_old_themes' );
	}
}
add_action( 'admin_init', 'wporg_themes_maybe_schedule_daily_job' );

/**
 * Prints Open Graph meta data and meta tags for Twitter cards.
 */
function wporg_themes_add_meta_tags() {
	if ( ! is_single() ) {
		return;
	}

	$post = get_post();
	if ( ! $post ) {
		return;
	}

	$theme = wporg_themes_theme_information( $post->post_name );
	if ( ! $theme ) {
		return;
	}

	echo "<meta property='og:title' content='" . esc_attr( $theme->name ) . "' />\n";
	echo "<meta property='og:description' content='" . esc_attr( $theme->description ) . "' />\n";
	echo "<meta property='og:site_name' content='WordPress.org' />\n";
	echo "<meta property='og:type' content='website' />\n";
	echo "<meta property='og:url' content='" . esc_attr( get_permalink( $post->id ) ) . "' />\n";

	if ( $theme->screenshot_url ) {
		echo "<meta property='og:image' content='" . esc_attr( $theme->screenshot_url ) . "' />\n";
		echo "<meta name='twitter:card' content='summary_large_image'>\n";
		echo "<meta name='twitter:site' content='@WordPress'>\n";
		echo "<meta name='twitter:image' content='" . esc_attr( $theme->screenshot_url . '?w=560&amp;strip=all' ) . "' />\n";
	}
}
add_action( 'wp_head', 'wporg_themes_add_meta_tags' );

/**
 * SEO Tweaks
 *  - noindex outdated themes.
 *  - noindex filtered views.
 */
function wporg_themes_noindex_request( $noindex ) {
	if ( is_single() && ( $post = get_post() ) ) {
		$theme = wporg_themes_theme_information( $post->post_name );
		if ( $theme ) {
			// If it's outdated, noindex the theme.
			if ( time() - strtotime( $theme->last_updated ) > 2 * YEAR_IN_SECONDS ) {
				$noindex = true;
			}
		}

		if ( !$noindex && 'delist' === $post->post_status ) {
			$noindex = 'nosnippet';
		}
	}

	if ( is_tag() ) {
		$noindex = true;
	}

	return $noindex;
}
add_filter( 'wporg_noindex_request', 'wporg_themes_noindex_request' );

/**
 * Adds hreflang link attributes to theme pages.
 *
 * @link https://support.google.com/webmasters/answer/189077?hl=en Use hreflang for language and regional URLs
 * @link https://sites.google.com/site/webmasterhelpforum/en/faq-internationalisation FAQ: Internationalisation
 */
function wporg_themes_add_hreflang_link_attributes() {
	if ( is_404() ) {
		return;
	}

	if ( ! defined( 'GLOTPRESS_LOCALES_PATH' ) ) {
		return;
	}

	$path = wporg_themes_get_current_url( $path_only = true );
	if ( ! $path ) {
		return;
	}

	wp_cache_add_global_groups( array( 'locale-associations' ) );

	// Google doesn't have support for a whole lot of languages and throws errors about it,
	// so we exclude them, as we're otherwise outputting data that isn't used at all.
	$unsupported_languages = array(
		'arq',
		'art',
		'art-xemoji',
		'ary',
		'ast',
		'az-ir',
		'azb',
		'bcc',
		'ff-sn',
		'frp',
		'fuc',
		'fur',
		'haz',
		'ido',
		'io',
		'kab',
		'li',
		'li-nl',
		'lmo',
		'me',
		'me-me',
		'rhg',
		'rup',
		'sah',
		'sc-it',
		'scn',
		'skr',
		'srd',
		'szl',
		'tah',
		'twd',
		'ty-tj',
		'tzm',
	);

	// WARNING: for any changes below, check other uses of the `locale-assosciations` group as there's shared cache keys in use.
	if ( false === ( $sites = wp_cache_get( 'local-sites', 'locale-associations' ) ) ) {
		global $wpdb;

		$sites = $wpdb->get_results( 'SELECT locale, subdomain FROM wporg_locales', OBJECT_K );
		if ( ! $sites ) {
			return;
		}

		require_once GLOTPRESS_LOCALES_PATH;

		foreach ( $sites as $key => $site ) {
			$gp_locale = GP_Locales::by_field( 'wp_locale', $site->locale );
			if ( ! $gp_locale ) {
				unset( $sites[ $key ] );
				continue;
			}

			// Skip non-existing subdomains, e.g. 'de_CH_informal'.
			if ( false !== strpos( $site->subdomain, '_' ) ) {
				unset( $sites[ $key ] );
				continue;
			}

			// Skip unsupported locales.
			if ( in_array( $gp_locale->slug, $unsupported_languages ) ) {
				unset( $sites[ $key ] );
				continue;
			}

			$hreflang = false;

			// Note that Google only supports ISO 639-1 codes.
			if ( isset( $gp_locale->lang_code_iso_639_1 ) && isset( $gp_locale->country_code ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_1 . '-' . $gp_locale->country_code;
			} elseif ( isset( $gp_locale->lang_code_iso_639_1 ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_1;
			} elseif ( isset( $gp_locale->lang_code_iso_639_2 ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_2;
			} elseif ( isset( $gp_locale->lang_code_iso_639_3 ) ) {
				$hreflang = $gp_locale->lang_code_iso_639_3;
			}

			if ( $hreflang ) {
				$sites[ $key ]->hreflang = strtolower( $hreflang );
			} else {
				unset( $sites[ $key ] );
			}
		}

		// Add en_US to the list of sites.
		$sites['en_US'] = (object) array(
			'locale'    => 'en_US',
			'hreflang'  => 'en',
			'subdomain' => ''
		);

		// Add x-default to the list of sites.
		$sites['x-default'] = (object) array(
			'locale'    => 'x-default',
			'hreflang'  => 'x-default',
			'subdomain' => '',
		);

		uasort( $sites, function( $a, $b ) {
			return strcasecmp( $a->hreflang, $b->hreflang );
		} );

		wp_cache_set( 'local-sites', $sites, 'locale-associations' );
	}

	foreach ( $sites as $site ) {
		$url = sprintf(
			'https://%swordpress.org%s',
			$site->subdomain ? "{$site->subdomain}." : '',
			$path
		);

		printf(
			'<link rel="alternate" href="%s" hreflang="%s" />',
			esc_url( $url ),
			esc_attr( $site->hreflang )
		);
	}
	echo "\n";
}
add_action( 'wp_head', 'wporg_themes_add_hreflang_link_attributes' );

/**
 * Get the current front-end requested URL.
 */
function wporg_themes_get_current_url( $path_only = false ) {
	// Back-compat: TODO: Used by hreflang links.
	$link = \WordPressdotorg\SEO\Canonical\get_canonical_url();

	if ( $path_only && $link ) {
		$path = parse_url( $link, PHP_URL_PATH );
		if ( $query = parse_url( $link, PHP_URL_QUERY ) ) {
			$path .= '?' . $query;
		}

		return $path;
	}

	return $link;
}

/**
 * Filter the WordPress.org SEO plugin Canonical location to respect Theme Directory differences.
 */
function wporg_themes_canonical_url( $url ) {
	$browse = get_query_var( 'browse' );
	if ( ! $browse || ! is_string( $browse ) ) {
		return $url;
	}

	// The browse/% urls on the Theme directory are front-page-query alterations.
	$url = home_url( "browse/{$browse}/" );

	if ( WPORG_THEMES_DEFAULT_BROWSE === $browse ) {
		$url = home_url( '/' );
	}

	return $url;
}
add_filter( 'wporg_canonical_url', 'wporg_themes_canonical_url' );

// Theme Directory doesn't support pagination.
add_filter( 'wporg_rel_next_pages', '__return_zero' );

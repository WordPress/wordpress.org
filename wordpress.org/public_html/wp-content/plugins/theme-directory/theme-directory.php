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

register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

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
			'public'              => true,
			'show_in_nav_menus'   => false,
			'exclude_from_search' => true,
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
 * Capability mapping for custom caps.
 *
 * @param array  $caps    Returns the user's actual capabilities.
 * @param string $cap     Capability name.
 * @param int    $user_id The user ID.
 * @param array  $args    Adds the context to the cap. Typically the object ID.
 * @return array
 */
function wporg_themes_map_meta_cap( $caps, $cap, $user_id, $args ) {
	switch ( $cap ) {
		case 'delete_categories':
		case 'edit_categories':
		case 'manage_categories':

			if ( ! is_super_admin() ) {
				$caps[] = 'do_not_allow';
			}
			break;
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'wporg_themes_map_meta_cap', 10, 4 );

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
		wp_publish_post( $post_id );
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

/* REPOPACKAGE EDITOR ENHANCEMENTS */

/**
 * Use theme screen shot for post thumbnails.
 *
 * @param string $html
 * @param int    $post_id
 * @return string
 */
function wporg_themes_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size ) {
	$post = get_post( $post_id );
	if ( 'repopackage' == $post->post_type ) {
		$theme = new WPORG_Themes_Repo_Package( $post );
		$src   = add_query_arg( array( 'w' => $size, 'strip' => 'all' ), $theme->screenshot_url() );

		$html = '<img src="' . esc_url( $src ) . '"/>';
	}

	return $html;
}
add_filter( 'post_thumbnail_html', 'wporg_themes_post_thumbnail_html', 10, 5 );

/**
 * Prevents repopackages from being deleted.
 *
 * @param int $post_id
 */
function wporg_theme_no_delete_repopackage( $post_id ) {
	if ( 'repopackage' == get_post( $post_id )->post_type ) {
		wp_die( __( 'Repopackages can not be deleted.', 'wporg-themes' ), '', array(
			'back_link' => true,
		) );
	}
}
add_filter( 'before_delete_post', 'wporg_theme_no_delete_repopackage' );

/**
 * Better view in the Packages screen.
 *
 * @param array $columns
 * @return array
 */
function wporg_themes_repopackage_columns( $columns ) {
	$columns = array_merge( $columns, array(
		'version'    => __( 'Version', 'wporg-themes' ),
		'theme-url'  => __( 'Theme URL', 'wporg-themes' ),
		'author-url' => __( 'Author URL', 'wporg-themes' ),
		'ticket'     => __( 'Ticket ID', 'wporg-themes' ),
	) );
	unset( $columns['categories'] );

	return $columns;
}
add_filter( 'manage_repopackage_posts_columns', 'wporg_themes_repopackage_columns' );

/**
 * Custom columns for the admin screen.
 *
 * @param string $column
 * @param int    $post_id
 */
function wporg_themes_repopackage_custom_columns( $column, $post_id ) {
	$theme = new WPORG_Themes_Repo_Package( $post_id );

	switch ( $column ) {
		case 'ticket':
			if ( $theme->ticket ) {
				printf( '<a href="%1$s">%2$s</a>', esc_url( 'https://themes.trac.wordpress.org/ticket/' . $theme->ticket ), '#' . $theme->ticket );
			}
			break;
		case 'theme-url':
		case 'author-url':
			echo make_clickable( $theme->$column );
			break;
		default:
			echo $theme->$column;
	}
}
add_action( 'manage_repopackage_posts_custom_column', 'wporg_themes_repopackage_custom_columns', 10, 2 );

/**
 * Meta box to choose which version is live.
 */
function wporg_themes_add_meta_box() {
	add_meta_box(
		'wporg_themes_versions',
		__( 'Theme Versions', 'wporg-themes' ),
		'wporg_themes_meta_box_callback',
		'repopackage',
		'side',
		'high'
	);
}
add_action( 'add_meta_boxes', 'wporg_themes_add_meta_box' );

/**
 * Displays the content of the `_status` meta box.
 *
 * @param WP_Post $post
 */
function wporg_themes_meta_box_callback( $post ) {
	$versions = get_post_meta( $post->ID, '_status', true );

	if ( empty( $versions ) ) {
		return;
	}

	// Add an nonce field so we can check for it later.
	wp_nonce_field( 'wporg_themes_meta_box', 'wporg_themes_meta_box_nonce' );

	foreach ( $versions as $version => $status ) :
		?>
		<p><?php echo $version; ?> -
			<select name="wporg_themes_status[<?php echo base64_encode( $version ); // base64 because version numbers don't work so well as parts of keys ?>]">
				<option value="new" <?php selected( $status, 'new' ); ?>><?php esc_html_e( 'New', 'wporg-themes' ); ?></option>
				<option value="live" <?php selected( $status, 'live' ); ?>><?php esc_html_e( 'Live', 'wporg-themes' ); ?></option>
				<option value="old" <?php selected( $status, 'old' ); ?>><?php esc_html_e( 'Old', 'wporg-themes' ); ?></option>
			</select>
		</p>
	<?php
	endforeach;
}

/**
 * Sanitizes and saves meta box settings.
 *
 * @param int $post_id
 */
function wporg_themes_save_meta_box_data( $post_id ) {
	// All the safety checks.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! isset( $_POST['wporg_themes_meta_box_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( $_POST['wporg_themes_meta_box_nonce'], 'wporg_themes_meta_box' ) ) {
		return;
	}
	// TODO should this be a post type specific capability?
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$new_status = array();
	foreach ( $_POST['wporg_themes_status'] as $version => $status ) {
		// We could check of the passed status is valid, but wporg_themes_update_version_status() handles that beautifully.
		$new_status[ base64_decode( $version ) ] = $status;
	}
	uksort( $new_status, 'version_compare' );

	// Update the statuses.
	foreach ( $new_status as $version => $status ) {
		wporg_themes_update_version_status( $post_id, $version, $status );
	}
}
add_action( 'save_post', 'wporg_themes_save_meta_box_data' );

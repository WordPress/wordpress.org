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

register_activation_hook( __FILE__, 'flush_rewrite_rules' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/**
 * Initialize.
 */
function wporg_themes_init() {
	load_plugin_textdomain( 'wporg-themes' );

	$labels = array(
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
	);

	$args = array(
		'labels'              => $labels,
		'hierarchical'        => false,
		'description'         => __( 'A package', 'wporg-themes' ),
		'supports'            => array( 'title', 'editor', 'author', 'custom-fields', 'page-attributes' ),
		'taxonomies'          => array( 'category', 'post_tag', 'type' ),
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => false,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'has_archive'         => true,
		'query_var'           => true,
		'can_export'          => true,
		'rewrite'             => false,
		'capability_type'     => 'post',
	);

	// This is the base generic type for repo plugins.
	if ( ! post_type_exists( 'repopackage' ) ) {
		register_post_type( 'repopackage', $args );
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
	if ( is_admin() || in_array( $wp_query->get( 'pagename' ), array( 'upload', 'commercial', 'getting-started' ) ) || 'nav_menu_item' == $wp_query->get( 'post_type' ) ) {
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
				add_filter( 'posts_clauses',  'wporg_themes_popular_posts_clauses' );
				break;

			case 'new':
				// Nothing to do here.
				break;
		}
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
 * Returns the status of a theme's version.
 *
 * @param int          $post_id Post ID.
 * @param string       $version The theme version to get the status for.
 * @return bool|string The version-specific meta value or False on failure.
 */
function wporg_themes_get_version_status( $post_id, $version ) {
	$status = false;
	$meta   = (array) get_post_meta( $post_id, '_status', true );

	if ( ! empty( $meta[ $version ] ) ) {
		$status = $meta[ $version ];
	}

	return $status;
}

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
	$meta = get_post_meta( $post_id, '_status', true );

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

	return update_post_meta( $post_id, '_status', $meta );
}

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
		$src   = add_query_arg( array( 'w' => $size, 'strip' => 'all' ), $theme->screen_shot_url() );

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

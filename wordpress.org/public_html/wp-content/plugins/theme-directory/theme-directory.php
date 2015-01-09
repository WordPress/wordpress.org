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

/**
 * Things to change on activation.
 */
function wporg_themes_activate() {

	// Give Editors the ability to approve a theme.
	// Can be split to different roles in the future.
	$admin = get_role( 'editor' );
	$admin->add_cap( 'approve_themes' );
}
register_activation_hook( __FILE__, 'wporg_themes_activate' );

/**
 * Things to change on deactivation.
 */
function wporg_themes_deactivate() {
	$admin = get_role( 'editor' );
	$admin->remove_cap( 'approve_themes' );
}
register_deactivation_hook( __FILE__, 'wporg_themes_deactivate' );

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
		'rewrite'             => true,
		'capability_type'     => 'post',
	);

	// This is the base generic type for repo plugins.
	if ( ! post_type_exists( 'repopackage' ) ) {
		register_post_type( 'repopackage', $args );
	}
}
add_action( 'init', 'wporg_themes_init' );

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
		case 'approve_theme':
			$caps[] = 'approve_themes';
			unset( $caps[ array_search( $cap, $caps ) ] );

			// Don't allow Admins to approve their own themes.
			if ( isset( $args[0] ) && get_post( $args[0] )->post_author == $user_id ) {
				$caps[] = 'do_not_allow';
			}
			break;
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'wporg_themes_map_meta_cap', 10, 4 );

/**
 * Grabs theme review results from Trac and updates theme version number statuses accordingly.
 *
 * We're only checking for new results when on the edit screen for themes, and only for updates since the last time we
 * checked.
 */
function wporg_themes_sync_review_results() {
	if ( 'repopackage' != $GLOBALS['typenow'] || ! defined( 'THEME_TRACBOT_PASSWORD' ) ) {
		return;
	}

	if ( ! class_exists( 'Trac' ) ) {
		require_once ABSPATH . WPINC . '/class-IXR.php';
		require_once ABSPATH . WPINC . '/class-wp-http-ixr-client.php';
		require_once WPORGPATH . 'bb-theme/themes/lib/class-trac.php';
	}

	$trac         = new Trac( 'themetracbot', THEME_TRACBOT_PASSWORD, 'https://themes.trac.wordpress.org/login/xmlrpc' );
	$last_request = get_option( 'wporg-themes-last-trac-sync', strtotime( '-2 days' ) );

	foreach ( array( 'live', 'not-approved' ) as $resolution ) {
		// Get array of tickets.
		$tickets = (array) $trac->ticket_query( add_query_arg( array(
			'status'     => 'closed',
			'resolution' => $resolution,
			'order'      => 'changetime',
			'changetime' => date( 'c', $last_request ),
			'desc'       => 1,
		) ) );

		foreach ( $tickets as $ticket_id ) {
			// Get the theme associated with that ticket.
			$post_ids = get_posts( array(
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'value'   => $ticket_id,
						'compare' => 'IN',
					),
				),
				'post_status'    => 'any',
				'posts_per_page' => - 1,
				'post_type'      => 'repopackage',
			) );

			if ( empty( $post_ids ) ) {
				continue;
			}

			$post_id = array_pop( $post_ids );
			$version = array_search( $ticket_id, (array) get_post_meta( $post_id, '_ticket_id', true ) );
			if ( ! $version ) {
				continue;
			}

			// Bail if the the theme is not new.
			if ( 'new' != wporg_themes_get_version_status( $post_id, $version ) ) {
				continue;
			}

			// Approved themes:
			if ( 'live' == $resolution ) {
				wporg_themes_update_version_status( $post_id, $version, 'pending' );

			// Unapproved themes:
			} else {
				wporg_themes_update_version_status( $post_id, $version, 'old' );
			}
		}
	}

	update_option( 'wporg-themes-last-trac-sync', time() );
}
add_action( 'load-edit.php', 'wporg_themes_sync_review_results' );

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

		case 'pending':
			// Discard all previous pending versions.
			foreach ( array_keys( $meta, $new_status ) as $version ) {
				if ( version_compare( $version, $current_version, '<' ) ) {
					$meta[ $version ] = 'old';
				}
			}

			// Mark the current version as pending.
			$meta[ $current_version ] = $new_status;

			// Register the pending version.
			update_post_meta( $post_id, '_has_pending_version', $current_version );
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
function wporg_themes_post_thumbnail_html( $html, $post_id ) {
	$post = get_post( $post_id );
	if ( 'repopackage' == $post->post_type ) {
		$theme = new WPORG_Themes_Repo_Package( $post );
		// no size because we only have one (unknown) image size, so the theme needs to size with css
		$html = '<img src="' . $theme->screenshot_url() . '"/>';
	}

	return $html;
}
add_filter( 'post_thumbnail_html', 'wporg_themes_post_thumbnail_html', 10, 2 );

/**
 * Filters repopackages to only contain themes that are ready to be approved, when on the corresponding view.
 *
 * @param WP_Query $wp_query
 * @return WP_Query
 */
function wporg_themes_filter_repopackages( $wp_query ) {
	if ( 'edit.php' == $GLOBALS['pagenow'] && 'repopackage' == $GLOBALS['typenow'] && current_user_can( 'approve_theme' ) && ! empty( $_REQUEST['meta_key'] ) && '_has_pending_version' == $_REQUEST['meta_key'] ) {
		$wp_query->set( 'meta_key', '_has_pending_version' );
	}

	return $wp_query;
}
add_filter( 'pre_get_posts', 'wporg_themes_filter_repopackages' );

/**
 * Adds a view for Themes awaiting approval.
 *
 * @param array $views
 * @return array
 */
function wporg_themes_status_pending_view( $views ) {
	$total_posts = count( get_posts(array(
		'fields'         => 'ids',
		'meta_key'       => '_has_pending_version',
		'post_status'    => 'any',
		'posts_per_page' => - 1,
		'post_type'      => 'repopackage',
	) ) );

	if ( current_user_can( 'approve_themes' ) && ! empty( $total_posts ) ) {
		$class = '';
		if ( isset( $_REQUEST['meta_key'] ) && '_has_pending_version' == $_REQUEST['meta_key'] ) {
			$class = ' class="current"';
		}

		$views['status_pending'] = "<a href='edit.php?post_type=repopackage&post_status=any&meta_key=_has_pending_version'$class>" . sprintf( _nx( 'Pending Approval <span class="count">(%s)</span>', 'Pending Approval <span class="count">(%s)</span>', $total_posts, 'posts', 'wporg-themes' ), number_format_i18n( $total_posts ) ) . '</a>';
	}

	return $views;
}
add_filter( 'views_edit-repopackage', 'wporg_themes_status_pending_view' );

/**
 * Adds an action to approve themes.
 *
 * @param array   $actions
 * @param WP_Post $post
 * @return array
 */
function wporg_themes_post_row_actions( $actions, $post ) {
	if ( 'repopackage' == $post->post_type ) {
		$before = array_slice( $actions, 0, - 1, true );
		$after  = array_slice( $actions, - 1, 1, true );

		if ( current_user_can( 'approve_theme', $post->ID ) && isset( $_REQUEST['meta_key'] ) && '_has_pending_version' == $_REQUEST['meta_key'] ) {
			$before['approve_theme'] = sprintf( '<a class="submit-approve_theme" title="%1$s" href="%2$s">%3$s</a>', esc_attr__( 'Approve this item', 'wporg-themes' ), esc_url( wporg_themes_get_approve_url( $post ) ), __( 'Approve', 'wporg-themes' ) );
		}

		$actions = array_merge( $before, $after );
	}

	return $actions;
}
add_filter( 'post_row_actions', 'wporg_themes_post_row_actions', 10, 2 );

/**
 * Action link to approve a theme version.
 *
 * @param WP_Post $post
 * @return string URL
 */
function wporg_themes_get_approve_url( $post ) {
	return wp_nonce_url( add_query_arg( 'action', 'approve', admin_url( sprintf( get_post_type_object( $post->post_type )->_edit_link, $post->ID ) ) ), "approve-post_{$post->ID}" );
}

/**
 * Approve a theme version.
 */
function wporg_themes_approve_theme() {
	if ( isset( $_GET['post'] ) ) {
		$post_id = (int) $_GET['post'];
	}

	if ( ! $post_id ) {
		wp_redirect( admin_url( 'edit.php' ) );
		exit();
	}

	check_admin_referer( 'approve-post_' . $post_id );

	$version = get_post_meta( $post_id, '_has_pending_version', true );

	if ( ! $version ) {
		wp_die( __( 'This item has already been approved.', 'wporg-themes' ) );
	}

	$post = get_post( $post_id );

	if ( ! get_post_type_object( $post->post_type ) ) {
		wp_die( __( 'Unknown post type.' ) );
	}

	if ( ! current_user_can( 'approve_theme', $post_id ) || 'repopackage' != $post->post_type ) {
		wp_die( __( 'You are not allowed to approve this item.', 'wporg-themes' ) );
	}

	wporg_themes_update_version_status( $post_id, $version, 'live' );
	delete_post_meta( $post_id, '_has_pending_version' );

	$ticket_id = get_post_meta( $post_id, '_trac_ticket_' . $version, true );
	if ( 'publish' == $post->post_status ) {
		$email_subject  = sprintf( __( '[WordPress Themes] %1$s %2$s is now live', 'wporg-themes' ), $post->post_title, $version );
		$email_content  = sprintf( __( 'Version %1$s of %2$s is now live at https://wordpress.org/themes/%3$s.', 'wporg-themes' ), $version, $post->post_title, $post->post_name ) . "\n\n";
		$email_content .= sprintf( __( 'Any feedback items are at %s.', 'wporg-themes' ), "https://themes.trac.wordpress.org/ticket/$ticket_id" ) . "\n\n--\n";
		$email_content .= __( 'The WordPress.org Themes Team', 'wporg' ) . "\n";
		$email_content .= 'theme-reviewers@lists.wordpress.org';

	} else {
		$email_subject  = sprintf( __( '[WordPress Themes] %s has been approved!', 'wporg-themes' ), $post->post_title );
		$email_content  = sprintf( __( 'Congratulations, your new theme %1$s is now available to the public at https://wordpress.org/themes/%2$s.', 'wporg-themes' ), $post->post_title, $post->post_name ) . "\n\n";
		$email_content .= sprintf( __( 'Any feedback items are at %s.', 'wporg-themes' ), "https://themes.trac.wordpress.org/ticket/$ticket_id" ) . "\n\n--\n";
		$email_content .= __( 'The WordPress.org Themes Team', 'wporg' ) . "\n";
		$email_content .= 'theme-reviewers@lists.wordpress.org';
	}

	wp_mail( get_user_by( 'id', $post->post_author )->user_email, $email_subject, $email_content, 'From: theme-reviewers@lists.wordpress.org' );


	// Update the theme's post status.
	wp_update_post( array(
		'ID'          => $post_id,
		'post_status' => 'publish',
	) );

	wp_redirect( add_query_arg( 'approved', 1, remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'ids' ), wp_get_referer() ) ) );
	exit();
}
add_filter( 'admin_action_approve', 'wporg_themes_approve_theme' );

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
 * Give the user feedback after approving a theme.
 */
function wporg_themes_admin_notices() {
	if ( ! empty( $_GET['approved'] ) ) {
		$approved = absint( $_GET['approved'] );
		$message  = _n( '%s theme approved.', '%s themes approved.', $approved );
		add_settings_error( 'wporg_themes', 'approved', sprintf( $message, $approved ), 'updated' );
	}

	// Display admin notices, if any.
	settings_errors( 'wporg_themes' );
}
add_filter( 'admin_notices', 'wporg_themes_admin_notices' );

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
				<option value="pending" <?php selected( $status, 'pending' ); ?>><?php esc_html_e( 'Pending', 'wporg-themes' ); ?></option>
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

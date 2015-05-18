<?php
/**
 * Adjustments to the edit.php screen for repopackage posts.
 */

/**
 * Adds custom capabilities on plugin activation.
 */
function wporg_themes_add_caps() {

	// Give Editors and higher the ability to suspend and reinstate a theme.
	foreach ( array( 'administrator', 'author', 'editor' ) as $role ) {
		$wp_roles = get_role( $role );

		$wp_roles->add_cap( 'suspend_themes' );
		$wp_roles->add_cap( 'reinstate_themes' );
	}
}
add_action( 'wporg_themes_activation', 'wporg_themes_add_caps' );

/**
 * Removes custom capabilities on plugin deactivation.
 */
function wporg_themes_remove_caps() {
	foreach ( array( 'administrator', 'author', 'editor' ) as $role ) {
		$wp_roles = get_role( $role );

		$wp_roles->remove_cap( 'suspend_themes' );
		$wp_roles->remove_cap( 'reinstate_themes' );
	}
}
add_action( 'wporg_themes_deactivation', 'wporg_themes_remove_caps' );

/**
 * Registers custom post status for suspended themes.
 */
function wporg_themes_post_status() {

	// Themes can be "suspended" to hide them.
	register_post_status( 'suspend', array(
		'label'               => __( 'Suspended', 'wporg-themes' ),
		'protected'           => true,
		'exclude_from_search' => true,
		'label_count'         => _n_noop( 'Suspended <span class="count">(%s)</span>', 'Suspended <span class="count">(%s)</span>', 'wporg-themes' ),
	) );
}
add_action( 'init', 'wporg_themes_post_status' );

/**
 * Extends repopackage searches in wp-admin to include theme slugs.
 *
 * @param string   $search   Search SQL for WHERE clause.
 * @param WP_Query $wp_query The current WP_Query object.
 * @return string
 */
function wporg_themes_search_slug( $search, $wp_query ) {
	if ( ! is_admin() || 'repopackage' !== $wp_query->query_vars['post_type'] ) {
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
 * Capability mapping for custom caps.
 *
 * @param array  $caps Returns the user's actual capabilities.
 * @param string $cap  Capability name.
 * @return array
 */
function wporg_themes_map_meta_cap( $caps, $cap ) {
	switch ( $cap ) {
		case 'delete_categories':
		case 'edit_categories':
		case 'manage_categories':

			if ( ! is_super_admin() ) {
				$caps[] = 'do_not_allow';
			}
			break;

		case 'suspend_theme':
			$caps[] = 'suspend_themes';
			unset( $caps[ array_search( $cap, $caps ) ] );
			break;

		case 'reinstate_theme':
			$caps[] = 'reinstate_themes';
			unset( $caps[ array_search( $cap, $caps ) ] );
			break;
	}

	return $caps;
}
add_filter( 'map_meta_cap', 'wporg_themes_map_meta_cap', 10, 2 );

/**
 * Adds suspend and reinstate actions.
 *
 * @param array   $actions
 * @param WP_Post $post
 * @return array
 */
function wporg_themes_post_row_actions( $actions, $post ) {
	if ( 'repopackage' == $post->post_type ) {
		$before = array_slice( $actions, 0, - 1, true );
		$after  = array_slice( $actions, - 1, 1, true );
		$custom = array();

		if ( current_user_can( 'reinstate_theme', $post->ID ) && 'suspend' == $post->post_status ) {
			$custom = array( 'reinstate' => sprintf( '<a class="submit-reinstate_theme" title="%1$s" href="%2$s">%3$s</a>', esc_attr__( 'Reinstate this item', 'wporg-themes' ), esc_url( wporg_themes_get_reinstate_url( $post ) ), __( 'Reinstate', 'wporg-themes' ) ) );
		}
		elseif ( current_user_can( 'suspend_theme', $post->ID ) ) {
			$custom = array( 'suspend' => sprintf( '<a class="submit-suspend_theme" title="%1$s" href="%2$s">%3$s</a>', esc_attr__( 'Suspend this item', 'wporg-themes' ), esc_url( wporg_themes_get_suspend_url( $post ) ), __( 'Suspend', 'wporg-themes' ) ) );
		}

		$actions = array_merge( $before, $custom, $after );
	}

	return $actions;
}
add_filter( 'post_row_actions', 'wporg_themes_post_row_actions', 10, 2 );

/**
 * Adds a suspend/reinstate link to the Publish meta box in the theme editor.
 */
function wporg_themes_post_submitbox_misc_actions() {
	$post = get_post();
	if ( ! $post || 'repopackage' != $post->post_type ) {
		return;
	}

	$links = array();

	if ( current_user_can( 'suspend_theme', $post->ID ) && 'suspend' != $post->post_status ) {
		$links[] = sprintf( '<a class="submit-suspend_theme submitdelete" title="%1$s" href="%2$s">%3$s</a>', esc_attr__( 'Suspend this item', 'wporg-themes' ), esc_url( wporg_themes_get_suspend_url( $post ) ), __( 'Suspend', 'wporg-themes' ) );
	}
	elseif ( current_user_can( 'reinstate_theme', $post->ID ) && 'suspend' == $post->post_status ) {
		$links[] = sprintf( '<a class="submit-reinstate_theme" title="%1$s" href="%2$s">%3$s</a>', esc_attr__( 'Suspend this item', 'wporg-themes' ), esc_url( wporg_themes_get_reinstate_url( $post ) ), __( 'Reinstate', 'wporg-themes' ) );
	}

	if ( ! empty( $links ) ) {
		echo '<div class="misc-pub-section">' . implode( ' | ', $links ) . '</div>';
	}
}
add_action( 'post_submitbox_misc_actions', 'wporg_themes_post_submitbox_misc_actions' );

/**
 * Action link to suspend a theme.
 *
 * @param WP_Post $post
 * @return string URL
 */
function wporg_themes_get_suspend_url( $post ) {
	return wp_nonce_url( add_query_arg( 'action', 'suspend', admin_url( sprintf( get_post_type_object( $post->post_type )->_edit_link, $post->ID ) ) ), "suspend-post_{$post->ID}" );
}

/**
 * Action link to reinstate a theme.
 *
 * @param WP_Post $post
 * @return string URL
 */
function wporg_themes_get_reinstate_url( $post ) {
	return wp_nonce_url( add_query_arg( 'action', 'reinstate', admin_url( sprintf( get_post_type_object( $post->post_type )->_edit_link, $post->ID ) ) ), "reinstate-post_{$post->ID}" );
}

/**
 * Suspend a theme.
 */
function wporg_themes_suspend_theme() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

	if ( ! $post_id ) {
		wp_redirect( admin_url( 'edit.php' ) );
		exit();
	}

	check_admin_referer( 'suspend-post_' . $post_id );

	$post = get_post( $post_id );

	if ( 'suspend' == $post->post_status ) {
		wp_die( __( 'This item has already been suspended.', 'wporg-themes' ) );
	}

	if ( ! get_post_type_object( $post->post_type ) ) {
		wp_die( __( 'Unknown post type.', 'wporg-themes' ) );
	}

	if ( ! current_user_can( 'suspend_theme', $post_id ) || 'repopackage' != $post->post_type ) {
		wp_die( __( 'You are not allowed to suspend this item.', 'wporg-themes' ) );
	}

	wp_update_post( array(
		'ID'          => $post_id,
		'post_status' => 'suspend',
	) );

	wporg_themes_remove_wpthemescom( $post->post_name );

	wp_redirect( add_query_arg( 'suspended', 1, remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'ids', 'reinstated' ), wp_get_referer() ) ) );
	exit();
}
add_filter( 'admin_action_suspend', 'wporg_themes_suspend_theme' );

/**
 * Reinstate a theme.
 */
function wporg_themes_reinstate_theme() {
	$post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;

	if ( ! $post_id ) {
		wp_redirect( admin_url( 'edit.php' ) );
		exit();
	}

	check_admin_referer( 'reinstate-post_' . $post_id );

	$post = get_post( $post_id );

	if ( 'suspend' != $post->post_status ) {
		wp_die( __( 'This item has already been reinstated.', 'wporg-themes' ) );
	}

	if ( ! get_post_type_object( $post->post_type ) ) {
		wp_die( __( 'Unknown post type.', 'wporg-themes' ) );
	}

	if ( ! current_user_can( 'reinstate_theme', $post_id ) || 'repopackage' != $post->post_type ) {
		wp_die( __( 'You are not allowed to reinstate this item.', 'wporg-themes' ) );
	}

	wp_update_post( array(
		'ID'          => $post_id,
		'post_status' => 'draft',
	) );

	/*
	 * Mark it as reinstated, so the post date doesn't get overwritten when it's
	 * published again.
	 */
	add_post_meta( $post_id, '_wporg_themes_reinstated', true );

	wp_redirect( add_query_arg( 'reinstated', 1, remove_query_arg( array( 'trashed', 'untrashed', 'deleted', 'ids', 'suspended' ), wp_get_referer() ) ) );
	exit();
}
add_filter( 'admin_action_reinstate', 'wporg_themes_reinstate_theme' );

/**
 * Give the user feedback after suspending or reinstating a theme.
 */
function wporg_themes_admin_notices() {
	if ( ! empty( $_GET['suspended'] ) ) {
		$suspended = absint( $_GET['suspended'] );
		$message   = _n( '%s theme suspended.', '%s themes suspended.', $suspended, 'wporg-themes' );

		add_settings_error( 'wporg_themes', 'suspended', sprintf( $message, $suspended ) );
	}

	elseif ( ! empty( $_GET['reinstated'] ) ) {
		$reinstated = absint( $_GET['reinstated'] );
		$message    = _n( '%s theme reinstated.', '%s themes reinstated.', $reinstated, 'wporg-themes' );

		add_settings_error( 'wporg_themes', 'reinstated', sprintf( $message, $reinstated ), 'updated' );
	}

	// Display admin notices, if any.
	settings_errors( 'wporg_themes' );
}
add_filter( 'admin_notices', 'wporg_themes_admin_notices' );

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

		$html = '<img src="' . esc_url( $src ) . '" alt="" />';
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
 * Better styles for our custom columns.
 */
function wporg_themes_custom_columns_style() {
	if ( 'repopackage' !== $GLOBALS['post_type'] ) {
		return;
	}

	wp_add_inline_style( 'wp-admin', '
		.fixed .column-version,
		.fixed .column-ticket {
			width: 10%;
		}

		@media screen and ( max-width: 782px ) {
			.fixed .column-version,
			.fixed .column-theme-url,
			.fixed .column-author-url,
			.fixed .column-ticket {
				display: none;
			}
		}
	' );
}
add_action( 'admin_print_styles-edit.php', 'wporg_themes_custom_columns_style' );

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

	// Only run once.
	remove_action( 'save_post', __FUNCTION__ );

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

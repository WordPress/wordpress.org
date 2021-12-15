<?php
namespace WordPressdotorg\Trac\Watcher;

/*
 * This is a suuuuper basic admin UI, that's not actually as minimal as I intended...
 */

include __DIR__ . '/list-table.php';
include __DIR__ . '/post.php';

add_action( 'admin_menu', function() {
	$svns = SVN\get_svns();

	// Limit the display to just this make sites revisions if possible.
	if ( 
		is_multisite() &&
		( $path = trim( parse_url( home_url('/'), PHP_URL_PATH ), '/' ) ) &&
		isset( $svns[ $path ] )
	) {
		$svns = [ $path => $svns[ $path ] ];
	}

	foreach ( $svns as $slug => $details ) {
		// No point showing this UI if we're not importing Props.
		if ( empty( $details['props_table'] ) ) {
			continue;
		}

		$name = sprintf( "%s Props", $details['name'] );
		$hook = add_menu_page(
			$name,
			$name,
			'edit_posts',
			'props-edit-' . $slug,
			function() use ( $details ) {
				display_list_table( $details );
			}
		);
		add_action( 'load-' . $hook, __NAMESPACE__ . '\load_page' );

		$hook = add_submenu_page(
			'props-edit-' . $slug,
			'Reports',
			'Reports',
			'edit_posts',
			'props-reports-' . $slug,
			function() use ( $details ) {
				include __DIR__ . '/reports-page.php';
				display_reports_page( $details );
			}
		);
		add_action( 'load-' . $hook, __NAMESPACE__ . '\load_page' );
	}

} );

function load_page() {
	add_screen_option(
		'per_page',
		[
			'default' => 100,
		]
	);

	// Run the import upon loading the page if it hasn't run recently.
	if ( get_site_transient( 'import_revisions_from_svn' ) < time() - 5*MINUTE_IN_SECONDS ) {
		do_action( 'import_revisions_from_svn' );
	}

	wp_enqueue_script( 'trac-watch', plugins_url( 'admin/trac-watch.js', PLUGIN ), [ 'jquery', 'thickbox' ], filemtime( __DIR__ . '/trac-watch.js' ) );
	wp_enqueue_style( 'trac-watch', plugins_url( 'admin/trac-watch.css', PLUGIN ), [ 'thickbox' ], filemtime( __DIR__ . '/trac-watch.css' ) );

	wp_localize_script(
		'trac-watch',
		'TracWatchData',
		[
			'edit_nonce'    => wp_create_nonce( 'edit_svn_prop' ),
			'reparse_nonce' => wp_create_nonce( 'reparse_svn' ),
		]
	);
}

function display_list_table( $details ) {
	$url   = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'admin.php' ) );
	$table = new Commits_List_Table( $details );
	$table->prepare_items( $_REQUEST );
	?>
		<div class="wrap propstable">
			<h2><?php echo esc_html( $details['name'] ); ?> Props</h2>
			<form method="GET" action="<?php echo esc_url( $url ); ?>">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php $table->search_box( 'Search', 's' ); ?>
				<?php $table->display(); ?>
			</form>
		</div>

		<div style="display:none" id="propedit-template">
			<h3>Edit Prop</h3>
			<form>
				<input type="hidden" name="prop_name_orig" value="" />
				<input type="hidden" name="svn" value="" />
				<input type="hidden" name="revision" value="" />
				<input type="hidden" name="what" value="" />
				<input type="hidden" name="_wpnonce" value="" />
				<label>
					Name in Commit message:<br>
					<input type="text" class="widefat" name="prop_name" value="" />
				</label>
				<br>
				<label>
					User ID, login, nicename, or profile URL:<br>
					<input type="text" class="widefat" name="user_id" value="" />
				</label>
				<p>
					<button type="button" class="save button button-primary">Save</button> &nbsp;
					<button type="button" class="cancel button button-secondary">Cancel</button> &nbsp;
					<button type="button" class="delete button button-secondary button-link-delete">Delete</button>
				</p>
			</form>
			<p>
				<em>Adding a missing prop? Fill out at least the first field..</em><br>
				<em>Typo made? Leave the Name in the commit as-is, just fill in the correct user. Future typo's will be then understand.</em><br>
				<em>Invalid prop? Prop Parser fail? Hit delete for required matches.</em>
			</p>
		</div>
	<?php
}

function get_wordpress_versions() {
	$versions = [];
	if ( function_exists( 'wporg_get_secure_versions' ) ) {
		$versions = array_map( 'floatval', wporg_get_secure_versions() );
	}
	if ( defined( 'WP_CORE_LATEST_RELEASE' ) ) {
		array_unshift( $versions, ((float)WP_CORE_LATEST_RELEASE)+0.1 );
	}

	return array_map( function( $v ) {
		return sprintf( '%.1f', $v );
	}, $versions );
}

function get_branches_for( $svn ) {
	global $wpdb;

	$branches = get_transient( $svn['slug'] . '_branches' );
	if ( ! $branches ) {
		$branches = $wpdb->get_col( 'SELECT branch FROM ' . $svn['rev_table'] . ' WHERE branch NOT LIKE "tag%" GROUP BY branch ORDER BY max(date) > DATE_SUB( NOW(), INTERVAL 1 YEAR ) DESC, branch DESC' );
		set_transient( $svn['slug'] . '_branches', $branches, DAY_IN_SECONDS );
	}

	return array_filter( (array)$branches );
}

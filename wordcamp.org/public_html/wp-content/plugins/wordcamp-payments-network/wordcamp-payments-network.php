<?php
/*
* Plugin Name: WordCamp Payments Network Dashboard
* Plugin URI: http://wordcamp.org
* Version: 1.0
* Author: Automattic
* Author URI: http://wordcamp.org
* License: GPLv2 or later
* Network: true
*/

class WordCamp_Payments_Network_Tools {
	public static $list_table;
	public static $db_version = 4;

	/**
	 * Runs during plugins_loaded, doh.
	 */
	public static function plugins_loaded() {
		$current_site = get_current_site();

		// Schedule the aggregate event only on the main blog in the network.
		if ( get_current_blog_id() == $current_site->blog_id && ! wp_next_scheduled( 'wordcamp_payments_aggregate' ) )
			wp_schedule_event( time(), 'hourly', 'wordcamp_payments_aggregate' );

		add_action( 'wordcamp_payments_aggregate', array( __CLASS__, 'aggregate' ) );
		add_action( 'network_admin_menu', array( __CLASS__, 'network_admin_menu' ) );
		add_action( 'init', array( __CLASS__, 'upgrade' ) );

		// Diff-based updates to the index.
		add_action( 'save_post', array( __CLASS__, 'save_post' ) );
		add_action( 'delete_post', array( __CLASS__, 'delete_post' ) );

		if ( ! empty( $_GET['wcp-debug-network'] ) && current_user_can( 'manage_network' ) )
			add_action( 'admin_init', function() { do_action( 'wordcamp_payments_aggregate' ); }, 99 );
	}

	/**
	 * Returns the name of the custom table.
	 */
	public static function get_table_name() {
		global $wpdb;
 		return $wpdb->get_blog_prefix(0) . 'wordcamp_payments_index';
	}

	/**
	 * Upgrade routine, makes sure that our schema is up to date.
	 */
	public static function upgrade() {
		global $wpdb;

		// Don't attempt to perform upgrades outside of the dashboard.
		if ( ! is_admin() )
			return;

		$current_version = get_site_option( 'wcp_network_db_version', 0 );
		if ( version_compare( $current_version, self::$db_version, '>=' ) )
			return;

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset} COLLATE {$wpdb->collate}";
		$sql = sprintf( "CREATE TABLE %s (
			id int(11) unsigned NOT NULL auto_increment,
			blog_id int(11) unsigned NOT NULL default '0',
			post_id int(11) unsigned NOT NULL default '0',
			created int(11) unsigned NOT NULL default '0',
			category varchar(255) NOT NULL default '',
			method varchar(255) NOT NULL default '',
			due int(11) unsigned NOT NULL default '0',
			status varchar(255) NOT NULL default '',
			PRIMARY KEY  (id),
			KEY blog_post_id (blog_id, post_id),
			KEY due (due),
			KEY status (status)
		) %s;", self::get_table_name(), $charset_collate );

		dbDelta( $sql );

		update_site_option( 'wcp_network_db_version', self::$db_version );
	}

	/**
	 * Runs on a cron job, reads data from all sites in the network
	 * and builds an index table for future queries.
	 */
	public static function aggregate() {
		global $wpdb;

		// Register the custom payment statuses so that we can filter posts to include only them, in order to exclude trashed posts
		require_once( WP_PLUGIN_DIR . '/wordcamp-payments/classes/payment-request.php' );
		WCP_Payment_Request::register_post_statuses();

		// Truncate existing table.
		$wpdb->query( sprintf( "TRUNCATE TABLE %s;", self::get_table_name() ) );

		$blogs = $wpdb->get_col( $wpdb->prepare( "SELECT blog_id FROM `{$wpdb->blogs}` WHERE site_id = %d ORDER BY last_updated DESC LIMIT %d;", $wpdb->siteid, 1000 ) );
		foreach ( $blogs as $blog_id ) {
			switch_to_blog( $blog_id );

			// Skip sites where WordCamp Payments is not active.
			if ( ! in_array( 'wordcamp-payments/bootstrap.php', (array) apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
				restore_current_blog();
				continue;
			}

			$paged = 1;
			while ( $requests = get_posts( array(
				'paged' => $paged++,
				'post_status' => array( 'paid', 'unpaid' ),
				'post_type' => 'wcp_payment_request',
				'posts_per_page' => 20,
			) ) ) {
				foreach ( $requests as $request ) {
					$wpdb->insert( self::get_table_name(), self::prepare_for_index( $request ) );
				}
			}

			restore_current_blog();
		}
	}

	/**
	 * Given a $request (could be a post_id) create an array that can
	 * be used with $wpdb->update() or $wpdb->insert() to add or update
	 * an index entry.
	 */
	public static function prepare_for_index( $request ) {
		$request = get_post( $request );

		return array(
			'blog_id' => get_current_blog_id(),
			'post_id' => $request->ID,
			'created' => get_post_time( 'U', true, $request->ID ),
			'due' => absint( get_post_meta( $request->ID, '_camppayments_due_by', true ) ),
			'status' => $request->post_status,
			'method' => get_post_meta( $request->ID, '_camppayments_payment_method', true ),
			'category' => get_post_meta( $request->ID, '_camppayments_payment_category', true ),
		);
	}

	/**
	 * Runs during save_post, make sure our index is up to date.
	 */
	public static function save_post( $post_id ) {
		global $wpdb;

		$request = get_post( $post_id );
		if ( 'wcp_payment_request' != $request->post_type )
			return;

		$table_name = self::get_table_name();
		$entry_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table_name} WHERE `blog_id` = %d AND `post_id` = %d LIMIT 1;", get_current_blog_id(), $request->ID ) );

		// Insert or update this record.
		if ( empty( $entry_id ) ) {
			$wpdb->insert( $table_name, self::prepare_for_index( $request ) );
		} else {
			$wpdb->update( $table_name, self::prepare_for_index( $request ), array( 'id' => $entry_id ) );
		}
	}

	/**
	 * Delete an index query when a request post has been deleted.
	 */
	public static function delete_post( $post_id ) {
		global $wpdb;

		$request = get_post( $post_id );
		if ( 'wcp_payment_request' != $request->post_type )
			return;

		$table_name = self::get_table_name();
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table_name} WHERE `blog_id` = %d AND `post_id` = %d LIMIT 1;", get_current_blog_id(), $request->ID ) );
	}

	/**
	 * Create a network admin menu item entry.
	 */
	public static function network_admin_menu() {
		$dashboard = add_dashboard_page( 'WordCamp Payments Dashboard', 'Payments', 'manage_network', 'wcp-dashboard', array( __CLASS__, 'render_dashboard' ) );
		add_action( 'load-' . $dashboard, array( __CLASS__, 'pre_render_dashboard' ) );
	}

	/**
	 * Renders the Dashboard - Payments screen.
	 */
	public static function render_dashboard() {
		?>
		<div class="wrap">
			<?php screen_icon( 'tools' ); ?>
			<h2>WordCamp Payments Dashboard</h2>
			<?php settings_errors(); ?>
			<h3 class="nav-tab-wrapper"><?php self::render_dashboard_tabs(); ?></h3>

			<?php self::$list_table->print_inline_css(); ?>
			<div id="wcp-list-table">

				<?php self::$list_table->prepare_items(); ?>
				<form id="posts-filter" action="" method="get">
					<input type="hidden" name="page" value="wcp-dashboard" />
					<input type="hidden" name="wcp-section" value="overdue" />
					<?php self::$list_table->display(); ?>
				</form>

			</div>
		<?php
	}

	/**
	 * Loads and initializes the list table object.
	 */
	public static function pre_render_dashboard() {
		require_once( __DIR__ . '/includes/class-list-table.php' );

		self::$list_table = new WordCamp_Payments_Network_List_Table;
		self::$list_table->set_view( self::get_current_tab() );
	}

	/**
	 * Returns the current active tab in the UI.
	 */
	public static function get_current_tab() {
		if ( isset( $_REQUEST['wcp-section'] ) )
			return strtolower( $_REQUEST['wcp-section'] );

		return 'overdue';
	}

	/**
	 * Renders available tabs.
	 */
	public static function render_dashboard_tabs() {
		$current_section = self::get_current_tab();
		$sections = array(
			'overdue' => 'Overdue',
			'pending' => 'Pending',
			'paid'    => 'Paid',
		);

		foreach ( $sections as $section_key => $section_caption ) {
			$active = $current_section === $section_key ? 'nav-tab-active' : '';
			$url = add_query_arg( array(
				'wcp-section' => $section_key,
				'page' => 'wcp-dashboard',
			), network_admin_url( 'index.php' ) );
			echo '<a class="nav-tab ' . $active . '" href="' . esc_url( $url ) . '">' . esc_html( $section_caption ) . '</a>';
		}
	}
}

// Initialize the plugin.
add_action( 'plugins_loaded', array( 'WordCamp_Payments_Network_Tools', 'plugins_loaded' ) );
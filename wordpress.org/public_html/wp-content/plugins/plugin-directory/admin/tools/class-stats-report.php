<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Tools;

/**
 * All functionality related to Stats_Report Tool.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Tools
 */
class Stats_Report {

	/**
	 * Fetch the instance of the Stats_Report class.
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new self();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_to_menu' ) );
	}

	/**
	 * Adds the "Stats Report" link to the admin menu under "Tools".
	 */
	public function add_to_menu() {
		add_submenu_page(
			'tools.php',
			__( 'Stats Report', 'wporg-plugins' ),
			__( 'Stats Report', 'wporg-plugins' ),
			'plugin_review',
			'statsreport',
			array( $this, 'show_stats' )
		);
	}

	/**
	 * Returns the stats.
	 *
	 * @param array  $args {
	 *     Optional. Array of override arguments
	 *
	 *     @type string $date       The date (in Y-m-d format) for the end of the stats time interval (non-inclusive). Default today.
	 *     @type int    $num_days   The number of days in the stats time interval that ends at $date. Default 7.
	 *     @type int    $recentdays The number of days back from today to be considered "recent". Default 7.
	 * }
	 * @return array {
	 *     Array of stats.
	 *
	 *     @type int $plugin_approve                     The number of plugins approved within the defined time interval.
	 *     @type int $plugin_delist                      The number of plugins delisted within the defined time interval.
	 *     @type int $plugin_new                         The number of plugins submitted within the defined time interval.
	 *     @type int $plugin_reject                      The number of plugins rejected within the defined time interval.
	 *     @type int $in_queue                           The number of plugins currently in the queue (draft or pending).
	 *     @type int $in_queue_draft                     The number of draft plugins currently in the queue.
	 *     @type int $in_queue_oending                   The number of pending plugins currently in the queue.
	 *     @type int $in_queue_from_time_window          The number of plugins currently in the queue submitted during the specified time window.
	 *     @type int $in_queue_old                       The number of plugins currently in the queue that are older than "recently".
	 *     @type int $supportpress_queue_total_open      The number of currently open support threads.
	 *     @type int $supportpress_queue_total_open_old  The number of currently open support threads with no activity "recently".
	 *     @type int $supportpress_queue_interval_all    The number of threads (from just within the specified time window).
	 *     @type int $supportpress_queue_interval_closed The number of closed threads (from just withing the specified time window).
	 *     @type int $supportpress_queue_interval_open   The number of open threads (from just withing the specified time window).
	 * }
	 */
	public function get_stats( $args = array() ) {
		global $wpdb;

		$stats['as_of_date'] = strftime( '%Y-%m-%d', time() );

		$defaults = array(
			'date'       => $stats['as_of_date'],
			'num_days'   => 7,
			'recentdays' => 7,
		);

		foreach ( $defaults as $key => $val ) {
			$args[ $key ] = empty( $args[ $key ] ) ? $val : $args[ $key ];
		}

		$stats = $args;

		// --------------
		// Plugin Status Changes
		// --------------

		$states = array( 'plugin_approve', 'plugin_delist', 'plugin_new', 'plugin_reject' );
		foreach ( $states as $state ) {
			// The stats table used by bbPress1 (and could still be used, but isn't yet).
			// Won't provide meaningful results for time intervals that include days after the switch to WP.
			$stats[ $state ] = $wpdb->get_var( $wpdb->prepare (
				"SELECT SUM(views) FROM stats_extras WHERE name = 'plugin' AND date < %s AND date > DATE_SUB( %s, INTERVAL %d DAY ) AND value = %s",
				$args['date'],
				$args['date'],
				absint( $args['num_days'] ) + 1,
				$state
			) );
		}

		// --------------
		// Plugin Queue
		// --------------

		// # of plugins currently in the queue that are drafts (have not been processed/replied to yet)
		$stats['in_queue_draft'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` = 'draft'"
		);

		// # of plugins currently in the queue that are pending (have been initially replied to)
		$stats['in_queue_pending'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` = 'pending'"
		);

		// # of plugins currently in the queue (draft + pending)
		$stats['in_queue'] = $stats['in_queue_draft'] + $stats['in_queue_pending'];

		// # of plugins currently in the queue submitted during the specified time window
		$stats['in_queue_from_time_window'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` IN ( 'draft','pending' ) AND post_date < %s AND post_date > DATE_SUB( %s, INTERVAL %d DAY )",
			$args['date'],
			$args['date'],
			absint( $args['num_days'] ) + 1
		) );

		// # of plugins currently in the queue that are older than "recently"
		$stats['in_queue_old'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` IN ( 'draft','pending' ) AND post_date < DATE_SUB( %s, INTERVAL %d DAY )",
			$args['date'],
			absint( $args['recentdays'] ) + 1
		) );

		// --------------
		// SupportPress Queue
		// --------------

		// # of currently open threads
		$stats['supportpress_queue_total_open'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM plugins_support_threads WHERE state = 'open'"
		);

		// # of currently open threads with no activity in last 7 days
		$stats['supportpress_queue_total_open_old'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM plugins_support_threads WHERE state = 'open' AND dt < DATE_SUB( NOW(), INTERVAL %d DAY )",
			$args['recentdays']
		) );

		// # of total threads (from just those received during the time window)
		$stats['supportpress_queue_interval_all'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM plugins_support_threads WHERE state IN ( 'closed', 'open' ) AND dt < %s AND dt > DATE_SUB( %s, INTERVAL %d DAY )",
			$args['date'],
			$args['date'],
			absint( $args['num_days'] ) + 1
		) );

		// # of open and closed threads (from just those received during the time window)
		$sp_states = array( 'closed', 'open' );
		foreach ( $sp_states as $sp_state ) {
			$stats[ 'supportpress_queue_interval_' . $sp_state ] = $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM plugins_support_threads WHERE state = %s AND dt < %s AND dt > DATE_SUB( %s, INTERVAL %d DAY )',
				$sp_state,
				$args['date'],
				$args['date'],
				absint( $args['num_days'] ) + 1
			) );
		}

		return $stats;
	}

	/**
	 * Outputs the stats report admin page, including form to customize time range
	 * and the stats themselves.
	 */
	public function show_stats() {
		if ( ! current_user_can( 'plugin_review' ) ) {
			return;
		}

		$args = array();

		if ( isset( $_POST['date'] ) && preg_match( '/[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $_POST['date'] ) ) {
			$args['date'] = $_POST['date'];
		} else {
			$args['date'] = '';
		}

		$args['num_days'] = empty( $_POST['days'] ) ? '' : absint( $_POST['days'] );
		$args['recentdays'] = empty( $_POST['recentdays'] ) ? '' : absint( $_POST['recentdays'] );

		$stats = $this->get_stats( $args );

		$date = strftime( '%Y-%m-%d', time() );

		$start_date = date( 'Y-m-d', strtotime( "-{$stats['num_days']} days", strtotime( $stats['date'] ) ) );
		?>

		<div class="wrap stats-report">

		<h1><?php _e( 'Plugin Repository and SupportPress Stats Report', 'wporg-plugins' ); ?></h1>

		<form method="post">
		<table class="form-table"><tbody>
		<tr><th scope="row"><label for="date"><?php _e( 'Date', 'wporg-plugins' ); ?></label></th><td>
		<input name="date" type="text" id="date" value="<?php echo esc_attr( $args['date'] ); ?>" class="text">
		<p><?php printf( __( 'The day up to which stats are to be gathered. In YYYY-MM-DD format. Defaults to today (%s).', 'wporg-plugins' ), $date ); ?></p>
		</td></tr>

		<tr><th scope="row"><label for="days"><?php _e( 'Number of days', 'wporg-plugins' ); ?></label></th><td>
		<input name="days" type="text" id="days" value="<?php echo esc_attr( $args['num_days'] ); ?>" class="small-text">
		<p><?php printf( __( 'The number of days before "Date" to include in stats. Default is %d.', 'wporg-plugins' ), 7 ); ?></p>
		</td></tr>

		<tr><th scope="row"><label for="recentdays"><?php _e( '"Recent" number of days', 'wporg-plugins' ); ?></label></th><td>
		<input name="recentdays" type="text" id="recentdays" value="<?php echo esc_attr( $args['recentdays'] ); ?>" class="small-text">
		<p><?php printf( __( 'The number of days before today to consider as being "recent" (stats marked with **). Default is %d.', 'wporg-plugins' ), 7 ); ?></p>
		</td></tr>

		</tbody></table>
		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit', 'wporg-plugins' ); ?>"></p>
		</form>

		<h2><?php _e( 'Stats', 'wporg-plugins' ); ?></h2>

		<p><?php printf(
			__( 'Displaying stats for the %1$d days preceding %2$s (and other stats for the %3$d most recent days).', 'wporg-plugins' ),
			$stats['num_days'],
			$stats['date'],
			$stats['recentdays']
		); ?>
		</p>

		<h3><?php _e( 'Plugin Status Change Stats', 'wporg-plugins' ); ?></h3>

		<ul style="font-family:Courier New;">
			<li><?php printf( __( 'Plugins requested : %d', 'wporg-plugins' ), $stats['plugin_new'] ); ?></li>
			<li><?php printf( __( 'Plugins rejected : %d', 'wporg-plugins' ),  $stats['plugin_reject'] ); ?></li>
			<li><?php printf( __( 'Plugins closed : %d', 'wporg-plugins' ),    $stats['plugin_delist'] ); ?></li>
			<li><?php printf( __( 'Plugins approved : %d', 'wporg-plugins' ),  $stats['plugin_approve'] ); ?></li>
		</ul>

		<h3><?php _e( 'Plugin Queue Stats (current)', 'wporg-plugins' ); ?></h3>

		<ul style="font-family:Courier New;">
			<li><?php printf( __( 'Plugins in the queue (draft and pending)* : %d', 'wporg-plugins' ), $stats['in_queue'] ); ?></li>
			<li><?php printf( __( '&rarr; (older than %1$d days ago)** : %2$d', 'wporg-plugins' ), $stats['recentdays'], $stats['in_queue_old'] ); ?></li>
			<li><?php printf( __( '&rarr; (%1$s - %2$s) : %3$d', 'wporg-plugins' ), $start_date, $stats['date'], $stats['in_queue_from_time_window'] ); ?></li>
			<li><?php printf( __( '&rarr; (drafts; not processed or replied to yet)* : %d', 'wporg-plugins' ), $stats['in_queue_draft'] ); ?></li>
			<li><?php printf( __( '&rarr; (pending; replied to)* : %d', 'wporg-plugins' ), $stats['in_queue_pending'] ); ?></li>
		</ul>

		<h3><?php _e( 'SupportPress Queue Stats', 'wporg-plugins' ); ?></h3>

		<ul style="font-family:Courier New;">
			<li><?php printf( __( 'Total open tickets* : %d', 'wporg-plugins' ), $stats['supportpress_queue_total_open'] ); ?></li>
			<li><?php printf( __( ' &rarr; (with no activity in last %1$d days)** : %2$d', 'wporg-plugins' ), $stats['recentdays'], $stats['supportpress_queue_total_open_old'] ); ?></li>
			<li><?php printf( __( 'Within defined %d day time window:', 'wporg-plugins' ), $stats['num_days'] ); ?>
				<ul style="margin-left:20px;margin-top:0.5em;">
					<li><?php printf( __( 'Total : %d', 'wporg-plugins' ), $stats['supportpress_queue_interval_all'] ); ?></li>
					<li><?php printf( __( 'Closed : %d', 'wporg-plugins' ), $stats['supportpress_queue_interval_closed'] ); ?></li>
					<li><?php printf( __( 'Open : %d', 'wporg-plugins' ), $stats['supportpress_queue_interval_open'] ); ?></li>
				</ul>
			</li>
		</ul>

		<ul style="font-style:italic;">
			<li><code>*</code> : <?php _e( "Stat reflects current size of queue and does not take into account 'date' or 'day' interval", 'wporg-plugins' ); ?></li>
			<li><code>**</code> : <?php _e( "Stat reflects activity only within the 'recentdays' from today", 'wporg-plugins' ); ?></li>
		</ul>

		</div>
		<?php
	}

}

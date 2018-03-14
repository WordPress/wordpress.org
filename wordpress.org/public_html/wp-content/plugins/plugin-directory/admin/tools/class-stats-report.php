<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Tools;
use WordPressdotorg\Plugin_Directory\Template;

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
	 * @param array $args {
	 *    Optional. Array of override arguments
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
	 *     @type int $in_queue                           The number of plugins currently in the queue (new or pending).
	 *     @type int $in_queue_new                       The number of new plugins currently in the queue.
	 *     @type int $in_queue_pending                   The number of pending plugins currently in the queue.
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
		$stats[ 'plugin_approve' ] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_approved' AND meta_value >= %d AND meta_value < %d",
			strtotime( $args['date'] ) - ( $args['num_days'] * DAY_IN_SECONDS ),
			strtotime( $args['date'] ) + DAY_IN_SECONDS
		) );

		$stats[ 'plugin_delist' ] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = 'plugin_closed_date' AND meta_value >= %s AND meta_value < %s",
			date( 'Y-m-d', strtotime( $args['date'] ) - ( $args['num_days'] * DAY_IN_SECONDS ) ),
			date( 'Y-m-d', strtotime( $args['date'] ) )
		) );

		$stats[ 'plugin_delist_reasons' ] = array_column( $wpdb->get_results( $wpdb->prepare(
			"SELECT reason.meta_value as reason, COUNT(*) as count FROM $wpdb->postmeta closed_date JOIN $wpdb->postmeta reason ON closed_date.post_id = reason.post_id AND reason.meta_key = '_close_reason' WHERE closed_date.meta_key = 'plugin_closed_date' AND closed_date.meta_value >= %s AND closed_date.meta_value < %s GROUP BY reason.meta_value",
			date( 'Y-m-d', strtotime( $args['date'] ) - ( $args['num_days'] * DAY_IN_SECONDS ) ),
			date( 'Y-m-d', strtotime( $args['date'] ) )
		) ), 'count', 'reason' );

		$stats[ 'plugin_new' ] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_submitted_date' AND meta_value >= %d AND meta_value < %d",
			strtotime( $args['date'] ) - ( $args['num_days'] * DAY_IN_SECONDS ),
			strtotime( $args['date'] ) + DAY_IN_SECONDS
		) );

		$stats[ 'plugin_reject' ] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = '_rejected' AND meta_value >= %d AND meta_value < %d",
			strtotime( $args['date'] ) - ( $args['num_days'] * DAY_IN_SECONDS ),
			strtotime( $args['date'] ) + DAY_IN_SECONDS
		) );

		// --------------
		// Plugin Queue
		// --------------
		// # of plugins currently in the queue that are new (have not been processed/replied to yet)
		$stats['in_queue_new'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` = 'new'"
		);

		// # of plugins currently in the queue that are pending (have been initially replied to)
		$stats['in_queue_pending'] = $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` = 'pending'"
		);

		// # of plugins currently in the queue (new + pending)
		$stats['in_queue'] = $stats['in_queue_new'] + $stats['in_queue_pending'];

		// # of plugins currently in the queue submitted during the specified time window
		$stats['in_queue_from_time_window'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` IN ( 'new','pending' ) AND post_date < %s AND post_date > DATE_SUB( %s, INTERVAL %d DAY )",
			$args['date'],
			$args['date'],
			absint( $args['num_days'] ) + 1
		) );

		// # of plugins currently in the queue that are older than "recently"
		$stats['in_queue_old'] = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` IN ( 'new','pending' ) AND post_date < DATE_SUB( %s, INTERVAL %d DAY )",
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

		$args['num_days']   = empty( $_POST['days'] ) ? '' : absint( $_POST['days'] );
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
		<p>
		<?php
			/* translators: %s: today's date */
			printf(
				__( 'The day up to which stats are to be gathered. In YYYY-MM-DD format. Defaults to today (%s).', 'wporg-plugins' ),
				$date
			);
		?>
		</p>
		</td></tr>

		<tr><th scope="row"><label for="days"><?php _e( 'Number of days', 'wporg-plugins' ); ?></label></th><td>
		<input name="days" type="text" id="days" value="<?php echo esc_attr( $args['num_days'] ); ?>" class="small-text">
		<p>
		<?php
			/* translators: %d: 7 */
			printf(
				__( 'The number of days before "Date" to include in stats. Default is %d.', 'wporg-plugins' ),
				7
			);
		?>
		</p>
		</td></tr>

		<tr><th scope="row"><label for="recentdays"><?php _e( '"Recent" number of days', 'wporg-plugins' ); ?></label></th><td>
		<input name="recentdays" type="text" id="recentdays" value="<?php echo esc_attr( $args['recentdays'] ); ?>" class="small-text">
		<p>
		<?php
			/* translators: %d: 7 */
			printf(
				__( 'The number of days before today to consider as being "recent" (stats marked with **). Default is %d.', 'wporg-plugins' ),
				7
			);
		?>
		</p>
		</td></tr>

		</tbody></table>
		<p class="submit"><input type="submit" name="submit" id="submit" class="button button-primary" value="<?php esc_attr_e( 'Submit', 'wporg-plugins' ); ?>"></p>
		</form>

		<h2><?php _e( 'Stats', 'wporg-plugins' ); ?></h2>

		<p>
		<?php
			/* translators: 1: number of days, 2: selected date, 3: number of most recent days */
			printf(
				__( 'Displaying stats for the %1$d days preceding %2$s (and other stats for the %3$d most recent days).', 'wporg-plugins' ),
				$stats['num_days'],
				$stats['date'],
				$stats['recentdays']
			);
		?>
		</p>

		<h3><?php _e( 'Plugin Status Change Stats', 'wporg-plugins' ); ?></h3>

		<ul style="font-family:Courier New;">
			<li>
			<?php
				/* translators: %d: number of requested plugins */
				printf(
					__( 'Plugins requested : %d', 'wporg-plugins' ),
					$stats['plugin_new']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %s: number of rejected plugins */
				printf(
					__( 'Plugins rejected : %s', 'wporg-plugins' ),
					$stats['plugin_reject']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %s: number of closed plugins */
				printf(
					__( 'Plugins closed : %s', 'wporg-plugins' ),
					$stats['plugin_delist']
				);
				$reasons = array();
				if ( $stats['plugin_delist_reasons'] ) {
					echo '<ul>';
					foreach ( $stats['plugin_delist_reasons'] as $reason => $number ) {
						$reason = Template::get_close_reasons()[ $reason ];
						echo "<li>&nbsp;&nbsp;{$reason}: {$number}</li>";
					}
					echo '</ul>';
				}
			?>
			</li>
			<li>
			<?php
				/* translators: %s: number of approved plugins */
				printf(
					__( 'Plugins approved : %s', 'wporg-plugins' ),
					$stats['plugin_approve']
				);
			?>
			</li>
		</ul>

		<h3><?php _e( 'Plugin Queue Stats (current)', 'wporg-plugins' ); ?></h3>

		<ul style="font-family:Courier New;">
			<li>
			<?php
				/* translators: %d: number of plugins in the queue */
				printf(
					__( 'Plugins in the queue (new and pending)* : %d', 'wporg-plugins' ),
					$stats['in_queue']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: 1: number of most recent days, 2: number of older plugins in the queue */
				printf(
					__( '&rarr; (older than %1$d days ago)** : %2$d', 'wporg-plugins' ),
					$stats['recentdays'],
					$stats['in_queue_old']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: 1: start date, 2: end date, 3: number of plugins in the queue within defined time window */
				printf(
					__( '&rarr; (%1$s - %2$s) : %3$d', 'wporg-plugins' ),
					$start_date,
					$stats['date'],
					$stats['in_queue_from_time_window']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %d: number of new plugins */
				printf(
					__( '&rarr; (new; not processed or replied to yet)* : %d', 'wporg-plugins' ),
					$stats['in_queue_new']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %d: number of pending plugins */
				printf(
					__( '&rarr; (pending; replied to)* : %d', 'wporg-plugins' ),
					$stats['in_queue_pending']
				);
			?>
			</li>
		</ul>

		<h3><?php _e( 'SupportPress Queue Stats', 'wporg-plugins' ); ?></h3>

		<ul style="font-family:Courier New;">
			<li>
			<?php
				/* translators: %d: number of open tickets */
				printf(
					__( 'Total open tickets* : %d', 'wporg-plugins' ),
					$stats['supportpress_queue_total_open']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: 1: number of most recent days, 2: number of plugins with no activity */
				printf(
					__( ' &rarr; (with no activity in last %1$d days)** : %2$d', 'wporg-plugins' ),
					$stats['recentdays'],
					$stats['supportpress_queue_total_open_old']
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %d: number of most recent days */
				printf(
					__( 'Within defined %d day time window:', 'wporg-plugins' ),
					$stats['num_days']
				);
				?>
				<ul style="margin-left:20px;margin-top:0.5em;">
					<li>
					<?php
						/* translators: %d: total number of plugins within defined time window */
						printf(
							__( 'Total : %d', 'wporg-plugins' ),
							$stats['supportpress_queue_interval_all']
						);
					?>
					</li>
					<li>
					<?php
						/* translators: %d: number of closed plugins within defined time window */
						printf(
							__( 'Closed : %d', 'wporg-plugins' ),
							$stats['supportpress_queue_interval_closed']
						);
					?>
					</li>
					<li>
					<?php
						/* translators: %d: number of open plugins within defined time window */
						printf(
							__( 'Open : %d', 'wporg-plugins' ),
							$stats['supportpress_queue_interval_open']
						);
					?>
					</li>
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

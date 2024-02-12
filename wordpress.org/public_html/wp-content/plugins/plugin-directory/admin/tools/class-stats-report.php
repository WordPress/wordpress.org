<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Tools;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Clients\HelpScout;

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
		add_action( 'admin_page_access_denied', array( $this, 'admin_page_access_denied' ) );
	}

	/**
	 * Adds the "Stats Report" link to the admin menu under "Tools".
	 */
	public function add_to_menu() {
		add_submenu_page(
			'plugin-tools',
			__( 'Stats Report', 'wporg-plugins' ),
			__( 'Stats Report', 'wporg-plugins' ),
			'plugin_review',
			'statsreport',
			array( $this, 'show_stats' )
		);
	}

	/**
	 * Redirect the old location.
	 */
	public function admin_page_access_denied() {
		global $pagenow, $plugin_page;
		if (
			isset( $pagenow, $plugin_page ) &&
			'tools.php' === $pagenow &&
			'statsreport' === $plugin_page
		) {
			wp_safe_redirect( admin_url( "admin.php?page={$plugin_page}" ) );
			exit;
		}
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
	 *     @type int   $plugin_approve                        The number of plugins approved within the defined time interval.
	 *     @type int   $plugin_delist                         The number of plugins delisted within the defined time interval.
	 *     @type array $plugin_delist_reasons                 The number of plugins delisted within the defined time interval, broken down by reason.
	 *     @type int   $plugin_new                            The number of plugins submitted within the defined time interval.
	 *     @type int   $plugin_reject                         The number of plugins rejected within the defined time interval.
	 *     @type int   $in_queue                              The number of plugins currently in the queue (new or pending).
	 *     @type int   $in_queue_new                          The number of new plugins currently in the queue.
	 *     @type int   $in_queue_pending                      The number of pending plugins currently in the queue.
	 *     @type array $in_queue_pending_why                  The number of pending plugins currently in the queue, broken down by whom we're waiting on.
	 *     @type int   $in_queue_from_time_window             The number of plugins currently in the queue submitted during the specified time window.
	 *     @type int   $in_queue_old                          The number of plugins currently in the queue that are older than "recently".
	 *     @type int   $helpscout_queue_total_conversations   The number of ongoing Help Scout conversations.
	 *     @type int   $helpscout_queue_new_conversations     The number of new Help Scout conversations.
	 *     @type int   $helpscout_queue_customers             The number of unique Plugin authors contacted.
	 *     @type int   $helpscout_queue_conversations_per_day The number of Help Scout conversations per day.
	 *     @type int   $helpscout_queue_busiest_day           The busiest day in the Help Scout queue.
	 *     @type int   $helpscout_queue_messages_received     The number of emails received in HelpScout.
	 *     @type int   $helpscout_queue_replies_sent          The number of replies sent to emails.
	 *     @type int   $helpscout_queue_emails_created        The number of new outgoing conversations created.
	 * }
	 */
	public function get_stats( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date'       => gmdate( 'Y-m-d' ),
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

		$stats[ 'plugin_delist_reasons' ] = array_map( 'intval', $stats[ 'plugin_delist_reasons' ] );

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
		$stats['in_queue_new'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` = 'new'"
		);

		// # of plugins currently in the queue that are pending (have been initially replied to)
		$stats['in_queue_pending'] = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` = 'pending'"
		);

		// # Break down the plugins in the queue, based on if we're still waiting on a reply.
		$stats['in_queue_pending_why'] = $wpdb->get_row( $wpdb->prepare(
			'SELECT
				SUM( IF( active = 0 AND closed > 0, 1, 0 ) ) AS `author`,
				SUM( IF( active > 0, 1, 0 ) ) AS `reviewer`,
				SUM( IF( active = 0 AND closed = 0, 1, 0 ) ) AS `noemail`
			FROM (
				SELECT
					p.post_name,
					SUM( IF( emails.status = "closed", 1, 0 ) ) AS `closed`,
					SUM( IF( emails.status = "active", 1, 0 ) ) AS `active`
				FROM %i p
					LEFT JOIN %i meta ON meta.meta_key = "plugins" AND meta.meta_value = p.post_name
					LEFT JOIN %i emails ON meta.helpscout_id = emails.id
				WHERE p.post_status = "pending"
				GROUP BY p.ID
			) subquery',
			$wpdb->posts,
			"{$wpdb->base_prefix}helpscout_meta",
			"{$wpdb->base_prefix}helpscout",
		), ARRAY_A );

		$stats['in_queue_pending_why'] = array_map( 'intval', $stats['in_queue_pending_why'] );

		// # of plugins currently in the queue (new + pending)
		$stats['in_queue'] = $stats['in_queue_new'] + $stats['in_queue_pending'];

		// # of plugins currently in the queue submitted during the specified time window
		$stats['in_queue_from_time_window'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` IN ( 'new','pending' ) AND post_date < %s AND post_date > DATE_SUB( %s, INTERVAL %d DAY )",
			$args['date'],
			$args['date'],
			absint( $args['num_days'] ) + 1
		) );

		// # of plugins currently in the queue that are older than "recently"
		$stats['in_queue_old'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $wpdb->posts WHERE `post_type` = 'plugin' AND `post_status` IN ( 'new','pending' ) AND post_date < DATE_SUB( %s, INTERVAL %d DAY )",
			$args['date'],
			absint( $args['recentdays'] ) + 1
		) );

		// --------------
		// Help Scout Queue
		// --------------

		$start_datetime = gmdate( 'Y-m-d\T00:00:00\Z', strtotime( $args['date'] ) - ( $args['num_days'] * DAY_IN_SECONDS ) );
		$end_datetime   = gmdate( 'Y-m-d\T23:59:59\Z', strtotime( $args['date'] ) );

		$api_payload = [
			'start'     => $start_datetime,
			'end'       => $end_datetime,
			'mailboxes' => HELPSCOUT_PLUGINS_MAILBOXID,
		];
		
		$company_report  = HelpScout::api( '/v2/reports/company', $api_payload );
		$mailbox_overall = HelpScout::api( '/v2/reports/conversations', $api_payload );
		$email_report    = HelpScout::api( '/v2/reports/email', $api_payload );

		// If any of the API's are unavailable, make it obvious that the requests have failed, but returning 0's for everything.
		if ( ! $company_report || ! $mailbox_overall || ! $email_report ) {
			$company_report = $mailbox_overall = $email_report = false;
		}

		$stats['helpscout_queue_total_conversations']     = $mailbox_overall->current->totalConversations ?? 0;
		$stats['helpscout_queue_new_conversations']       = $mailbox_overall->current->newConversations ?? 0;
		$stats['helpscout_queue_customers']               = $mailbox_overall->current->customers ?? 0;
		$stats['helpscout_queue_conversations_per_day']   = $mailbox_overall->current->conversationsPerDay ?? 0;
		$stats['helpscout_queue_busiest_day']             = gmdate( 'l', strtotime( 'Sunday +' . ( $mailbox_overall->busiestDay->day ?? 0 ) . ' days' ) ); // Hacky? but works
		$stats['helpscout_queue_messages_received']       = $mailbox_overall->current->messagesReceived ?? 0;
		$stats['helpscout_queue_replies_sent']            = $company_report->current->totalReplies;
		$stats['helpscout_queue_emails_created']          = $email_report->current->volume->emailsCreated ?? 0;

		return $stats;
	}

	public function get_user_stats( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'date'       => gmdate( 'Y-m-d' ),
			'num_days'   => 7,
			'recentdays' => 7,
		);

		foreach ( $defaults as $key => $val ) {
			$args[ $key ] = empty( $args[ $key ] ) ? $val : $args[ $key ];
		}

		$stats = $args;
		$stats['start_date'] = gmdate( 'Y-m-d', time() - ( $args['num_days'] * DAY_IN_SECONDS ) );
		$stats['data']       = [];

		$reviewers = get_users( [
			'role__in' => [
				'plugin_reviewer',
				'plugin_admin',
				'administrator',
			],
		] );

		$reviewer_ids            = wp_list_pluck( $reviewers, 'ID' );
		$reviewer_ids_list       = implode( ', ', $reviewer_ids );
		$reviewer_nicenames      = wp_list_pluck( $reviewers, 'user_nicename' );
		$reviewer_nicenames_list = $wpdb->prepare( trim( str_repeat( '%s,', count( $reviewer_nicenames ) ) , ',' ), $reviewer_nicenames );

		$events = $wpdb->get_results( $wpdb->prepare(
			"SELECT user_id,
			CASE
				WHEN `comment_content` LIKE concat( 'Assigned to%', comment_author, '%' ) THEN 'Assigned to self.'
				WHEN `comment_content` LIKE 'Assigned to%' THEN 'Assigned to others.'
				ELSE `comment_content`
			END AS `_thing`,
			count(*) AS `count`
			FROM %i
			WHERE
				`comment_date_gmt` > DATE_SUB( %s, INTERVAL %d DAY )
				AND `user_id` IN( {$reviewer_ids_list} )
				AND `comment_agent` = ''
				AND (
					`comment_content` IN( 'Plugin Approved.', 'Unassigned.' )
					OR `comment_content` LIKE 'Assigned TO%'
					OR `comment_content` LIKE 'Plugin rejected.%'
					OR (
						`comment_content` LIKE 'Plugin closed.%'
						AND NOT `comment_content` LIKE '%Author Self-close%'
					)
				)
			GROUP BY `user_id`, `_thing`
			ORDER BY `user_id`, `_thing`",
			$wpdb->comments,
			$args['date'],
			$args['num_days'],
		) );

		foreach ( $events as $row ) {
			$stats['data'][ $row->user_id ] ??= [];
			$stats['data'][ $row->user_id ][ $row->_thing ] = $row->count;
		}

		// Fetch HelpScout stats from our stats. We might be able to pull some information from HS Stats instead.
		$stats_field_prefix = 'hs-plugins-';
		if (
			// See https://meta.trac.wordpress.org/changeset/13010
			strtotime( $stats['start_date'] ) < strtotime('2023-12-06')
		) {
			$stats_field_prefix      = 'hs-';
			$stats['all-hs-warning'] = true;
		}

		$emails = $wpdb->get_results( $wpdb->prepare(
			"SELECT `name`, `value`, SUM(views) AS count
			FROM %i
			WHERE `name` IN( %s, %s )
				AND `value` IN( {$reviewer_nicenames_list} )
				AND `date` > %s
			GROUP BY `name`, `value`",
			'stats_extras',
			$stats_field_prefix . 'total',
			$stats_field_prefix . 'replies',
			$stats['start_date']
		) );

		foreach ( $emails as $row ) {
			$user  = get_user_by( 'slug', $row->value );
			$field = str_ends_with( $row->name, '-total' ) ? 'Email Actions' : 'Email Replies';
			$stats['data'][ $user->ID ] ??= [];
			$stats['data'][ $user->ID ][ $field ] = $row->count;
		}

		uasort( $stats['data'], function( $a, $b ) {
			return array_sum( $b ) <=> array_sum( $a );
		} );

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

		if ( isset( $_REQUEST['date'] ) && preg_match( '/[0-9]{4}\-[0-9]{2}\-[0-9]{2}$/', $_REQUEST['date'] ) ) {
			$args['date'] = $_REQUEST['date'];
		} else {
			$args['date'] = gmdate( 'Y-m-d' );
		}

		$args['num_days']   = empty( $_REQUEST['days'] ) ? 7 : absint( $_REQUEST['days'] );
		$args['recentdays'] = empty( $_REQUEST['recentdays'] ) ? 7 : absint( $_REQUEST['recentdays'] );

		$stats      = $this->get_stats( $args );
		$user_stats = $this->get_user_stats( $args );

		$date = gmdate( 'Y-m-d' );

		$start_date = gmdate( 'Y-m-d', strtotime( "-{$stats['num_days']} days", strtotime( $stats['date'] ) ) );
		?>

		<div class="wrap stats-report">

		<h1><?php _e( 'Plugin Repository and email Stats Report', 'wporg-plugins' ); ?></h1>

		<form method="get">
		<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>"/>
		<table class="form-table"><tbody>
		<tr><th scope="row"><label for="date"><?php _e( 'Date', 'wporg-plugins' ); ?></label></th><td>
		<input name="date" type="text" id="date" value="<?php echo esc_attr( $args['date'] ); ?>" class="text">
		<p>
		<?php
			/* translators: %s: today's date */
			printf(
				__( 'The day up to which stats are to be gathered. In YYYY-MM-DD format. Defaults to today (%s).', 'wporg-plugins' ),
				esc_html( $date )
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
				esc_html( $stats['num_days'] ),
				esc_html( $stats['date'] ),
				esc_html( $stats['recentdays'] )
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
					esc_html( $stats['plugin_new'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %s: number of rejected plugins */
				printf(
					__( 'Plugins rejected : %s', 'wporg-plugins' ),
					esc_html( $stats['plugin_reject'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %s: number of closed plugins */
				printf(
					__( 'Plugins closed : %s', 'wporg-plugins' ),
					esc_html( $stats['plugin_delist'] )
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
					esc_html( $stats['plugin_approve'] )
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
					esc_html( $stats['in_queue'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: 1: number of most recent days, 2: number of older plugins in the queue */
				printf(
					__( '&rarr; (older than %1$d days ago)** : %2$d', 'wporg-plugins' ),
					esc_html( $stats['recentdays'] ),
					esc_html( $stats['in_queue_old'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: 1: start date, 2: end date, 3: number of plugins in the queue within defined time window */
				printf(
					__( '&rarr; (%1$s - %2$s) : %3$d', 'wporg-plugins' ),
					esc_html( $start_date ),
					esc_html( $stats['date'] ),
					esc_html( $stats['in_queue_from_time_window'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %d: number of new plugins */
				printf(
					__( '&rarr; (new; not processed or replied to yet)* : %d', 'wporg-plugins' ),
					esc_html( $stats['in_queue_new'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %d: number of pending plugins */
				printf(
					__( '&rarr; (pending; replied to)* : %d', 'wporg-plugins' ),
					esc_html( $stats['in_queue_pending'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %d: number of pending plugins */
				printf(
					__( '&rarr; (pending; waiting on author)* : %d', 'wporg-plugins' ),
					esc_html( $stats['in_queue_pending_why']['author'] )
				);
			?>
			</li>
			<li>
			<?php
				/* translators: %d: number of pending plugins */
				printf(
					__( '&rarr; (pending; waiting on reviewer)* : %d', 'wporg-plugins' ),
					esc_html( $stats['in_queue_pending_why']['reviewer'] )
				);
			?>
			</li>
			<?php if ( $stats['in_queue_pending_why']['noemail'] ) : ?>
				<li>
				<?php
					/* translators: %d: number of pending plugins */
					printf(
						__( '&rarr; (pending; waiting on reviewer, email not yet sent)* : %d', 'wporg-plugins' ),
						esc_html( $stats['in_queue_pending_why']['noemail'] )
					);
				?>
				</li>
			<?php endif; ?>
		</ul>

		<h3><?php _e( 'Help Scout Queue Stats', 'wporg-plugins' ); ?></h3>

		<ul style="font-family:Courier New;">
			<li>
			<?php
				printf(
					'Total Conversations: %d',
					esc_html( $stats['helpscout_queue_total_conversations'] )
				);
			?>
			</li>
			<li>
			<?php
				printf(
					'New Conversations: %d',
					esc_html( $stats['helpscout_queue_new_conversations'] )
				);
			?>
			</li>
			<li>
			<?php
				printf(
					'Customers: %d',
					esc_html( $stats['helpscout_queue_customers'] )
				);
			?>
			</li>
			<li>
			<?php
				printf(
					'Conversations per Day: %d',
					esc_html( $stats['helpscout_queue_conversations_per_day'] )
				);
			?>
			</li>
			<li>
			<?php
				printf(
					'Busiest Day: %s',
					esc_html( $stats['helpscout_queue_busiest_day'] )
				);
			?>
			</li>
			<li>
			<?php
				printf(
					'Messages Received: %d',
					esc_html( $stats['helpscout_queue_messages_received'] )
				);
			?>
			</li>
			<li>
			<?php
				printf(
					'Replies Sent: %d',
					esc_html( $stats['helpscout_queue_replies_sent'] )
				);
			?>
			</li>
			<li>
			<?php
				printf(
					'Emails Created: %d',
					esc_html( $stats['helpscout_queue_emails_created'] )
				);
			?>
			</li>
		</ul>

		<ul style="font-style:italic;">
			<li><code>*</code> : <?php _e( "Stat reflects current size of queue and does not take into account 'date' or 'day' interval", 'wporg-plugins' ); ?></li>
			<li><code>**</code> : <?php _e( "Stat reflects activity only within the 'recentdays' from today", 'wporg-plugins' ); ?></li>
		</ul>

		<h3>Reviewer Stats</h3>
		<p><em>NOTE: These are not intended on being made public. Data displayed for the <?php echo esc_html( $user_stats['num_days'] ); ?> days ending <?php echo esc_html( $user_stats['date'] ); ?></em></p>

		<table class="widefat review-stats">
			<thead>
				<tr>
					<th>Reviewer</th>
					<th>Assigned<br>(to others)</th>
					<th>Plugins<br>Approved</th>
					<th>Plugins<br>Rejected</th>
					<th>Plugins<br>Closed</th>
					<th>Email<br>Actions^</th>
					<th>Email<br>Replies^</th>
				</tr>
			</thead>
			<?php

			echo '<tbody>';
			foreach ( $user_stats['data'] as $user_id => $user_stat ) {
				$user = get_user_by( 'id', $user_id );
				echo '<tr><th>', esc_html( $user->display_name ?: $user->user_login ), '</th>';

				// Assigned, Unassigned, Assigned to others.
				echo '<td><span title="Assigned to self">', number_format_i18n( $user_stat[ 'Assigned to self.' ] ?? 0 ), '</span>';
				if ( $user_stat[ 'Unassigned.' ] ?? 0 ) {
					echo '<span title="Unassigned"> -', number_format_i18n( $user_stat[ 'Unassigned.' ] ), '</span>';
				}
				if ( $user_stat[ 'Assigned to others.' ] ?? 0 ) {
					echo ' <span title="Assigned to others">(', number_format_i18n( $user_stat[ 'Assigned to others.' ] ), ')</span>';
				}
				echo '</td>';

				// Plugins Approved.
				echo '<td>', number_format_i18n( $user_stat[ 'Plugin approved.' ] ?? 0 ), '</td>';

				// Plugins Rejected.
				$user_rejected_breakdown = '';
				$user_rejected_count     = 0;
				foreach ( $user_stat as $key => $count ) {
					if ( ! preg_match( '/^Plugin rejected\./', $key ) ) {
						continue;
					}
					$user_rejected_count += $count;
					$reason               = trim( explode( ':', $key )[1] ?? '' );

					if ( ! $reason ) {
						continue;
					}

					$user_rejected_breakdown .= sprintf(
						"%s: %s\n",
						Template::get_rejection_reasons()[ $reason ],
						number_format_i18n( $count )
					);
				}
				$user_rejected_breakdown = trim( $user_rejected_breakdown );

				echo '<td class="breakdown">', '<span title="', esc_attr( $user_rejected_breakdown ), '">', number_format_i18n( $user_rejected_count ), '</span>';
				if ( $user_rejected_breakdown ) {
					echo '<div class="hidden">', nl2br( $user_rejected_breakdown ), '</div>';
				}
				echo '</td>';

				// Plugins Closed.
				$user_closed_breakdown = '';
				$user_closed_count     = 0;
				foreach ( $user_stat as $key => $count ) {
					if ( ! preg_match( '/^Plugin closed\./', $key ) ) {
						continue;
					}
					$reason             = trim( explode( ':', $key )[1] );
					$user_closed_count += $count;

					$user_closed_breakdown .= sprintf(
						"%s: %s\n",
						Template::get_close_reasons()[ $reason ],
						number_format_i18n( $count )
					);
				}
				$user_closed_breakdown = trim( $user_closed_breakdown );

				echo '<td class="breakdown">', '<span title="', esc_attr( $user_closed_breakdown ), '">', number_format_i18n( $user_closed_count ), '</span>';
				if ( $user_closed_breakdown ) {
					echo '<div class="hidden">', nl2br( $user_closed_breakdown ), '</div>';
				}
				echo '</td>';

				// Emails.
				echo '<td>', number_format_i18n( $user_stat[ "Email Actions" ] ?? 0 ), '</td>';
				echo '<td>', number_format_i18n( $user_stat[ "Email Replies" ] ?? 0 ), '</td>';
				echo '</tr>';
			}
			echo '</tbody>';
			echo '</table>';

			echo '<script> jQuery(document).ready( function($) {
				$("table.review-stats").on( "click", ".breakdown", function() {
					$(this).children().length > 1 && $(this).children().toggleClass("hidden");
				} );
			} );</script>';

			?>
			<ul style="font-style:italic;">
				<?php if ( isset( $user_stats['all-hs-warning'] ) ) : ?>
					<li><code>^</code> : This is of all Helpscout mailboxes, not Plugins specific, as plugins-only data is only available after <a href="https://meta.trac.wordpress.org/changeset/13010">2023-12-05</a>.</li>
				<?php endif; ?>
				<li><code>^</code> : Requires your Helpscout email to be the same as your WordPress.org email, or as one of your profiles alternate emails.</li>
				<li><code>^</code> : Email "Actions" include sending emails, replying to emails, marking as spam, moving to different inbox, etc.</li>
			</ul>

		</div>
		<?php
	}

}

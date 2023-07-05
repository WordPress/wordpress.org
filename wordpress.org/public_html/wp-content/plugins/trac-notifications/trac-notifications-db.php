<?php
/**
 * This class must execute queries valid for both MySQL and SQLite3.
 * The DB driver must be wpdb or Trac_Notifications_SQLite_Driver.
 * It must work without any other dependencies, such as WordPress.
 */
class Trac_Notifications_DB implements Trac_Notifications_API {
	function __construct( $db ) {
		$this->db = $db;
	}

	function get_unreplied_ticket_counts_by_component() {
		$rows = $this->db->get_results( "SELECT id, component FROM ticket t
			WHERE id NOT IN (SELECT ticket FROM ticket_change WHERE ticket = t.id AND t.reporter <> author AND field = 'comment' AND newvalue <> '')
			AND status <> 'closed'" );
		$component_unreplied = array();
		foreach ( $rows as $row ) {
			$component_unreplied[ $row->component ][] = $row->id;
		}
		return $component_unreplied;
	}

	function get_unreplied_tickets_by_component( $component ) {
		return $this->db->get_results( $this->db->prepare(
			"SELECT id, summary, status, resolution, milestone, value as focuses
			FROM ticket t LEFT JOIN ticket_custom c ON c.ticket = t.id AND c.name = 'focuses'
			WHERE id NOT IN (
				SELECT ticket FROM ticket_change
				WHERE ticket = t.id AND t.reporter <> author
				AND field = 'comment' AND newvalue <> ''
			) AND status <> 'closed' AND component = %s", $component ) );
	}

	function get_tickets_by_component_type_milestone() {
		return $this->db->get_results( "SELECT component, type, milestone, count(*) as count FROM ticket
			WHERE status <> 'closed' GROUP BY component, type, milestone ORDER BY component, type, milestone" );
	}

	function get_ticket_counts_for_component( $component ) {
		$tickets_by_type = $this->db->get_results( $this->db->prepare( "SELECT type, COUNT(*) as count FROM ticket WHERE component = %s AND status <> 'closed' GROUP BY type", $component ), OBJECT_K );
		foreach ( $tickets_by_type as &$object ) {
			$object = $object->count;
		}
		unset( $object );
		return $tickets_by_type;
	}

	function get_tickets_by( $args ) {
		$where = 'AND ' . implode( ' = %s AND ', array_keys( $args ) ) . ' = %s';
		if ( ! isset( $args['status'] ) ) {
			$where .= " AND status <> 'closed'";
		}
		return $this->db->get_results( $this->db->prepare( "SELECT id, summary, status, resolution, milestone FROM ticket WHERE 1=1 $where", array_values( $args ) ) );
	}

	function get_components() {
		return $this->db->get_col( "SELECT name FROM component WHERE name <> 'WordPress.org site' ORDER BY name ASC" );
	}

	function get_component_followers( $component ) {
		return $this->db->get_col( $this->db->prepare( "SELECT username FROM _notifications WHERE type = 'component' AND value = %s", $component ) );
	}

	function get_component_history( $component, $last_x_days = 7 ) {
		$days_ago = ( time() - ( 86400 * $last_x_days ) ) * 1000000;
		$closed_reopened = $this->db->get_results( $this->db->prepare( "SELECT newvalue, COUNT(DISTINCT ticket) as count
			FROM ticket_change tc INNER JOIN ticket t ON tc.ticket = t.id
			WHERE field = 'status' AND (newvalue = 'closed' OR newvalue = 'reopened')
			AND tc.time >= %s AND t.component = %s GROUP BY newvalue", $days_ago, $component ), OBJECT_K );
		$reopened = isset( $closed_reopened['reopened'] ) ? $closed_reopened['reopened']->count : 0;
		$closed = isset( $closed_reopened['closed'] ) ? $closed_reopened['closed']->count : 0;
		$opened = $this->db->get_var( $this->db->prepare( "SELECT COUNT(DISTINCT id) FROM ticket WHERE time >= %s AND component = %s", $days_ago, $component ) );
		$assigned_unassigned = $this->db->get_results( $this->db->prepare(
			"SELECT CASE WHEN newvalue = %s THEN 'assigned' ELSE 'unassigned' END as direction,
			COUNT(*) as count FROM ticket_change WHERE field = 'component' AND ( oldvalue = %s OR newvalue = %s ) AND time >= %s GROUP BY direction",
			$component, $component, $component, $days_ago ), OBJECT_K );
		$assigned = isset( $assigned_unassigned['assigned'] ) ? $assigned_unassigned['assigned']->count : 0;
		$unassigned = isset( $assigned_unassigned['unassigned'] ) ? $assigned_unassigned['unassigned']->count : 0;

		$change = $opened + $reopened + $assigned - $closed - $unassigned;
		return compact( 'change', 'opened', 'reopened', 'closed', 'assigned', 'unassigned' );
	}

	function get_milestones() {
		// Only show 3.8+, when this feature was launched.
		return $this->db->get_results( "SELECT name, completed FROM milestone
			WHERE name <> 'WordPress.org' AND (completed = 0 OR completed >= 1386864000000000)
			ORDER BY (completed = 0) DESC, name DESC", ARRAY_A );
	}

	function get_tickets_in_next_milestone( $component ) {
		return $this->db->get_results( $this->db->prepare( "SELECT id, summary, status, resolution, milestone, value as focuses FROM ticket t
			LEFT JOIN ticket_custom c ON c.ticket = t.id AND c.name = 'focuses' WHERE component = %s AND status <> 'closed' AND milestone LIKE '_._'", $component ) );
	}

	function get_trac_notifications_info( $ticket_id, $username ) {
		$meta = array(
			'get_trac_ticket'                              => $this->get_trac_ticket( $ticket_id ),
			'get_trac_ticket_focuses'                      => $this->get_trac_ticket_focuses( $ticket_id ),
			'get_trac_notifications_for_user'              => $this->get_trac_notifications_for_user( $username ),
			'get_trac_ticket_subscription_status_for_user' => $this->get_trac_ticket_subscription_status_for_user( $ticket_id, $username ),
			'get_trac_ticket_subscriptions'                => $this->get_trac_ticket_subscriptions( $ticket_id ),
			'get_trac_ticket_participants'                 => $this->get_trac_ticket_participants( $ticket_id ),
		);

		if ( $meta['get_trac_ticket']['reporter'] !== $username ) {
			$meta['get_reporter_last_activity'] = $this->get_reporter_past_activity( $meta['get_trac_ticket']['reporter'], $ticket_id );
		}

		return $meta;
	}

	function get_trac_ticket( $ticket_id ) {
		return $this->db->get_row( $this->db->prepare( "SELECT * FROM ticket WHERE id = %d", $ticket_id ), ARRAY_A );
	}

	function get_trac_ticket_focuses( $ticket_id ) {
		return $this->db->get_var( $this->db->prepare( "SELECT value FROM ticket_custom WHERE ticket = %d AND name = 'focuses'", $ticket_id ) );
	}

	function get_trac_ticket_participants( $ticket_id ) {
		// Make sure we suppress CC-only comments that still exist in the database.
		// Do this by suppressing any 'cc' changes and also any empty comments (used by Trac for comment numbering).
		// Empty comments are also used for other property changes made without comment, but those changes will still be returned by this query.
		$ignore_cc = "field <> 'cc' AND NOT (field = 'comment' AND newvalue = '') AND";
		return $this->db->get_col( $this->db->prepare( "SELECT DISTINCT author FROM ticket_change WHERE $ignore_cc ticket = %d", $ticket_id ) );
	}

	function get_trac_ticket_subscriptions( $ticket_id ) {
		$by_status = array( 'blocked' => array(), 'starred' => array() );
		$subscriptions = $this->db->get_results( $this->db->prepare( "SELECT username, status FROM _ticket_subs WHERE ticket = %d", $ticket_id ) );
		foreach ( $subscriptions as $subscription ) {
			$by_status[ $subscription->status ? 'starred' : 'blocked' ][] = $subscription->username;
		}
		return $by_status;
	}

	function get_trac_ticket_subscription_status_for_user( $ticket_id, $username ) {
		$status = $this->db->get_var( $this->db->prepare( "SELECT status FROM _ticket_subs WHERE username = %s AND ticket = %d", $username, $ticket_id ) );
		if ( null !== $status ) {
			$status = (int) $status;
		}
		return $status;
	}

	function get_trac_notifications_for_user( $username ) {
		$rows = $this->db->get_results( $this->db->prepare( "SELECT type, value FROM _notifications WHERE username = %s ORDER BY type ASC, value ASC", $username ) );
		$notifications = array( 'component' => array(), 'milestone' => array(), 'focus' => array(), 'newticket' => array() );

		foreach ( $rows as $row ) {
			$notifications[ $row->type ][ $row->value ] = true;
		}
		$notifications['newticket'] = ! empty( $notifications['newticket']['1'] );

		return $notifications;
	}

	function get_trac_ticket_subscriptions_for_user( $username ) {
		return $this->db->get_col( $this->db->prepare( "SELECT ticket FROM _ticket_subs WHERE username = %s AND status = 1", $username ) );
	}

	function get_reporter_past_activity( $reporter, $ticket ) {
		$activity = array();

		$activity['tickets'] = $this->db->get_results( $this->db->prepare( "SELECT id, summary, type, status, resolution
			FROM ticket WHERE reporter = %s AND id <= %d LIMIT 5", $reporter, $ticket ), ARRAY_A );

		if ( count( $activity['tickets'] ) === 1 ) {
			$activity['comments'] = (bool) $this->db->get_var( $this->db->prepare(
				"SELECT ticket FROM ticket_change WHERE field = 'comment'
				AND author = %s AND ticket <> %d LIMIT 1",
				$reporter, $ticket
			) );
		}

		return $activity;
	}

	function update_subscription( $username, $ticket, $status ) {
		$this->db->delete( '_ticket_subs', compact( 'username', 'ticket' ) );
		return $this->db->insert( '_ticket_subs', compact( 'username', 'ticket', 'status' ) );
	}

	function delete_subscription( $username, $ticket, $status ) {
		return $this->db->delete( '_ticket_subs', compact( 'username', 'ticket', 'status' ) );
	}

	function update_notifications( $all_changes ) {
		foreach ( $all_changes as $method => $changes ) {
			foreach ( $changes as $where ) {
				call_user_func( array( $this->db, $method ), '_notifications', (array) $where );
			}
		}
	}

	/**
	 * Fetch user preferences from Trac.
	 *
	 * @param string $username The username.
	 * @return array The user preferences. [ key => value ].
	 */
	function get_user_prefs( $username ) {
		$data = $this->db->get_results( $this->db->prepare(
			'SELECT name, value FROM session_attribute WHERE sid = %s',
			$username
		), ARRAY_A );

		return array_column( $data, 'value', 'name' );
	}

	/**
	 * Set a user preference in Trac.
	 *
	 * @param string $username   The username.
	 * @param string $name       The preference.
	 * @param string|null $value The value to set.
	 */
	function set_user_pref( $username, $name, $value = '' ) {
		// The trac column for username is `sid`. All our users are authenticated.
		// The session_attribute table has an index: UNIQUE (sid,authenticated,name)

		$result = $this->db->insert( 'session_attribute', array(
			'sid'           => $username,
			'authenticated' => 1,
			'name'          => $name,
			'value'         => $value
		) );

		if ( ! $result ) {
			$result = $this->db->update(
				'session_attribute',
				array(
					'value'         => $value
				), array(
					'sid'           => $username,
					'authenticated' => 1,
					'name'          => $name,
				)
			);
		}

		return $result;
	}
	
	/**
	 * Delete a user preference.
	 *
	 * @param string $username The username.
	 * @param string $name     The preference.
	 * @return bool
	 */
	function delete_user_pref( $username, $name ) {
		return $this->db->delete( 'session_attribute', array(
			'sid'           => $username,
			'authenticated' => 1,
			'name'          => $name
		) );
	}

	function get_user_anonymization_items( $username ) {
		$ticket_subscriptions = $this->get_trac_ticket_subscriptions_for_user( $username );
		$ticket_notifications = $this->get_trac_notifications_for_user( $username );

		$ticket_reporter = $this->db->get_col( $this->db->prepare(
			"SELECT id FROM ticket WHERE reporter = %s",
			$username
		) );

		$ticket_owner = $this->db->get_col( $this->db->prepare(
			"SELECT id FROM ticket WHERE owner = %s",
			$username
		) );

		$attachments = $this->db->get_results( $this->db->prepare(
			"SELECT type, id, filename FROM attachment WHERE author = %s",
			$username
		), ARRAY_A );

		$comments = $this->db->get_results( $this->db->prepare(
			"SELECT ticket, time FROM ticket_change WHERE author = %s",
			$username
		), ARRAY_A );

		$profile_data = $this->db->get_results( $this->db->prepare(
			"SELECT name, value FROM session_attribute WHERE sid = %s",
			$username
		), ARRAY_A );

		// Convert from [ name: "field", value: "value" ] to [ field: value ]
		$profile_data = array_column( $profile_data, 'value', 'name' );

		return compact(
			'ticket_subscriptions',
			'ticket_notifications',
			'ticket_reporter',
			'ticket_owner',
			'attachments',
			'comments',
			'profile_data'
		);
	}

	/**
	 * Anonymize a user on Trac.
	 *  - Switches their tickets, comments, and attachments to be owned by $to
	 *  - Removes Subscriptions & notification prefs
	 *  - Removes user Trac preferences
	 *
	 * The $to user doesn't have to be unique, but associated user-data (NOT content) of that user will be lost.
	 *
	 * @param string $from The user login of the user to anonymize.
	 * @param string $to   The new user login placeholder for the user, should be unique.
	 */
	function anonymize_user( $from, $to ) {
		$from = trim( $from );
		$to   = trim( $to );

		if ( empty( $from ) || empty( $to ) ) {
			return false;
		}

		// Perform rename first.
		if ( ! $this->rename_user( $from, $to ) ) {
			return false;
		}

		// Remove Trac sessions & preferences
		$this->db->delete( 'session', array( 'sid' => $from ) );
		$this->db->delete( 'session', array( 'sid' => $to ) );
		$this->db->delete( 'session_attribute', array( 'sid' => $from ) );
		$this->db->delete( 'session_attribute', array( 'sid' => $to ) );

		// Remove Authentication cookies (May not be applicable to WordPress.org trac)
		$this->db->delete( 'auth_cookie', array( 'name' => $from ) );
		$this->db->delete( 'auth_cookie', array( 'name' => $to ) );

		// Remove any Trac notification & subscription settings (May not be applicable to WordPress.org trac)
		$this->db->delete( 'notify_watch', array( 'sid' => $from ) );
		$this->db->delete( 'notify_watch', array( 'sid' => $to ) );
		$this->db->delete( 'notify_subscription', array( 'sid' => $from ) );
		$this->db->delete( 'notify_subscription', array( 'sid' => $to ) );

		// Remove subscriptions and notifications (Should all be owned by $to, but do $from just in case)
		$this->db->delete( '_ticket_subs',   array( 'username' => $from ) );
		$this->db->delete( '_ticket_subs',   array( 'username' => $to ) );
		$this->db->delete( '_notifications', array( 'username' => $from ) );
		$this->db->delete( '_notifications', array( 'username' => $to ) );

		return true;
	}

	/**
	 * Rename a user on Trac, can be used for username migrations and GDPR anonymization needs.
	 * 
	 * @param string $from The user login of the user to rename.
	 * @param string $to   The new user login that the items owned by $from will be reauthored to.
	 */
	function rename_user( $from, $to ) {
		$from = trim( $from );
		$to   = trim( $to );

		// Prevent data issues by ensuring that both are supplied.
		if ( empty( $from ) || empty( $to ) ) {
			return false;
		}

		// If the user has (or will have) specific permissions on the trac instance, bail.
		// If this needs to be bypassed, remove the user permissions first before migration.
		if ( $this->db->get_var( $this->db->prepare(
			"SELECT action FROM permission WHERE username IN( %s, %s )",
			$from,
			$to
		) ) ) {
			return false;
		}

		// Check for the user existing. If exists, delete old user prefs, else, migrate.
		$dest_user_exists = (bool) $this->db->get_var( $this->db->prepare(
			"SELECT sid FROM session WHERE sid = %s",
			$to
		) );

		// Trac Sessions & Prefs. If the $to user doesn't exist, migrate prefs, otherwise delete old prefs.
		if ( ! $dest_user_exists ) {
			$this->db->get_var( $this->db->prepare(
				"UPDATE session SET sid = %s WHERE sid = %s",
				$to,
				$from
			) );
			$this->db->get_var( $this->db->prepare(
				"UPDATE auth_cookie SET name = %s WHERE name = %s",
				$to,
				$from
			) );
			$this->db->get_var( $this->db->prepare(
				"UPDATE session_attribute SET sid = %s WHERE sid = %s",
				$to,
				$from
			) );
		} else {
			// Dest user existed, delete those data values instead.
			$this->db->delete( 'session', array( 'sid' => $from ) );
			$this->db->delete( 'auth_cookie', array( 'name' => $from ) );
			$this->db->delete( 'session_attribute', array( 'sid' => $from ) );
		}

		// Tickets, Attachments, and Comments.
		$this->db->get_var( $this->db->prepare(
			"UPDATE ticket SET reporter = %s WHERE reporter = %s",
			$to,
			$from
		) );

		$this->db->get_var( $this->db->prepare(
			"UPDATE ticket SET owner = %s WHERE owner = %s",
			$to,
			$from
		) );

		$this->db->get_var( $this->db->prepare(
			"UPDATE attachment SET author = %s WHERE author = %s",
			$to,
			$from
		) );

		$this->db->get_var( $this->db->prepare(
			"UPDATE ticket_change SET author = %s WHERE author = %s",
			$to,
			$from
		) );
	
		$this->db->get_var( $this->db->prepare(
			"UPDATE wiki SET author = %s WHERE author = %s",
			$to,
			$from
		) );

		// WordPress.org Subscriptions and notifications.
		$this->db->get_var( $this->db->prepare(
			"UPDATE _ticket_subs SET username = %s WHERE username = %s",
			$to,
			$from
		) );

		$this->db->get_var( $this->db->prepare(
			"UPDATE _notifications SET username = %s WHERE username = %s",
			$to,
			$from
		) );

		// DO NOT update the following tables:
		// - revision (SVN "cache")
		// - permission (Trac permissions)

		return true;
	}
}

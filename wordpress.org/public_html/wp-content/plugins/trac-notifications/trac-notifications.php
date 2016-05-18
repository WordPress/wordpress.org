<?php
/* Plugin Name: Trac Notifications
 * Description: Adds notifications endpoints for Trac, as well as notification and component management.
 * Author: Nacin
 * Version: 1.1
 */

require __DIR__ . '/autoload.php';

class wporg_trac_notifications {

	protected $trac;
	protected $components;

	protected $tracs_supported = array( 'core', 'meta', /* 'themes', 'plugins' */ );
	protected $tracs_supported_extra = array( /* 'bbpress', 'buddypress', 'gsoc', 'glotpress' */ );

	function __construct() {
		$make_site = explode( '/', home_url( '' ) );
		$trac = $make_site[3];
		if ( $make_site[2] !== 'make.wordpress.org' || ! in_array( $trac, $this->tracs_supported ) ) {
			return;
		}
		if ( 'core' === $trac && isset( $_GET['trac'] ) && in_array( $_GET['trac'], $this->tracs_supported_extra ) ) {
			$trac = $_GET['trac'];
		}

		$this->trac = $trac;

		if ( 'core' === $trac && function_exists( 'add_db_table' ) ) {
			$tables = array( 'ticket', '_ticket_subs', '_notifications', 'ticket_change', 'component', 'milestone', 'ticket_custom' );
			foreach ( $tables as $table ) {
				add_db_table( 'trac_' . $trac, $table );
			}
		}

		if ( 'core' === $trac ) {
			$this->api = new Trac_Notifications_DB( $GLOBALS['wpdb'] );
		} else {
			$this->api = new Trac_Notifications_HTTP_Client( $this->trac_url() . '/wpapi', TRAC_NOTIFICATIONS_API_KEY );
		}

		if ( 'core' === $trac ) {
			require __DIR__ . '/trac-components.php';
			$this->components = new Make_Core_Trac_Components( $this->api );
		}

		add_filter( 'allowed_http_origins', array( $this, 'filter_allowed_http_origins' ) );
		add_action( 'template_redirect', array( $this, 'action_template_redirect' ) );
		add_shortcode( 'trac-notifications', array( $this, 'notification_settings_page' ) );
	}

	function trac_url() {
		return 'https://' . $this->trac . '.trac.wordpress.org';
	}

	function filter_allowed_http_origins( $origins ) {
		$origins[] = $this->trac_url();
		return $origins;
	}

	function ticket_link( $ticket ) {
		$ticket = (object) $ticket;
		$status_res = $ticket->status;
		if ( $ticket->resolution ) {
			$status_res .= ': ' . $ticket->resolution;
		}
		return sprintf( '<a href="%s" class="%s ticket" title="%s">#%s</a>',
			$this->trac_url() . '/ticket/' . $ticket->id,
			$ticket->status,
			esc_attr( sprintf( "%s: %s (%s)", $ticket->type, $ticket->summary, $status_res ) ),
			$ticket->id );
	}

	function action_template_redirect() {
		if ( isset( $_POST['trac-ticket-sub'] ) ) {
			$this->trac_notifications_box_actions();
			exit;
		} elseif ( isset( $_POST['trac-ticket-subs'] ) ) {
			$this->trac_notifications_query_tickets();
			exit;
		} elseif ( isset( $_GET['trac-notifications'] ) ) {
			$this->trac_notifications_box_render();
			exit;
		}
	}

	function get_trac_focuses() {
		if ( 'core' === $this->trac ) {
			return array( 'accessibility', 'administration', 'docs', 'javascript', 'multisite', 'performance', 'rtl', 'template', 'ui' );
		}
		return array();
	}

	function make_components_tree( $components ) {
		$tree = array();
		$subcomponents = array(
			'Comments' => array( 'Pings/Trackbacks' ),
			'Editor' => array( 'Autosave', 'Press This', 'Quick/Bulk Edit', 'TinyMCE' ),
			'Formatting' => array( 'Charset', 'Shortcodes' ),
			'Media' => array( 'Embeds', 'Gallery', 'Upload' ),
			'Permalinks' => array( 'Canonical', 'Rewrite Rules' ),
			'Posts, Post Types' => array( 'Post Formats', 'Post Thumbnails', 'Revisions' ),
			'Themes' => array( 'Appearance', 'Widgets', 'Menus' ),
			'Users' => array( 'Role/Capability', 'Login and Registration' )
		);
		foreach ( $components as $component ) {
			if ( isset( $tree[ $component ] ) && false === $tree[ $component ] ) {
				continue;
			} elseif ( isset( $subcomponents[ $component ] ) ) {
				$tree[ $component ] = $subcomponents[ $component ];
				foreach ( $subcomponents[ $component ] as $subcomponent ) {
					$tree[ $subcomponent ] = false;
				}
			} else {
				$tree[ $component ] = true;
			}
		}
		$tree = array_filter( $tree );
		return $tree;
	}

	function trac_notifications_box_actions() {
		send_origin_headers();

		if ( ! is_user_logged_in() ) {
			wp_send_json_error();
		}

		$username = wp_get_current_user()->user_login;

		$ticket = absint( $_POST['trac-ticket-sub'] );
		if ( ! $ticket ) {
			wp_send_json_error();
		}

		if ( ! $this->api->get_trac_ticket( $ticket ) ) {
			wp_send_json_error();
		}

		$action = $_POST['action'];
		if ( ! $action ) {
			wp_send_json_error();
		}

		switch ( $action ) {
			case 'subscribe' :
			case 'block' :
				$status = $action === 'subscribe' ? 1 : 0;
				$result = $this->api->update_subscription( $username, $ticket, $status );
				break;

			case 'unsubscribe' :
			case 'unblock' :
				$status = $action === 'unsubscribe' ? 1 : 0;
				$result = $this->api->delete_subscription( $username, $ticket, $status );
				break;
		}

		if ( $result ) {
			wp_send_json_success();
		}

		wp_send_json_error();
	}

	function trac_notifications_query_tickets() {
		send_origin_headers();

		if ( ! is_user_logged_in() ) {
			exit;
		}
		$username = wp_get_current_user()->user_login;

		$queried_tickets = (array) $_POST['tickets'];
		if ( count( $queried_tickets ) > 100 ) {
			wp_send_json_error();
		}

		$subscribed_tickets = $this->api->get_trac_ticket_subscriptions_for_user( $username );
		if ( ! is_array( $subscribed_tickets ) ) {
			wp_send_json_error();
		}
		$tickets = array_intersect( $queried_tickets, $subscribed_tickets );
		$tickets = array_map( 'intval', array_values( $tickets ) );
		wp_send_json_success( array( 'tickets' => $tickets ) );
	}

	function trac_notifications_box_render() {
		send_origin_headers();

		if ( ! is_user_logged_in() ) {
			exit;
		}
		$username = wp_get_current_user()->user_login;

		$ticket_id = absint( $_GET['trac-notifications'] );
		if ( ! $ticket_id ) {
			exit;
		}

		$meta = $this->api->get_trac_notifications_info( $ticket_id, $username );
		if ( ! $meta ) {
			exit;
		}

		$ticket               = $meta['get_trac_ticket'];
		$focuses              = $meta['get_trac_ticket_focuses'];
		$notifications        = $meta['get_trac_notifications_for_user'];
		$ticket_sub           = $meta['get_trac_ticket_subscription_status_for_user'];
		$ticket_subscriptions = $meta['get_trac_ticket_subscriptions'];
		$participants         = $meta['get_trac_ticket_participants'];

		$focuses = explode( ', ', $focuses );
		$stars = $ticket_subscriptions['starred'];
		$star_count = count( $stars );

		$unblocked_participants = array_diff( $participants, $ticket_subscriptions['blocked'] );
		$all_receiving_notifications = array_unique( array_merge( $stars, $unblocked_participants ) );
		natcasesort( $all_receiving_notifications );

		$reasons = array();

		if ( $username == $ticket['reporter'] ) {
			$reasons['reporter'] = 'you reported this ticket';
		}
		if ( $username == $ticket['owner'] ) {
			$reasons['owner'] = 'you own this ticket';
		}
		if ( in_array( $username, $participants ) ) {
			$reasons['participant'] = 'you have commented';
		}

		$intersected_focuses = array();
		foreach ( $focuses as $focus ) {
			if ( ! empty( $notifications['focus'][ $focus ] ) ) {
				$intersected_focuses[] = $focus;
			}
		}
		if ( $intersected_focuses ) {
			if ( count( $intersected_focuses ) === 1 ) {
				$reasons['focus'] = sprintf( 'you have subscribed to the %s focus', $intersected_focuses[0] );
			} else {
				$reasons['focus'] = 'you have subscribed to the ' . wp_sprintf( '%l focuses', $intersected_focuses );
			}
		}

		if ( ! empty( $notifications['component'][ $ticket['component'] ] ) ) {
			$reasons['component'] = sprintf( 'you have subscribed to the %s component', $ticket['component'] );
		}
		if ( ! empty( $notifiations['milestone'][ $ticket['milestone'] ] ) ) {
			$reasons['milestone'] = sprintf( 'you have subscribed to the %s milestone', $ticket['milestone'] );
		}

		if ( 1 === $ticket_sub ) {
			$class = 'subscribed';
		} else {
			if ( null === $ticket_sub && $reasons ) {
				$class = 'block';
			} elseif ( 0 === $ticket_sub ) {
				$class = 'blocked';
			} else {
				$class = '';
			}
		}
		if ( $reasons ) {
			$class .= ' receiving';
		}

		if ( $star_count === 0 || $star_count === 1 ) {
			$class .= ' count-' . $star_count;
		}
		if ( ! empty( $_COOKIE['wp_trac_ngrid'] ) ) {
			$class .= ' show-usernames';
		}

		ob_start();
		?>
	<div id="notifications" class="<?php echo $class; ?>">
		<fieldset>
			<legend>Notifications</legend>
				<p class="star-this-ticket">
					<a href="#" class="button button-large watching-ticket"><span class="dashicons dashicons-star-filled"></span> Watching ticket</a>
					<a href="#" class="button button-large watch-this-ticket"><span class="dashicons dashicons-star-empty"></span> Watch this ticket</a>
					<span class="num-stars"><span class="count"><?php echo $star_count; ?></span> <span class="count-1">star</span> <span class="count-many">stars</span></span>
					<div class="star-list">
				<?php
					natcasesort( $stars ); foreach ( $stars as $follower ) :
					// foreach ( $all_receiving_notifications as $follower ) :
						if ( $username === $follower ) {
							continue;
						}
						$follower = esc_attr( $follower );
						$class = ''; // in_array( $follower, $stars, true ) ? ' class="star"' : '';
					?>
						<a<?php echo $class; ?> title="<?php echo $follower; ?>" href="//profiles.wordpress.org/<?php echo $follower; ?>">
							<?php echo get_avatar( get_user_by( 'login', $follower )->user_email, 36 ); ?>
							<span class="username"><?php echo $follower; ?></span>
						</a>
					<?php endforeach; ?>
					<a title="you" class="star-you" href="//profiles.wordpress.org/<?php echo esc_attr( $username ); ?>">
						<?php echo get_avatar( wp_get_current_user()->user_email, 36 ); ?>
						<span class="username"><?php echo $username; ?></span>
					</a>
					</div>
				</p>
				<p class="receiving-notifications">You are receiving notifications.</p>
			<?php if ( $reasons ) : ?>
				<p class="receiving-notifications-because">You are receiving notifications because <?php echo current( $reasons ); ?>. <a href="#" class="button button-small block-notifications">Block notifications</a></p>
			<?php endif ?>
				<p class="not-receiving-notifications">You do not receive notifications because you have blocked this ticket. <a href="#" class="button button-small unblock-notifications">Unblock</a></p>
				<span class="preferences"><span class="grid-toggle"><a href="#" class="grid dashicons dashicons-screenoptions"></a> <a href="#" class="names dashicons dashicons-exerpt-view dashicons-excerpt-view"></a></span> <a href="<?php echo home_url( 'notifications/' ); ?>">Preferences</a></span>
		</fieldset>
	</div>
	<?php
		$this->ticket_notes( $ticket, $username, $meta );
		$send = array( 'notifications-box' => ob_get_clean() );
		if ( isset( $this->components ) ) {
			$send['maintainers'] = $this->components->get_component_maintainers( $ticket['component'] );
		}
		wp_send_json_success( $send );
		exit;
	}

	function ticket_notes( $ticket, $username, $meta ) {
		if ( $username == $ticket['reporter'] ) {
			return;
		}

		$activity = $meta['get_reporter_last_activity'];

		if ( count( $activity['tickets'] ) >= 5 ) {
			return;
		}

		if ( 1 == count( $activity['tickets'] ) ) {
			$output = sprintf( '<strong>Make sure %s receives a warm welcome.</strong><br/>', $ticket['reporter'] );

			if ( ! empty( $activity['comments'] ) ) {
				$output .= 'They&#8217;ve commented before, but it&#8127;s their first ticket!';
			} else {
				$output .= 'It&#8127;s their first ticket!';
			}
		} else {
			$mapping = array( 2 => 'second', 3 => 'third', 4 => 'fourth' );

			$output = sprintf( '<strong>This is only %s&#8217;s %s ticket!</strong><br/>Previously:',
				$ticket['reporter'], $mapping[ count( $activity['tickets'] ) ] );

				foreach ( $activity['tickets'] as $t ) {
					if ( $t['id'] != $ticket['id'] ) {
						$output .= ' ' . $this->ticket_link( $t );
					}
				}
				$output .= '.';
		}

		echo '<p class="ticket-note note-new-reporter">';
		echo get_avatar( get_user_by( 'login', $ticket['reporter'] )->user_email, 36 );
		echo '<span class="note">' . $output . '</span>';
		echo '<span class="dashicons dashicons-welcome-learn-more"></span>';
	}

	function notification_settings_page() {
		if ( ! is_user_logged_in() ) {
			return 'Please <a href="//wordpress.org/support/bb-login.php">log in</a> to save your notification preferences.';
		}

		ob_start();
		$components = $this->api->get_components();
		$milestones = $this->api->get_milestones();
		$focuses = $this->get_trac_focuses();

		$username = wp_get_current_user()->user_login;
		$notifications = $this->api->get_trac_notifications_for_user( $username );

		if ( $_POST && isset( $_POST['trac-nonce'] ) ) {
			check_admin_referer( 'save-trac-notifications', 'trac-nonce' );

			$changes = array();

			foreach ( array( 'milestone', 'component', 'focus' ) as $type ) {
				if ( ! empty( $_POST['notifications'][ $type ] ) ) {
					foreach ( $_POST['notifications'][ $type ] as $value => $on ) {
						if ( empty( $notifications[ $type ][ $value ] ) ) {
							$changes['insert'][] = compact( 'username', 'type', 'value' );
							$notifications[ $type ][ $value ] = true;
						}
					}
				}

				foreach ( $notifications[ $type ] as $value => $on ) {
					if ( empty( $_POST['notifications'][ $type ][ $value ] ) ) {
						$changes['delete'][] = compact( 'username', 'type', 'value' );
						unset( $notifications[ $type ][ $value ] );
					}
				}
			}
			if ( empty( $_POST['notifications']['newticket'] ) && ! empty( $notifications['newticket'] ) ) {
				$changes['delete'][] = array( 'username' => $username, 'type' => 'newticket' );
				$notifications['newticket'] = false;
			} elseif ( ! empty( $_POST['notifications']['newticket'] ) && empty( $notifications['newticket'] ) ) {
				$changes['insert'][] = array( 'username' => $username, 'type' => 'newticket', 'value' => '1' );
				$notifications['newticket'] = true;
			}
			$this->api->update_notifications( $changes );
		}

		?>

		<style>
		#focuses, #components, #milestones, p.save-changes {
			clear: both;
		}
		#milestones, p.save-changes {
			padding-top: 1em;
		}
		#focuses li {
			display: inline-block !important;
			list-style: none;
			min-width: 15%;
			margin-right: 30px;
		}
		#components > ul {
			margin: 0 0 0 1% !important;
			margin: 0;
			padding: 0;
		}
		.make-core #components > ul {
			width: 24%;
			float: left;
		}
		#components > ul > li {
			list-style: none;
		}
		#milestones > ul > li {
			float: left;
			width: 25%;
			list-style: none;
		}
		.completed-milestone {
			display: none !important;
		}
		.completed-milestone.checked,
		#milestones.show-completed-milestones .completed-milestone {
			display: list-item !important;
		}
		</style>
		<script>
		jQuery(document).ready( function($) {
			$('#show-completed').on('click', 'a', function() {
				$('#show-completed').hide();
				$('#milestones').addClass( 'show-completed-milestones' );
				return false;
			});
			$('p.select-all').on('click', 'a', function() {
				$('#components').find('input[type=checkbox]').prop('checked', $(this).data('action') === 'select-all');
				return false;
			});
		});
		</script>
		<?php
		echo '<form method="post" action="">';
		wp_nonce_field( 'save-trac-notifications', 'trac-nonce', false );
		echo '<h3>New Tickets</h3>';
		$checked = checked( $notifications['newticket'], true, false );
		echo '<ul style="margin-left: 1% !important"><li style="list-style:none"><label><input type="checkbox" ' . $checked . 'name="notifications[newticket]" /> Receive a notification when new tickets are created.</label></li></ul>';

		if ( $focuses ) {
			echo '<div id="focuses">';
			echo '<h3>Focuses</h3>';
			echo '<ul>';
			foreach ( $focuses as $focus ) {
				$checked = checked( ! empty( $notifications['focus'][ $focus ] ), true, false );
				echo '<li><label><input type="checkbox" ' . $checked . 'name="notifications[focus][' . esc_attr( $focus ) . ']" /> ' . $focus . '</label></li>';
			}
			echo '</ul>';
			echo '</div>';
		}

		if ( $components ) {
			echo '<div id="components">';
			echo '<h3>Components</h3>';
			echo '<p class="select-all"><a href="#" data-action="select-all">select all</a> &bull; <a href="#" data-action="clear-all">clear all</a></p>';
			echo "<ul>\n";
			$components_tree = $this->make_components_tree( $components );
			$breakpoints = array( 'Export', 'Media', 'Script Loader' );
			foreach ( $components_tree as $component => $subcomponents ) {
				if ( in_array( $component, $breakpoints ) ) {
					echo '</ul><ul>';
				}
				$checked = checked( ! empty( $notifications['component'][ $component ] ), true, false );
				echo '<li><label><input type="checkbox" ' . $checked . 'name="notifications[component][' . esc_attr( $component ) . ']" /> ' . $component . "</label>\n";
				if ( is_array( $subcomponents ) ) {
					echo "<ul>\n";
					foreach ( $subcomponents as $subcomponent ) {
						$checked = checked( ! empty( $notifications['component'][ $subcomponent ] ), true, false );
						echo '<li><label><input type="checkbox" ' . $checked . 'name="notifications[component][' . esc_attr( $subcomponent ) . ']" /> ' . $subcomponent . "</label></li>\n";
					}
					echo "</ul>\n";
				}
				echo "</li>\n";
			}
			echo '</ul>';
			echo '</div>';
		}

		if ( $milestones ) {
			echo '<div id="milestones">';
			echo '<h3>Milestones</h3>';
			echo '<ul>';
			foreach ( $milestones as $milestone ) {
				$checked = checked( ! empty( $notifications['milestone'][ $milestone['name'] ] ), true, false );
				$class = '';
				if ( ! empty( $milestone['completed'] ) ) {
					$class = 'completed-milestone';
					if ( $checked ) {
						$class .= ' checked';
					}
					$class = ' class="' . $class . '"';
				}
				echo  '<li' . $class . '><label><input type="checkbox" ' . $checked . 'name="notifications[milestone][' . esc_attr( $milestone['name'] ) . ']" /> ' . $milestone['name'] . '</label></li>';
			}
			echo '<li id="show-completed"><a href="#">Show recently completed&hellip;</a></li>';
			echo '</ul>';
			echo '</div>';
		}

		echo '<p class="save-changes"><input type="submit" value="Save Changes" /></p>';
		echo '</form>';
		return ob_get_clean();
	}
}
$wporg_trac_notifications = new wporg_trac_notifications;

<?php

class Make_Core_Trac_Components {
	const last_x_days = 7;

	function __construct() {
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'the_content', array( $this, 'the_content' ), 5 );
		add_action( 'save_post_component', array( $this, 'save_post' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'wp_head' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		$this->trac = $GLOBALS['wpdb'];
	}

	function init() {
		add_shortcode( 'trac-select', array( $this, 'shortcode_select' ) );
		add_shortcode( 'logged-in', array( $this, 'shortcode_logged_in' ) );
		add_shortcode( 'logged-out', array( $this, 'shortcode_logged_in' ) );

		$labels = array(
			'name' => 'Component Pages',
			'menu_name' => 'Components',
			'singular_name' => 'Component Page',
			'add_new' => 'Add New Page',
			'add_new_item' => 'Add New Page',
			'edit_item' => 'Edit Component',
			'new_item' => 'New Page',
			'view_item' => 'View Component Page',
			'search_items' => 'Search Components',
			'not_found' => 'No components found.',
			'not_found_in_trash' => 'No components found in trash.',
			'parent_item_colon' => 'Parent Component:',
			'all_items' => 'All Components',
		);

		register_post_type( 'component', array(
			'public' => true,
			'show_ui' => true,
			'labels' => $labels,
			'capabilities' => array(
				'delete_published_posts' => 'manage_options',
			),
			'menu_icon' => 'dashicons-admin-generic',
			'menu_position' => 19,
			'capability_type' => 'post',
			'map_meta_cap' => true,
			'hierarchical' => true,
			'supports' => array( 'title', 'editor', 'page-attributes', 'revisions', 'author' ),
			'register_meta_box_cb' => array( $this, 'register_meta_box_cb' ),
			'has_archive' => true,
			'rewrite' => array(
				'slug' => 'components',
				'with_front' => false,
				'feeds' => true,
				'pages' => true,
			),
			'delete_with_user' => false,
		) );
	}

	function pre_get_posts( $query ) {
		if ( $query->is_main_query() && $query->is_post_type_archive( 'component' ) ) {
			$query->set( 'posts_per_page', -1 );
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'asc' );
		}
	}

	function page_is_component( $post ) {
		$post = get_post( $post );
		if ( $post->post_type != 'component' ) {
			return false;
		}
		if ( $post->post_parent == 0 ) {
			return true;
		}
		if ( get_post_meta( $post->ID, '_page_is_subcomponent', true ) ) {
			return true;
		}
		return false;
	}

	function register_meta_box_cb( $post ) {
		if ( $post->post_status !== 'auto-draft' ) {
			add_meta_box( 'component-settings', 'Settings', array( $this, 'meta_box_cb' ) );
		}
	}

	function meta_box_cb( $post ) {
		wp_nonce_field( 'component-settings_' . $post->ID, 'component-settings-nonce', false );
		if ( $post->post_parent != 0 ) {
			$checked = checked( (bool) get_post_meta( $post->ID, '_page_is_subcomponent', true ), true, false );
			echo '<p><label for="page-is-subcomponent"><input type="checkbox"' . $checked . ' name="page-is-subcomponent" id="page-is-subcomponent" /> This page is a subcomponent</label></p>';
		}
		if ( ! $this->page_is_component( $post ) ) {
			return;
		}
		$value = get_post_meta( $post->ID, '_active_maintainers', true );
		echo '<p><label for="active-maintainers">Active maintainers (WP.org usernames, comma-separated)</label> <input type="text" class="large-text" id="active-maintainers" name="active-maintainers" value="' . esc_attr( $value ) . '" />';
	}

	function save_post( $post_id, $post ) {
		if ( ! isset( $_POST['component-settings-nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( $_POST['component-settings-nonce'], 'component-settings_' . $post->ID ) ) {
			return;
		}

		if ( $post->post_parent != 0 ) {
			update_post_meta( $post->ID, '_page_is_subcomponent', isset( $_POST['page-is-subcomponent'] ) );
		}

		if ( isset( $_POST['active-maintainers'] ) ) {
			update_post_meta( $post->ID, '_active_maintainers', sanitize_text_field( wp_unslash( $_POST['active-maintainers'] ) ) );
		}
	}

	function admin_menu() {
		remove_submenu_page( 'edit.php?post_type=component', 'post-new.php?post_type=component' );
	}

	function wp_enqueue_scripts() {
		wp_enqueue_style( 'make-core-trac', plugins_url( '/make-core.css', __FILE__ ), array(), '3' );
	}

	function wp_head() {
		if ( ! is_singular( 'component' ) && ! is_post_type_archive( 'component' ) ) {
			return;
		}
?>
<style>
#toggle-compact-components { text-align: right }
.component-info .compact { display: none }
.compact-components .component-info { width: 49%; float: left; margin-right: 1% }
.compact-components .compact { display: inline }
.compact-components .trac-summary { display: none }
.postcontent { padding-left: 0; }
.component-info .meta .actions { display: none }
.component-info h3 { color: #333; font-size: 20px; margin: 30px 0 15px; font-weight: normal; }
.compact-components .component-info h3 { margin: 15px 0 10px; }
.trac-summary th, .trac-summary td { padding: 4px 8px; }
.trac-summary th { font-weight: bold; text-align: right }
.trac-summary th.title { font-weight: normal; text-align: left; font-size: 14px }
.trac-summary th a { font-weight: bold }
.trac-summary th.title a { color: #000; }
.trac-summary .count { text-align: right; min-width: 3em }
.trac-summary .zero { color: #ddd }
#main ul.maintainers, #main ul.followers { list-style: none; padding: 0; margin: 0 }
ul.maintainers li, ul.followers li { display: inline-block; line-height: 36px; margin-right: 20px; margin-bottom: 10px }
ul.maintainers img, ul.followers img { float: left; margin-right: 10px; }
#main ul.ticket-list { list-style: none; margin: 0; padding: 0 }
ul.ticket-list li { margin-bottom: 4px }
ul.ticket-list .focus { display: inline-block; border-radius: 3px; background: #eee; padding: 2px 6px; margin-right: 4px }
.history.growing:before, .history.shrinking:before { font-family: Dashicons; font-size: 30px; vertical-align: top }
.history.growing:before { content: "\f142"; color: red }
.history.shrinking:before { content: "\f140"; color: green }
.component-info .create-new-ticket { float: right; margin-top: 25px; }
</style>
<script>
jQuery( document ).ready( function( $ ) {
	$( '#toggle-compact-components input' ).on( 'change', function() {
		$( '#main' ).toggleClass( 'compact-components' );
	});
});
</script>
<?php
	}

	function the_content( $content ) {
		global $wpdb;

		$post = get_post();
		if ( ! $this->page_is_component( $post ) ) {
			return $content;
		}	

		ob_start();

		if ( ! is_singular() ) {
			$this->ticket_table( $post->post_title );
			return ob_get_clean();
		}

		if ( $post->post_parent ) {
			$top_level = '<h4>This is a subcomponent of the <a href="' . get_permalink( $post->post_parent ) . '">' . get_post( $post->post_parent )->post_title . '</a> component.</h4>';
			$content = $top_level . "\n\n" . $content;
		}

		$subcomponents_query = new WP_Query( array(
			'post_type' => 'component',
			'post_status' => 'publish',
			'post_parent' => $post->ID,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_key' => '_page_is_subcomponent',
			'meta_value' => '1',
		) );

		if ( $subcomponents_query->have_posts() ) {
			$subcomponents = array();
			foreach ( $subcomponents_query->posts as $subcomponent ) {
				$subcomponents[ $subcomponent->ID ] = '<a href="' . get_permalink( $subcomponent ) . '">' . $subcomponent->post_title . '</a>';
			}
			echo wp_sprintf( "<h4>Subcomponents: %l.</h4>", $subcomponents );
		}

		$recent_posts = new WP_Query( array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => 5,
			'tag_slug__in' => $post->post_name
		) );
		if ( $recent_posts->have_posts() ) {
			echo "<h3>Recent posts on the blog</h3>\n<ul>";
			while ( $recent_posts->have_posts() ) {
				$recent_posts->the_post();
				echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a> (' . get_the_date() . ")</li>\n";
			}
			echo '</ul>';
			echo 'View all posts tagged <a href="' . get_term_link( $post->post_name, 'post_tag' ) . '">' . $post->post_name . "</a>.\n\n";
			wp_reset_postdata();
		}

		$sub_pages = wp_list_pages( array( 'child_of' => $post->ID, 'post_type' => 'component', 'echo' => false, 'title_li' => false, 'exclude' => implode( ',', array_keys( $subcomponents ) ) ) );
		if ( $sub_pages ) {
			echo "<h3>Pages under " . get_the_title() . "</h3>\n";
			echo "<ul>$sub_pages</ul>";
			echo "\n\n";
		}

		$this->ticket_table( $post->post_title );

		$this->trac_content( $post->post_title );

		echo '<h3>Help maintain this component</h3>';

		$maintainers = get_post_meta( $post->ID, '_active_maintainers', true );
		if ( $maintainers ) {
			$maintainers = array_map( 'trim', explode( ',', $maintainers ) );
			echo 'Component maintainers: ';
			echo '<ul class="maintainers">';
			foreach ( $maintainers as $maintainer ) {
				echo '<li><a href="//profiles.wordpress.org/' . esc_attr( $maintainer ) . '">' . get_avatar( get_user_by( 'login', $maintainer )->user_email, 36 ) . "</a> $maintainer</li>";
			}
			echo "</ul>\n\n";
		}

		echo "\n" . "Many contributors help maintain one or more components. These maintainers are vital to keeping WordPress development running as smoothly as possible. They triage new tickets, look after existing ones, spearhead or mentor tasks, pitch new ideas, curate roadmaps, and provide feedback to other contributors. Longtime maintainers with a deep understanding of particular areas of core are always seeking to mentor others to impart their knowledge.\n\n";
		echo "<strong>Want to help? Start following this component!</strong> <a href='/core/notifications/'>Adjust your notifications here</a>. Feel free to dig into any ticket." . "\n\n";

		$followers = $this->trac->get_col( $this->trac->prepare( "SELECT username FROM _notifications WHERE type = 'component' AND value = %s", $post->post_title ) );
		$followers = "'" . implode( "', '", esc_sql( $followers ) ) . "'";
		$followers = $wpdb->get_results( "SELECT user_login, user_nicename, user_email FROM $wpdb->users WHERE user_login IN ($followers)" );
		if ( $followers ) {
			echo 'Contributors following this component:';
			echo '<ul class="followers">';
			foreach ( $followers as $follower ) {
				echo '<li><a title="' . esc_attr( $follower->user_login ) . '" href="//profiles.wordpress.org/' . esc_attr( $follower->user_nicename ) . '">';
				echo get_avatar( $follower->user_email, 36 ) . '</a></li>';
			}
			echo '</ul>';
		}

		$content .= "\n\n" . '<div class="component-info">' . ob_get_clean() . '</div>';
		return $content;
	}

	function ticket_table( $component ) {
		$type_filled = array_fill_keys( array( 'defect (bug)', 'enhancement', 'feature request', 'task (blessed)' ), 0 );

		$rows = wp_cache_get( 'trac_tickets_by_component_type_milestone' );
		if ( ! $rows ) {
			$rows = $this->trac->get_results( "SELECT component, type, milestone, count(*) as count FROM ticket
				WHERE status <> 'closed' GROUP BY component, type, milestone ORDER BY component, type, milestone" );
			wp_cache_add( 'trac_tickets_by_component_type_milestone', $rows, '', 300 );
		}

		$component_type_milestone = array();
		foreach ( $rows as $row ) {
			if ( empty( $component_type[ $row->component ] ) ) {
				$component_type[ $row->component ] = $type_filled;
			}
			$component_type[ $row->component ][ $row->type ] += $row->count;

			if ( empty( $component_milestone_type[ $row->component ][ $row->milestone ] ) ) {
				$component_milestone_type[ $row->component ][ $row->milestone ] = $type_filled;
			}
			$component_milestone_type[ $row->component ][ $row->milestone ][ $row->type ] += $row->count;
		}

		if ( is_singular() ) {
			echo '<div><a class="create-new-ticket button button-large button-primary" href="https://wordpress.org/support/bb-login.php?redirect_to=' . urlencode( 'https://core.trac.wordpress.org/newticket?component=' . urlencode( $component ) ) . '">Create a new ticket</a></div>';
		}

		if ( ! $component_count = array_sum( $component_type[ $component ] ) ) {
			if ( is_singular() ) {
				echo '<h3>No open tickets!</h3>';
			}
			return;
		}

		if ( is_singular() ) {
			echo '<h3>' . sprintf( _n( '%s open ticket', '%s open tickets', $component_count ), $component_count ) . ' in the ' . $component . ' component</h3>';
		}

		$history = $this->get_component_history( $component );
		$direction = '';
		if ( $history['change'] > 0 ) {
			$direction = ' growing';
		} elseif ( $history['change'] < 0 ) {
			$direction = ' shrinking';
		}
		$history_line = array();
		foreach ( $history as $action => $count ) {
			if ( ! $count || 'change' == $action ) {
				continue;
			}
			$history_line[] = $count . ' ' . $action;
		}

		$num_open_tickets_string = sprintf( _n( '%s open ticket', '%s open tickets', $component_count ), $component_count );

		$last_x = "<strong class='compact'>" . $num_open_tickets_string . ".</strong> Last " . self::last_x_days . " days: <span title='" . wp_sprintf( '%l', $history_line ) . "'>";
		$last_x .= sprintf( "%+d", $history['change'] ) . ' ' . _n( 'ticket', 'tickets', abs( $history['change'] ) );
		$last_x .= '<span class="history ' . $direction . '"></span></span>' . "\n\n";

		if ( ! is_singular() ) {
			echo $last_x;
		}
		echo '<table class="trac-summary">';
		echo '<tr><th class="title">' . $this->trac_query_link( $num_open_tickets_string, array( 'component' => $component ) ) . '</th>';
		foreach ( $component_type[ $component ] as $type => $count ) {
			if ( $count ) {
				echo '<th>' . $this->trac_query_link( $type, array( 'component' => $component, 'type' => $type, 'group' => 'milestone' ) ) . '</th>';
			}
		}
		echo '</tr>';
		foreach ( $component_milestone_type[ $component ] as $milestone => $type_count ) {
			echo '<tr><th>' . $this->trac_query_link( $milestone, array( 'component' => $component, 'milestone' => $milestone, 'group' => $type ) ) . '</th>';
			foreach ( $type_count as $type => $count ) {
				if ( $component_type[ $component ][ $type ] ) {
					if ( $count ) {
						echo '<td class="count">' . $this->trac_query_link( $count, compact( 'component', 'milestone', 'type' ) ) . '</td>';
					} else {
						echo '<td class="count zero">0</td>';
					}
				}
			}
			echo '</tr>';
		}
		echo "</table>\n\n";
		if ( is_singular() ) {
			echo $last_x;
		}
	}

	function trac_content( $component ) {
		if ( $unreplied_tickets = $this->trac->get_results( $this->trac->prepare( "SELECT id, summary, status, resolution, milestone FROM ticket t WHERE id NOT IN (SELECT ticket FROM ticket_change WHERE ticket = t.id AND t.reporter <> author AND field = 'comment' AND newvalue <> '') AND status <> 'closed' AND component = %s", $component ) ) ) {
			$count = count( $unreplied_tickets );
			echo '<h3>' . sprintf( _n( '%d ticket that has no replies', '%d tickets that have no replies', $count ), $count ) . '</h3>';
			echo '<a href="' . $this->trac_query( array( 'component' => $component, 'id' => implode( ',', wp_list_pluck( $unreplied_tickets, 'id' ) ) ) ) . '">View list on Trac</a>';
			$this->render_tickets( $unreplied_tickets );
		}

		$next_milestone = $this->trac->get_results( $this->trac->prepare( "SELECT id, summary, status, resolution, milestone, value as focuses FROM ticket t
			LEFT JOIN ticket_custom c ON c.ticket = t.id AND c.name = 'focuses' WHERE component = %s AND status <> 'closed' AND milestone LIKE '_._'", $component ) );
		if ( $next_milestone ) {
			$count = count( $next_milestone );
			echo '<h3>' . sprintf( _n( '%s ticket slated for ' . $next_milestone[0]->milestone, '%s tickets slated for ' . $next_milestone[0]->milestone, $count ), $count ) . '</h3>';
			echo $this->trac_query_link( 'View list in Trac', array( 'component' => $component, 'milestone' => $next_milestone[0]->milestone ) );
			$this->render_tickets( $next_milestone );
		}

		return; // Ditch the rest for now.

		$tickets_by_type = $this->trac->get_results( $this->trac->prepare( "SELECT type, COUNT(*) as count FROM ticket WHERE component = %s AND status <> 'closed' GROUP BY type", $component ), OBJECT_K );
		foreach ( $tickets_by_type as &$object ) {
			$object = $object->count;
		}
		unset( $object );

		$count = array_sum( $tickets_by_type );
		echo '<h3>' . sprintf( _n( '%s open ticket', '%s open tickets', $count ), $count ) . '</h3>';
		echo "\n" . '<strong>Open bugs: ' . $tickets_by_type['defect (bug)'] . '</strong>. ';
		echo $this->trac_query_link( 'View list on Trac', array( 'component' => $component, 'type' => 'defect (bug)' ) );
		echo "\n\n";

		if ( $enhancements = $this->trac->get_results( $this->trac->prepare( "SELECT id, summary, status, resolution, milestone FROM ticket WHERE component = %s AND status <> 'closed' AND type = %s", $component, 'enhancement' ) ) ) {
			printf( '<h3>Open enhancements (%d)</h3>', count( $enhancements ) );
			echo $this->trac_query_link( 'View list on Trac', array( 'component' => $component, 'type' => 'enhancement' ) );
			$this->render_tickets( $enhancements );
		}

		if ( $tasks = $this->trac->get_results( $this->trac->prepare( "SELECT id, summary, status, resolution, milestone FROM ticket WHERE component = %s AND status <> 'closed' AND type = %s", $component, 'task (blessed)' ) ) ) {
			printf( '<h3>Open tasks (%d)</h3>', count( $tasks ) );
			echo $this->trac_query_link( 'View list on Trac', array( 'component' => $component, 'type' => 'task (blessed)' ) );
			$this->render_tickets( $tasks );
		}

		if ( $feature_requests = $this->trac->get_results( $this->trac->prepare( "SELECT id, summary, status, resolution, milestone FROM ticket WHERE component = %s AND status <> 'closed' AND type = %s", $component, 'feature request' ) ) ) {
			printf( '<h3>Open feature requests (%d)</h3>', count( $feature_requests ) );
			echo $this->trac_query_link( 'View list on Trac', array( 'component' => $component, 'type' => 'feature request' ) );
			$this->render_tickets( $feature_requests );
		}
	}

	function trac_query_link( $text, $args ) {
		return '<a href="' . $this->trac_query( $args ) . '">' . $text . '</a>';
	}

	function trac_query( $args ) {
		$args = array_map( 'urlencode', $args );
		if ( ! isset( $args['status'] ) ) {
			$args['status'] = '!closed';
		}
		return add_query_arg( $args, 'https://core.trac.wordpress.org/query' );
	}

	function render_tickets( $tickets ) {
		echo '<ul class="ticket-list">';
		foreach ( $tickets as $ticket ) {
			echo '<li><a href="https://core.trac.wordpress.org/ticket/' . $ticket->id . '">#' . $ticket->id . '</a> &nbsp;' . esc_html( $ticket->summary );
			if ( ! empty( $ticket->focuses ) ) {
				echo ' <span class="focus">' . implode( '</span> <span class="focus">', explode( ', ', esc_html( $ticket->focuses ) ) ) . '</span>';
			}
			echo "</li>\n";
		}
		echo '</ul>';
	}

	function get_component_history( $component ) {
		$days_ago = ( time() - ( DAY_IN_SECONDS * self::last_x_days ) ) * 1000000;
		$closed_reopened = $this->trac->get_results( $this->trac->prepare( "SELECT newvalue, COUNT(DISTINCT ticket) as count
			FROM ticket_change tc INNER JOIN ticket t ON tc.ticket = t.id
			WHERE field = 'status' AND (newvalue = 'closed' OR newvalue = 'reopened')
			AND tc.time >= %s AND t.component = %s GROUP BY newvalue", $days_ago, $component ), OBJECT_K );
		$reopened = isset( $closed_reopened['reopened'] ) ? $closed_reopened['reopened']->count : 0;
		$closed = isset( $closed_reopened['closed'] ) ? $closed_reopened['closed']->count : 0;
		$opened = $this->trac->get_var( $this->trac->prepare( "SELECT COUNT(DISTINCT id) FROM ticket WHERE time >= %s AND component = %s", $days_ago, $component ) );
		$assigned_unassigned = $this->trac->get_results( $this->trac->prepare( "SELECT IF(newvalue = %s, 'assigned', 'unassigned') as direction,
			COUNT(*) as count FROM ticket_change WHERE field = 'component' AND ( oldvalue = %s OR newvalue = %s ) AND time >= %s GROUP BY direction",
			$component, $component, $component, $days_ago ), OBJECT_K );
		$assigned = isset( $assigned_unassigned['assigned'] ) ? $assigned_unassigned['assigned']->count : 0;
		$unassigned = isset( $assigned_unassigned['unassigned'] ) ? $assigned_unassigned['unassigned']->count : 0;

		$change = $opened + $reopened + $assigned - $closed - $unassigned;
		return compact( 'change', 'opened', 'reopened', 'closed', 'assigned', 'unassigned' );
	}

	function shortcode_logged_in( $attr, $content, $tag ) {
		if ( is_user_logged_in() == ( $tag == 'logged-in' ) ) {
			return $content;
		}
		return '';
	}

	function shortcode_select( $attr ) {
		ob_start();

		$topics = explode( ' ', $attr[0] );
		$both = in_array( 'focus', $topics ) && in_array( 'component', $topics );

		echo '<select class="tickets-by-topic" data-location="https://core.trac.wordpress.org/">';
		if ( $both ) {
			$default = 'Select a focus or component';
		} elseif ( in_array( 'focus', $topics ) ) {
			$default = 'Select a focus';
		} else {
			$default = 'Select a component';
		}
		echo '<option value="" selected="selected">' . $default . '</option>';
		if ( in_array( 'focus', $topics ) ) {
			$focuses = array( 'accessibility', 'administration', 'docs', 'javascript', 'multisite', 'performance', 'rtl', 'template', 'ui' );
			foreach ( $focuses as $focus ) {
				echo '<option value="focus/' . $focus . '">' . $focus . ( $both ? ' (focus)' : '' ) . '</option>';
			}
		}
		if ( $both ) {
			echo '<option></option>';
		}
		if ( in_array( 'component', $topics ) ) {
			$components = $this->trac->get_col( "SELECT name FROM component" );
			foreach ( $components as $component ) {
				echo '<option value="component/' . esc_attr( urlencode( $component ) ) . '">' . esc_html( $component ) . "</option>";
			}
		}
		echo '</select>';
		return ob_get_clean();
	}
}
new Make_Core_Trac_Components;


<?php

class Make_Core_Trac_Components {
	const last_x_days = 7;

	const POST_TYPE_NAME = 'component';

	protected $trac;

	protected $tracs_supported = array( 'core', 'meta' );

	function __construct( $api ) {
		$make_site = explode( '/', home_url( '' ) );
		$trac = $make_site[3];
		if ( $make_site[2] !== 'make.wordpress.org' || ! in_array( $trac, $this->tracs_supported ) ) {
			return;
		}

		$this->trac = $trac;
		$this->api  = $api;

		add_action( 'init', array( $this, 'init' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'the_content', array( $this, 'the_content' ), 5 );
		add_action( 'save_post_component', array( $this, 'save_post' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'wp_head' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );
		add_action( 'component_table_row', array( $this, 'component_table_row' ) );
		add_filter( 'manage_component_posts_columns', array( $this, 'manage_posts_columns' ) );
		add_action( 'manage_component_posts_custom_column', array( $this, 'manage_posts_custom_column' ), 10, 2 );
		add_filter( 'wp_nav_menu_objects', array( $this, 'highlight_menu_component_link' ) );
		add_filter( 'map_meta_cap', [ $this, 'map_meta_cap' ], 10, 4 );
	}

	function trac_url() {
		return 'https://' . $this->trac . '.trac.wordpress.org';
	}

	function trac_name() {
		return ucfirst( $this->trac );
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

		register_post_type( self::POST_TYPE_NAME, array(
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
		if ( $query->is_main_query() && $query->is_post_type_archive( self::POST_TYPE_NAME ) ) {
			$query->set( 'posts_per_page', -1 );
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'asc' );
			$query->set( 'order_components', true );
			add_action( 'the_posts', array( $this, 'order_components_into_tree' ), 10, 2 );
		}
	}

	function order_components_into_tree( $posts, $query ) {
		if ( ! $query->get( 'order_components' ) ) {
			return $posts;
		}
		// Poor man's hierarchy sort
		$parents = array_filter( wp_list_pluck( $posts, 'post_parent' ) );
		$new_ordering = array();
		foreach ( $posts as $post ) {
			if ( $post->post_parent ) {
				continue;
			}
			$new_ordering[] = $post;
			if ( in_array( $post->ID, $parents ) ) {
				foreach ( wp_list_filter( $posts, array( 'post_parent' => $post->ID ) ) as $child ) {
					$new_ordering[] = $child;
				}
			}
		}
		return $new_ordering;
		return $posts;
	}

	function page_is_component( $post ) {
		$post = get_post( $post );
		if ( ! $post ) {
			return false;
		}
		if ( $post->post_type != self::POST_TYPE_NAME ) {
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

	function manage_posts_columns( $columns ) {
		return array_merge(
			array_slice( $columns, 0, 2 ),
			array( 'maintainers' => 'Maintainers' ),
			array_slice( $columns, 2 )
		);
	}

	function manage_posts_custom_column( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'maintainers' :
				echo esc_html( get_post_meta( $post_id, '_active_maintainers', true ) );
				break;
		}
	}

	/**
	 * Highlights a menu link to the components home page when on any constituent
	 * component page.
	 *
	 * @see WPorg_Handbook::highlight_menu_handbook_link()
	 *
	 * @param array $menu_items Array of sorted menu items.
	 * @return array
	 */
	public function highlight_menu_component_link( $menu_items ) {
		// Must be on a component archive or page.
		if ( ! is_post_type_archive( self::POST_TYPE_NAME ) && ! is_singular( self::POST_TYPE_NAME ) ) {
			return $menu_items;
		}

		// Menu must not have an item that is already noted as being current.
		$current_menu_item = wp_filter_object_list( $menu_items, array( 'current' => true ) );
		if ( $current_menu_item ) {
			return $menu_items;
		}

		$post_type_data = get_post_type_object( self::POST_TYPE_NAME );
		$post_type_slug = $post_type_data->rewrite['slug'];

		$page = get_page_by_path( $post_type_slug );
		if ( ! $page ) {
			return $menu_items;
		}

		$components_menu_item = wp_filter_object_list( $menu_items, array( 'object_id' => $page->ID ) );
		if ( ! $components_menu_item ) {
			return $menu_items;
		}

		// Add current-menu-item class to the components menu item.
		reset( $components_menu_item );
		$components_item_index = key( $components_menu_item );
		$menu_items[ $components_item_index ]->classes[] = 'current-menu-item';

		return $menu_items;
	}

	/**
	 * Allows component maintainers to edit their components if they are at least a Contributor.
	 *
	 * @param array  $required_caps The user's actual capabilities.
	 * @param string $cap           Capability name.
	 * @param int    $user_id       The user ID.
	 * @param array  $context       Context to the cap. Typically the object ID.
	 * @return array Primitive caps.
	 */
	public function map_meta_cap( $required_caps, $cap, $user_id, $context ) {
		if ( $user_id && in_array( $cap, [ 'edit_post', 'publish_post', 'edit_others_posts' ], true ) ) {
			if ( empty( $context[0] ) ) {
				$context[0] = isset( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0;
			}

			if ( 'component' === get_post_type( $context[0] ) ) {
				$user_name   = get_user_by( 'id', $user_id )->user_login;
				$maintainers = array_map( 'trim', explode( ',', get_post_meta( $context[0], '_active_maintainers', true ) ) );

				if ( in_array( $user_name, $maintainers, true ) ) {
					$required_caps = ['edit_posts'];
				}
			}
		}

		return $required_caps;
	}

	function wp_enqueue_scripts() {
		wp_enqueue_style( 'make-core-trac', plugins_url( '/make-core.css', __FILE__ ), array(), 5 );
	}

	function wp_head() {
		if ( ! is_singular( self::POST_TYPE_NAME ) && ! is_post_type_archive( self::POST_TYPE_NAME ) ) {
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
.trac-summary tbody th { border-bottom: 1px solid #eee; border-right: 1px solid #eee; }
.trac-summary tbody tr:last-child th { border-bottom: none; }
.trac-summary th.title { font-weight: normal; text-align: left; font-size: 14px }
.trac-summary th a { font-weight: bold }
.trac-summary th.title a { color: #000; }
.trac-summary .count { text-align: right; border-right: none; width: auto; }
.trac-summary .zero { color: #ddd }
#main ul.maintainers, #main ul.followers { list-style: none; padding: 0; margin: 0 }
ul.maintainers li, ul.followers li { display: inline-block; line-height: 36px; margin-right: 20px; margin-bottom: 10px }
ul.maintainers img, ul.followers img { float: left; margin-right: 10px; }
#main ul.ticket-list { list-style: none; margin: 0; padding: 0 }
ul.ticket-list li { margin-bottom: 4px }
ul.ticket-list .focus { display: inline-block; border-radius: 3px; background: #eee; padding: 2px 6px; margin-right: 4px }
.history.growing:before, .history.shrinking:before { font-family: Dashicons; font-size: 30px; vertical-align: middle; line-height: 15px; }
.history.growing:before { content: "\f142"; color: red }
.history.shrinking:before { content: "\f140"; color: green }
td.right { text-align: right; }
body.post-type-archive-component table td { vertical-align: middle; }
td.maintainers { padding-top: 4px; padding-bottom: 4px; height: 26px; }
td.maintainers img.avatar { margin-right: 5px; }
.component-info .create-new-ticket { float: right; margin-top: 25px; }
</style>
<script>
jQuery( function( $ ) {
	$( '#toggle-compact-components' ).on( 'change', 'input', function() {
		$( '#main' ).toggleClass( 'compact-components' );
	});
});
</script>
<?php
	}

	function the_content( $content ) {
		global $wpdb;

		$post = get_post();
		if ( ! $this->page_is_component( $post ) || ! in_the_loop() || doing_action( 'wp_head' ) || ! did_action( 'wp_head' ) ) {
			return $content;
		}

		$component = str_replace( '&amp;', '&', $post->post_title );

		ob_start();

		if ( ! is_singular() ) {
			$this->ticket_table( $component );
			return ob_get_clean();
		}

		if ( $post->post_parent ) {
			$top_level = '<h4>This is a subcomponent of the <a href="' . get_permalink( $post->post_parent ) . '">' . get_post( $post->post_parent )->post_title . '</a> component.</h4>';
			$content = $top_level . "\n\n" . $content;
		}

		$subcomponents_query = new WP_Query( array(
			'post_type' => self::POST_TYPE_NAME,
			'post_status' => 'publish',
			'post_parent' => $post->ID,
			'update_post_term_cache' => false,
			'update_post_meta_cache' => false,
			'meta_key' => '_page_is_subcomponent',
			'meta_value' => '1',
		) );

		$subcomponents = array();
		if ( $subcomponents_query->have_posts() ) {
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
			echo "<h3>Recent posts on the make/{$this->trac} blog</h3>\n<ul>";
			while ( $recent_posts->have_posts() ) {
				$recent_posts->the_post();
				echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a> (' . get_the_date() . ")</li>\n";
			}
			echo '</ul>';
			echo 'View all posts tagged <a href="' . get_term_link( $post->post_name, 'post_tag' ) . '">' . $post->post_name . "</a>.\n\n";
			wp_reset_postdata();
		}

		switch_to_blog( 34 ); // Blog ID of make/test
		$flow_posts = new WP_Query( array(
			'post_type' => 'post',
			'post_status' => 'publish',
			'posts_per_page' => 5,
			'tag_slug__in' => $post->post_name
		) );
		if ( $flow_posts->have_posts() ) {
			echo "<h3>Recent posts on the make/test blog</h3>\n<ul>";
			while ( $flow_posts->have_posts() ) {
				$flow_posts->the_post();
				echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a> (' . get_the_date() . ")</li>\n";
			}
			echo '</ul>';
			echo 'View all posts tagged <a href="' . get_term_link( $post->post_name, 'post_tag' ) . '">' . $post->post_name . "</a>.\n\n";
			wp_reset_postdata();
		}
		restore_current_blog();

		$sub_pages = wp_list_pages( array( 'child_of' => $post->ID, 'post_type' => self::POST_TYPE_NAME, 'echo' => false, 'title_li' => false, 'exclude' => implode( ',', array_keys( $subcomponents ) ) ) );
		if ( $sub_pages ) {
			echo "<h3>Pages under " . get_the_title() . "</h3>\n";
			echo "<ul>$sub_pages</ul>";
			echo "\n\n";
		}

		$this->ticket_table( $component );

		$this->trac_content( $component );

		echo '<h3>Help maintain this component</h3>';

		$maintainers = get_post_meta( $post->ID, '_active_maintainers', true );
		if ( $maintainers ) {
			$maintainers = array_map( 'trim', explode( ',', $maintainers ) );
			echo 'Component maintainers: ';
			echo '<ul class="maintainers">';
			foreach ( $maintainers as $maintainer ) {
				$maintainer = get_user_by( 'login', $maintainer );
				if ( ! $maintainer ) {
					continue;
				}

				printf( '<li><a href="//profiles.wordpress.org/%s/">%s %s</a></li>',
					esc_attr( $maintainer->user_nicename ),
					get_avatar( $maintainer->user_email, 36 ),
					$maintainer->display_name ?: $maintainer->user_login
				);
			}
			echo "</ul>\n\n";
		}

		echo "\n" . "Many contributors help maintain one or more components. These maintainers are vital to keeping WordPress development running as smoothly as possible. They triage new tickets, look after existing ones, spearhead or mentor tasks, pitch new ideas, curate roadmaps, and provide feedback to other contributors. Longtime maintainers with a deep understanding of particular areas of {$this->trac_name()} are always seeking to mentor others to impart their knowledge.\n\n";
		echo "<strong>Want to help? Start following this component!</strong> <a href='/{$this->trac}/notifications/'>Adjust your notifications here</a>. Feel free to dig into any ticket." . "\n\n";

		$followers = $this->api->get_component_followers( $component );
		if ( $followers ) {
			$followers = "'" . implode( "', '", esc_sql( $followers ) ) . "'";
			$followers = $wpdb->get_results( "SELECT user_login, user_nicename, user_email FROM $wpdb->users WHERE user_login IN ($followers)" );
		}
		if ( $followers ) {
			echo 'Contributors following this component:';
			echo '<ul class="followers">';
			foreach ( $followers as $follower ) {
				echo '<li><a title="' . esc_attr( $follower->user_login ) . '" href="//profiles.wordpress.org/' . esc_attr( $follower->user_nicename ) . '/">';
				echo get_avatar( $follower->user_email, 36 ) . '</a></li>';
			}
			echo '</ul>';
		}

		$content .= "\n\n" . '<div class="component-info">' . ob_get_clean() . '</div>';
		return $content;
	}


	function generate_component_breakdowns() {
		if ( isset( $this->breakdown_component_type, $this->breakdown_component_milestone_type, $this->breakdown_component_unreplied ) ) {
			return true;
		}

		$type_filled = array_fill_keys( array( 'defect (bug)', 'enhancement', 'feature request', 'task (blessed)' ), 0 );
		$rows = wp_cache_get( 'trac_tickets_by_component_type_milestone' );
		if ( ! $rows ) {
			$rows = $this->api->get_tickets_by_component_type_milestone();
			if ( ! $rows ) {
				return false; // API error.
			}
			wp_cache_add( 'trac_tickets_by_component_type_milestone', $rows, '', 300 );
		}

		foreach ( $rows as $row ) {
			$row = (object) $row;
			if ( empty( $component_type[ $row->component ] ) ) {
				$component_type[ $row->component ] = $type_filled;
			}
			$component_type[ $row->component ][ $row->type ] += $row->count;

			if ( empty( $component_milestone_type[ $row->component ][ $row->milestone ] ) ) {
				$component_milestone_type[ $row->component ][ $row->milestone ] = $type_filled;
			}
			$component_milestone_type[ $row->component ][ $row->milestone ][ $row->type ] += $row->count;
		}

		$component_unreplied = wp_cache_get( 'trac_tickets_by_component_unreplied' );
		if ( ! $component_unreplied ) {
			$component_unreplied = $this->api->get_unreplied_ticket_counts_by_component();
			wp_cache_add( 'trac_tickets_by_component_unreplied', $component_unreplied, '', 300 );
		}

		$this->breakdown_component_type = $component_type;
		$this->breakdown_component_milestone_type = $component_milestone_type;
		$this->breakdown_component_unreplied = $component_unreplied;

		return true;
	}

	function ticket_table( $component ) {
		$result = $this->generate_component_breakdowns();
		if ( ! $result ) {
			return;
		}

		$component_type = $this->breakdown_component_type;
		$component_milestone_type = $this->breakdown_component_milestone_type;
		$component_count = isset( $component_type[ $component ] ) ? array_sum( $component_type[ $component ] ) : 0;

		if ( is_singular() ) {
			echo '<div><a class="create-new-ticket button button-large button-primary" href="https://login.wordpress.org/?redirect_to=' . urlencode( $this->trac_url() . '/newticket?component=' . urlencode( $component ) ) . '" rel="nofollow">Create a new ticket</a></div>';
		}

		if ( ! $component_count ) {
			if ( is_singular() ) {
				echo '<h3>No open tickets!</h3>';
			}
			return;
		}

		if ( is_singular() ) {
			echo '<h3>' . sprintf( _n( '%s open ticket', '%s open tickets', $component_count ), $component_count ) . ' in the ' . $component . ' component</h3>';
		}

		$history = $this->api->get_component_history( $component, self::last_x_days );
		if ( ! $history ) {
			$history = array( 'change' => 0 ); // Incorrect, but allows full page render.
		}

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
		echo '<thead><tr><th class="title">' . $this->trac_query_link( $num_open_tickets_string, array( 'component' => $component ) ) . '</th>';
		foreach ( $component_type[ $component ] as $type => $count ) {
			if ( $count ) {
				echo '<th>' . $this->trac_query_link( $type, array( 'component' => $component, 'type' => $type, 'group' => 'milestone' ) ) . '</th>';
			}
		}
		echo '</tr></thead>';
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
		$unreplied_tickets = $this->api->get_unreplied_tickets_by_component( $component );

		if ( $unreplied_tickets ) {
			$count = count( $unreplied_tickets );
			echo '<h3>' . sprintf( _n( '%d ticket that has no replies', '%d tickets that have no replies', $count ), $count ) . '</h3>';
			echo '<a href="' . $this->trac_query( array( 'component' => $component, 'id' => implode( ',', wp_list_pluck( $unreplied_tickets, 'id' ) ) ) ) . '">View list on Trac</a>';
			$this->render_tickets( $unreplied_tickets );
		}

		$next_milestone = $this->api->get_tickets_in_next_milestone( $component );

		if ( $next_milestone ) {
			$count = count( $next_milestone );
			$next_milestone_object = (object) $next_milestone[0];
			echo '<h3>' . sprintf( _n( '%s ticket slated for ' . $next_milestone_object->milestone, '%s tickets slated for ' . $next_milestone_object->milestone, $count ), $count ) . '</h3>';
			echo $this->trac_query_link( 'View list in Trac', array( 'component' => $component, 'milestone' => $next_milestone_object->milestone ) );
			$this->render_tickets( $next_milestone );
		}

		$tickets_by_type = (array) $this->api->get_ticket_counts_for_component( $component );

		$count = array_sum( $tickets_by_type );
		echo '<h3>' . sprintf( _n( '%s open ticket', '%s open tickets', $count ), $count ) . '</h3>';

		$types = array(
			'enhancement'     => 'Open enhancements',
			'task (blessed)'  => 'Open tasks',
			'feature request' => 'Open feature requests',
		);

		foreach ( $types as $type => $title ) {
			$count = $tickets_by_type[ $type ] ?? 0;
			printf( '<strong>%s: %d<strong> ', $title, $count );
			echo $this->trac_query_link( 'View list on Trac', compact( 'component', 'type' ) );
			echo '<br>';
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
		return add_query_arg( $args, $this->trac_url() . '/query' );
	}

	function render_tickets( $tickets ) {
		echo '<ul class="ticket-list">';
		foreach ( $tickets as $ticket ) {
			$ticket = (object) $ticket;
			echo '<li><a href="' . $this->trac_url() . '/ticket/' . $ticket->id . '">#' . $ticket->id . '</a> &nbsp;' . esc_html( $ticket->summary );
			if ( ! empty( $ticket->focuses ) ) {
				echo ' <span class="focus">' . implode( '</span> <span class="focus">', explode( ', ', esc_html( $ticket->focuses ) ) ) . '</span>';
			}
			echo "</li>\n";
		}
		echo '</ul>';
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

		echo '<select class="tickets-by-topic" data-location="' . $this->trac_url() . '/">';
		if ( $both ) {
			$default = 'Select a focus or component';
		} elseif ( in_array( 'focus', $topics ) ) {
			$default = 'Select a focus';
		} else {
			$default = 'Select a component';
		}
		echo '<option value="" selected="selected">' . $default . '</option>';
		if ( in_array( 'focus', $topics ) ) {
			$focuses = array( 'accessibility', 'admin', 'coding-standards', 'css', 'docs', 'javascript', 'multisite', 'performance', 'php-compatibility', 'privacy', 'rest-api', 'rtl', 'sustainability', 'template', 'ui', 'ui-copy' );
			
			foreach ( $focuses as $focus ) {
				echo '<option value="focus/' . esc_attr( rawurlencode( $focus ) ) . '">' . $focus . ( $both ? ' (focus)' : '' ) . '</option>';
			}
		}
		if ( $both ) {
			echo '<option></option>';
		}
		if ( in_array( 'component', $topics ) ) {
			$components = $this->api->get_components();
			if ( $components ) {
				foreach ( $components as $component ) {
					echo '<option value="component/' . esc_attr( rawurlencode( $component ) ) . '">' . esc_html( $component ) . "</option>";
				}
			}
		}
		echo '</select>';
		return ob_get_clean();
	}

	function component_table_row( $post ) {
		$result = $this->generate_component_breakdowns();
		if ( ! $result ) {
			return;
		}

		$component = str_replace( '&amp;', '&', $post->post_title );

		$history = $this->api->get_component_history( $component );

		if ( ! $history ) {
			return;
		}

		static $once = true;
		if ( $once ) {
			$once = false;
			echo '<thead><tr><td>Component</td><td>Tickets</td><td>7 Days</td><td>0&nbsp;Replies</td><td>Maintainers</td></tr></thead>';
		}

		$arrow = '';
		if ( $history['change'] ) {
			$direction = $history['change'] > 0 ? 'growing' : 'shrinking';
			$arrow = '<span class="history ' . $direction . '"></span>';
		}

		echo '<tr>';
		if ( $post->post_parent ) {
			echo '<td>&mdash; <a href="' . get_permalink() . '">' . $post->post_title . '</a></td>';
		} else {
			echo '<td><a href="' . get_permalink() . '"><strong>' . $post->post_title . '</strong></a></td>';
		}

		$open_tickets = 0;
		if ( ! empty( $this->breakdown_component_type[ $component ] ) ) {
			$open_tickets = array_sum( $this->breakdown_component_type[ $component ] );
		}
		echo '<td class="right"><a href="' . $this->trac_url() . '/component/' . esc_attr( rawurlencode( $component ) ) . '">' . $open_tickets . '</a></td>';
		if ( $history['change'] ) {
			$count = sprintf( "%+d", $history['change'] );
			if ( $history['change'] > 0 ) {
				$count = $this->trac_query_link( $count, ['component' => $component, 'time' => date( 'm/d/y', strtotime( '-7 days' ) ) ] );
			}
			echo '<td class="right">' . $arrow . ' ' . $count . '</td>';
		} else {
			echo '<td></td>';
		}

		if ( isset( $this->breakdown_component_unreplied[ $component ] ) ) {
			$unreplied = $this->breakdown_component_unreplied[ $component ];
			echo '<td class="right">' . $this->trac_query_link( count( $unreplied ), array( 'component' => $component, 'id' => implode( ',', $unreplied ) ) );
			echo ' <span style="color: red; font-weight: bold">!!</span></td>';
		} else {
			echo '<td></td>';
		}

		$maintainers = $this->get_component_maintainers_by_post( $post->ID );
		echo '<td class="no-grav maintainers">';
		foreach ( $maintainers as $maintainer ) {
			$maintainer = get_user_by( 'login', $maintainer );
			if ( ! $maintainer ) {
				continue;
			}

			echo '<a href="//profiles.wordpress.org/' . esc_attr( $maintainer->user_nicename ) . '/" title="' . esc_attr( $maintainer->display_name ) . '">' . get_avatar( $maintainer->user_email, 24 ) . "</a>";
		}
		echo '</td>';
		echo '</tr>';
	}

	function get_component_maintainers_by_post( $post_id ) {
		return array_filter( array_map( 'trim', explode( ',', get_post_meta( $post_id, '_active_maintainers', true ) ) ) );
	}

	function get_component_maintainers( $component ) {
		$component_page = get_page_by_title( $component, OBJECT, self::POST_TYPE_NAME );
		if ( $component_page ) {
			return $this->get_component_maintainers_by_post( $component_page->ID );
		} else {
			return array();
		}
	}
}

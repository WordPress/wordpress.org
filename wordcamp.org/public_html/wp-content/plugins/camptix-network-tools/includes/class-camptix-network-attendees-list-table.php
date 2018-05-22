<?php
class CampTix_Network_Attendees_List_Table extends WP_List_Table {
	
	public $max_results = 100;
	
	function get_columns() {
		return array(
			'tix_date' => 'Date',
			'tix_name' => 'Name',
			'tix_email' => 'E-mail',
			'tix_event' => 'Event',
		);
	}
		
	function prepare_items() {
		global $wpdb;

		if ( ! isset( $_POST['s'] ) || empty( $_POST['s'] ) )
			return;
		check_admin_referer( 'dashboard_attendees_search_query', 'dashboard_attendees_search_query_nonce' );

		$search_query = trim( $_POST['s'] );
		$results = array();

		$blogs = $wpdb->get_col( $wpdb->prepare(
			"SELECT blog_id FROM `{$wpdb->blogs}` WHERE site_id = %d ORDER BY last_updated DESC LIMIT %d;",
			$wpdb->siteid,
			apply_filters( 'camptix_nt_attendee_list_blog_limit', 1500 )
		) );
		foreach ( $blogs as $bid ) {
			
			if ( count( $results ) >= $this->max_results )
				break;
			
			switch_to_blog( $bid );

			if ( is_plugin_active( 'camptix/camptix.php') ) {
					$paged = 1;
					while ( $attendees = get_posts( array(
						'paged' => $paged++,
						'post_status' => array( 'publish', 'pending' ),
						'post_type' => 'tix_attendee',
						'posts_per_page' => 20,
						's' => $search_query,
					) ) ) {
						foreach ( $attendees as $attendee ) {
							
							// Out of the foreach $attendees and while loop, but not the $blogs foreach loop.
							if ( count( $results ) >= $this->max_results )
								break 2;
							
							$results[] = array(
								'attendee' => $attendee,
								'meta' => get_post_custom( $attendee->ID ),
								'event' => array(
									'name' => get_bloginfo( 'name' ),
									'url' => home_url( '/' ),
									'edit_post_link' => add_query_arg( array(
										'post' => $attendee->ID,
										'action' => 'edit',
									), admin_url( 'post.php' ) ),
								),
							);
							clean_post_cache( $attendee->ID );
						}
					}
				}
			
			restore_current_blog();
		}

		$this->items = $results;

		/*$total_items = count( $output );
		$total_pages = 5;
		
		$this->set_pagination_args( array(
			'total_items' => 0,
			'total_pages' => 99,
			'per_page' => $per_page,
		) );*/
	}
	
	function column_tix_date( $item ) {
		extract( $item ); // $attendee, $meta, $event
		return date( 'Y-m-d', strtotime( $attendee->post_date ) );
	}
	
	function column_tix_name( $item ) {
		extract( $item ); // $attendee, $meta, $event
		return sprintf( '<a href="%s">%s</a>', esc_url( $event['edit_post_link'] ), esc_html( $attendee->post_title ) );
	}
	
	function column_tix_email( $item ) {
		extract( $item ); // $attendee, $meta, $event
		$email = '';

		if ( isset( $meta['tix_email'], $meta['tix_email'][0] ) && is_email( $meta['tix_email'][0] ) )
			$email = $meta['tix_email'][0];

		return sprintf( '<a href="mailto:%s">%s</a>', esc_attr( $email ), esc_html( $email ) );
	}
	
	function column_tix_event( $item ) {
		extract( $item ); // $attendee, $meta, $event
		return sprintf( '<a href="%s">%s</a>', esc_url( $event['url'] ), esc_html( $event['name'] ) );
	}
	
	function column_default( $item, $column_name ) {
		return 'default';
	}
}
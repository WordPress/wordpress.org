<?php
class CampTix_Network_Dashboard_List_Table extends WP_List_Table {
	
	function get_columns() {
		return array(
			'tix_event' => 'Event',
			'tix_start' => 'Sales Open',
			'tix_end' => 'Sales Close',
			'tix_sold' => 'Sold',
			'tix_remaining' => 'Remaining',
			'tix_subtotal' => 'Sub-Total',
			'tix_discounted' => 'Discounted',
			'tix_revenue' => 'Revenue',
			'tix_version' => 'Version',
		);
	}
	
	function get_sortable_columns() {
		return array(
			'tix_event' => 'tix_event',
			'tix_start' => 'tix_start',
			'tix_end' => 'tix_end',
			'tix_sold' => 'tix_sold',
			'tix_remaining' => 'tix_remaining',
			'tix_subtotal' => 'tix_subtotal',
			'tix_discounted' => 'tix_discounted',
			'tix_revenue' => 'tix_revenue',
		);
	}

	function get_views() {
		return array(
			'all' => '<a href="' . esc_url( remove_query_arg( 'tix_view' ) ) . '">All</a>',
			'active' => '<a href="' . esc_url( remove_query_arg( 'tix_view' ) ) . '">Active</a>',
			'sandbox' => '<a href="' . esc_url( remove_query_arg( 'tix_view' ) ) . '">Sandboxed</a>',
			'archived' => '<a href="' . esc_url( remove_query_arg( 'tix_view' ) ) . '">Archived</a>',
		);
	}
	
	function prepare_items() {
		
		$this->currency = 'USD';
		$paged = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		$per_page = apply_filters( 'camptix_nt_overview_per_page', 10 );
		
		$args = array(
			'post_type' => 'tix_event',
			'post_status' => 'any',
			'posts_per_page' => $per_page,
			'paged' => $paged,
			'meta_query' => array(),
		);

		// Exclude archived sites
		$args['meta_query'][] = array(
			'key' => 'tix_archived',
			'compare' => '=',
			'value' => '0',
		);

		if ( isset( $_REQUEST['orderby'] ) ) {
			$orderby = strtolower( $_REQUEST['orderby'] );
			
			switch ( $orderby ) {
				case 'tix_event':
					$args['orderby'] = 'title';
					break;
				case 'tix_sold':
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = 'tix_stats_sold';
					break;
				case 'tix_remaining':
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = 'tix_stats_remaining';
					break;
				case 'tix_subtotal':
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = 'tix_stats_subtotal';
					break;
				case 'tix_discounted':
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = 'tix_stats_discounted';
					break;
				case 'tix_revenue':
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = 'tix_stats_revenue';
					break;
				case 'tix_start':
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = 'tix_earliest_start';
					break;
				case 'tix_end':
					$args['orderby'] = 'meta_value_num';
					$args['meta_key'] = 'tix_latest_end';
					break;
				default:
			}
		}
		
		$args['order'] = ( isset( $_REQUEST['order'] ) && in_array( $_REQUEST['order'], array( 'asc', 'desc' ) ) ) ? $_REQUEST['order'] : 'asc';
		
		if ( isset( $_REQUEST['s'] ) )
		{
			check_admin_referer( 'dashboard_overview_search_events', 'dashboard_overview_search_events_nonce' );
			$args['s'] = $_REQUEST['s'];
		}
		
		$query = new WP_Query( $args );
		$this->items = $query->posts;

		$total_items = $query->found_posts;
		$total_pages = $query->max_num_pages;
		
		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'total_pages' => $total_pages,
			'per_page' => $per_page
		) );
	}

	function column_tix_event( $item ) {
		$home_url = esc_url( get_post_meta( $item->ID, 'tix_home_url', true ) );
		$admin_url = esc_url( get_post_meta( $item->ID, 'tix_admin_url', true ) );
		$options = get_post_meta( $item->ID, 'tix_options', true );
		
		$revenue_url = add_query_arg( array( 'post_type' => 'tix_ticket', 'page' => 'camptix_tools', 'tix_section' => 'revenue' ), $admin_url . 'edit.php' );
 		$return = '<strong><a href="' . $home_url . '" class="row-title">' . $item->post_title . '</a></strong>';
		
		$extra = array();
		if ( isset( $options['paypal_sandbox'] ) && $options['paypal_sandbox'] )
			$extra[] = 'Sandbox';
		
		if ( isset( $options['archived'] ) && $options['archived'] )
			$extra[] = 'Archived';
			
		if ( $extra )
			$return .= ' - ' . implode( ', ', $extra );
		
		$actions = array(
			'dashboard' => '<span><a href="' . $admin_url . '">Dashboard</a></span>',
			'revenue' => '<span><a href="' . $revenue_url . '">Revenue Report</a></span>',
			'visit' => '<span><a href="' . $home_url . '">Visit</a></span>',
		);
		$return .= $this->row_actions( $actions );
		return $return;
	}
	
	function column_tix_sold( $item ) {
		return intval( get_post_meta( $item->ID, 'tix_stats_sold', true ) );
	}
	
	function column_tix_remaining( $item ) {
		return intval( get_post_meta( $item->ID, 'tix_stats_remaining', true ) );
	}
	
	function column_tix_subtotal( $item ) {
		return $this->append_currency( (float) get_post_meta( $item->ID, 'tix_stats_subtotal', true ) );
	}
	
	function column_tix_discounted( $item ) {
		return $this->append_currency( (float) get_post_meta( $item->ID, 'tix_stats_discounted', true ) );
	}
	
	function column_tix_revenue( $item ) {
		return $this->append_currency( (float) get_post_meta( $item->ID, 'tix_stats_revenue', true ) );
	}
	
	function column_tix_start( $item ) {
		$start = intval( get_post_meta( $item->ID, 'tix_earliest_start', true ) );
		$undefined_start = (bool) get_post_meta( $item->ID, 'tix_undefined_start', true );
		
		if ( $undefined_start )
			return 'Undefined';
		
		if ( $start ) {
			$ago = human_time_diff( $start, time() );
			$ago .= $start < time() ? ' ago' : ' from now';
			return '<acronym class="tix-tooltip" title="' . esc_attr( $ago ) . '">' . esc_html( date( 'Y-m-d', $start ) ) . '</acronym>';
		}
	}
	
	function column_tix_end( $item ) {
		$end = intval( get_post_meta( $item->ID, 'tix_latest_end', true ) );
		$undefined_end = (bool) get_post_meta( $item->ID, 'tix_undefined_end', true );
		
		if ( $undefined_end )
			return 'Undefined';
		
		if ( $end ) {
			$ago = human_time_diff( $end, time() );
			$ago .= $end < time() ? ' ago' : ' from now';
			return '<acronym class="tix-tooltip" title="' . esc_attr( $ago ) . '">' . esc_html( date( 'Y-m-d', $end ) ) . '</acronym>';
		}
	}
	
	function column_tix_version( $item ) {
		$options = get_post_meta( $item->ID, 'tix_options', true );
		if ( isset( $options['version'] ) )
			return $options['version'];
	}
	
	function column_default( $item, $column_name ) {
		return 'default';
	}
	
	function single_row( $item ) {
		
		$options = get_post_meta( $item->ID, 'tix_options', true );
		if ( isset( $options['paypal_currency'] ) )
			$this->currency = $options['paypal_currency'];
		
		parent::single_row( $item );
	}
	
	function append_currency( $price ) {
		return sprintf( "%s %s", number_format( (float) $price, 2 ), $this->currency );
	}
}
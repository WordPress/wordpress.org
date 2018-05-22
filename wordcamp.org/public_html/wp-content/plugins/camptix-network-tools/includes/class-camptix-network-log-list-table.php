<?php

class CampTix_Network_Log_List_Table extends WP_List_Table {
	var $log_highlight_id = false;

	function get_columns() {
		return array(
			'tix_timestamp' => 'Timestamp',
			'tix_message' => 'Message',
			'tix_domain' => 'Domain',
		);
	}

	function prepare_items() {
		global $wpdb;

		$per_page = (int) apply_filters( 'camptix_nt_log_entries_per_page', 50 );
		$paged = isset( $_REQUEST['paged'] ) ? absint( $_REQUEST['paged'] ) : 1;
		$offset = $per_page * ( $paged - 1 );
		$where = ' WHERE 1=1 ';

		if ( isset( $_REQUEST['s'] ) ) {
			$s = $_REQUEST['s'];
			$advanced_query = explode( ':', $s );

			switch ( $advanced_query[0] ) {
				case 'id':
					$this->log_highlight_id = absint( $advanced_query[1] );
					$range = floor( $per_page / 2 );

					$advanced_query_value1 = $this->log_highlight_id - $range;
					$advanced_query_value2 = $this->log_highlight_id + $range - 1;	// - 1 to avoid pagination
					$advanced_query = "OR id BETWEEN %d AND %d";
				break;

				default:
					$advanced_query = $advanced_query_value1 = $advanced_query_value2 = '';
				break;
			}

			$where .= $wpdb->prepare( " AND ( message LIKE '%%%s%%' OR data LIKE '%%%s%%' OR ( object_id = '%s' AND object_id > 0 ) ". $advanced_query ." )",
				like_escape( $s ), like_escape( $s ), $s, $advanced_query_value1, $advanced_query_value2
			);
		}

		if ( isset( $_REQUEST['tix_log_section'] ) ) {
			$section = $_REQUEST['tix_log_section'];
			$where .= $wpdb->prepare ( " AND section = '%s' ", $section );
		}

		if ( isset( $_REQUEST['tix_log_blog_id'] ) ) {
			$bid = absint( $_REQUEST['tix_log_blog_id'] );
			$where .= $wpdb->prepare( " AND blog_id = %d ", $bid );
		}

		$orderby = " ORDER BY id DESC";
		$limit = $wpdb->prepare( " LIMIT %d OFFSET %d ", $per_page, $offset );

		$table_name = $wpdb->base_prefix . "camptix_log";
		$this->items = $wpdb->get_results( "SELECT SQL_CALC_FOUND_ROWS * FROM $table_name $where $orderby $limit;" );
		$found_rows = $wpdb->get_var( "SELECT FOUND_ROWS();" );

		$this->set_pagination_args( array(
			'total_items' => $found_rows,
			'total_pages' => ceil( $found_rows / $per_page ),
			'per_page' => $per_page,
		) );
	}

	function column_tix_timestamp( $item ) {
		$timestamp = $item->timestamp;
		$ago = human_time_diff( strtotime( $timestamp ), time() ) . ' ago';
		return '<acronym class="tix-tooltip tix-tooltip-' . absint( $item->id ) . '" title="' . esc_attr( $ago ) . '">' . esc_html( $timestamp ) . '</acronym>';
	}

	function column_tix_message( $item ) {
		$message = esc_html( $item->message );
		$actions = array();
		$data = '';

		if ( isset( $item->data ) && $item->data ) {
			$actions[] = '<a href="#" class="tix-more-bytes">data</a>';
			$data .= '<pre class="tix-bytes" style="display: none;">' . esc_html( print_r( $item->data, true ) ) . '</pre>';
		}

		if ( isset( $item->object_id ) && $item->object_id > 0 ) {
			$edit_url = get_admin_url( $item->blog_id, 'post.php' );
			$edit_url = add_query_arg( array(
				'post' => rawurlencode( $item->object_id ),
				'action' => 'edit',
			), $edit_url );
			$actions[] = sprintf( '<a href="%s">%d</a>', esc_url( $edit_url ), $item->object_id );
		}

		$section = isset( $item->section ) ? esc_html( $item->section ) : 'general';
		$url = add_query_arg( 'tix_log_section', $section, network_admin_url( 'index.php?tix_section=log&page=camptix-dashboard' ) );
		$actions[] = sprintf( '<a href="%s">%s</a>', esc_url( $url ), $section );

		if ( $actions )
			$actions = ' <span class="tix-network-log-actions">' . implode( ', ', $actions ) . '</span>';
		else
			$actions = '';

		return $message . $actions . $data;
	}

	function column_tix_domain( $item ) {
		$url = str_replace( array( 'http://', 'https://' ), '', esc_url( get_home_url( $item->blog_id ) ) );
		$link = add_query_arg( 'tix_log_blog_id', rawurlencode( $item->blog_id ), network_admin_url( 'index.php?tix_section=log&page=camptix-dashboard' ) );

		return sprintf( '<a href="%s">%s</a>', esc_url( $link ), $url );
	}

	function column_default( $item, $column_name ) {
		return 'default';
	}

	function single_row( $item ) {
		static $row_class = array( 'alternate' => false, 'highlight' => false );

		$row_class['alternate'] = ! $row_class['alternate'];
		$row_class['highlight'] = $item->id == $this->log_highlight_id ? true : false;

		$data_json = json_decode( $item->data );
		if ( JSON_ERROR_NONE == json_last_error() ) {
			$item->data = $data_json;
		}

		echo '<tr class="' . ( $row_class['alternate'] ? 'alternate' : '' ) . ( $row_class['highlight'] ? ' highlight' : '' ) . '">';
		echo $this->single_row_columns( $item );
		echo '</tr>';
	}
}
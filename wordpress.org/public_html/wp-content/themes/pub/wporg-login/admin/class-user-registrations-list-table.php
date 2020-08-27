<?php

class User_Registrations_List_Table extends WP_List_Table {

	function get_columns() {
		return [
			'pending_id'      => 'ID',
			'created'         => 'Created',
			'user_login'      => 'User Login',
			'user_email'      => 'User Email',
			'user_ip'         => 'IP',
			'scores'          => 'reCaptcha',
			'user_registered' => 'Registered Date',
			'created_date'    => 'Created Date',
		];
	 }

	 public function get_sortable_columns() {
		return [
			'pending_id'      => array( 'pending_id', false ),
			'created'         => array( 'created', true ),
			'user_login'      => array( 'user_login', true ),
			'user_email'      => array( 'user_email', true ),
			'scores'          => array( 'scores', true ),
			'user_registered' => array( 'user_registered', true ),
			'created_date'    => array( 'created_date', true ),
		];
	 }

	function prepare_items() {
		global $wpdb;

		$this->_column_headers = array( 
			$this->get_columns(),
			array(),
			$this->get_sortable_columns(),
	   );

	   $sort_column = $_GET['orderby'] ?? 'pending_id';
	   $sort_order = strtoupper( $_GET['order'] ?? 'DESC' );

	   if ( ! in_array( $sort_order, [ 'DESC', 'ASC' ] ) ) {
		   $sort_order = 'DESC';
	   }
	   if ( ! isset( $this->get_sortable_columns()[ $sort_column ] ) ) {
		   $sort_column = 'pending_id';
	   }

	   $per_page     = $this->get_items_per_page( 'users_per_page', 100 );
	   $current_page = $this->get_pagenum();

	   $where = '1 = 1 ';
	   if ( isset( $_GET['s'] ) ) {
			$search_like = '%' . $wpdb->esc_like( $_GET['s'] ) . '%';
			$where .= $wpdb->prepare(
				"AND ( user_login LIKE %s OR user_email LIKE %s OR meta LIKE %s )",
				$search_like, $search_like, $search_like
		   );
	   }

	   $per_page_offset = ($current_page-1) * $per_page;

		$this->items = $wpdb->get_results(
			"SELECT SQL_CALC_FOUND_ROWS *
			FROM {$wpdb->base_prefix}user_pending_registrations
			WHERE $where
			ORDER BY {$sort_column} {$sort_order}
			LIMIT {$per_page_offset}, {$per_page}"
		);

		$this->set_pagination_args([
			'total_items' => $wpdb->get_var( 'SELECT FOUND_ROWS()' ),
			'per_page'    => $per_page,
		]);

	}

	function column_default( $item, $column_name ) {
		echo esc_html( $item->$column_name );
	}

	function column_created( $item ) {
		echo ( $item->created ? 'Yes' : 'No' );

		if ( ! $item->created ) {
			$url = add_query_arg(
				'email',
				urlencode( $item->user_email ),
				admin_url( 'admin-post.php?action=login_resend_email' )
			);
			$url = wp_nonce_url( $url, 'resend_' . $item->user_email );
			echo $this->row_actions( [
				'resend' => '<a href="' . esc_url( $url ) . '">Resend Email</a>',
			] );
		}
	}

	function column_user_registered( $item ) {
		printf(
			'<abbr title="%s">%s ago</abbr>',
			esc_attr( $item->user_registered ),
			human_time_diff( strtotime( $item->user_registered ) )
		);
	}

	function column_created_date( $item ) {
		if ( $item->created_date && '0000-00-00 00:00:00' !== $item->created_date ) {
			printf(
				'<abbr title="%s">%s ago</abbr>',
				esc_attr( $item->created_date ),
				human_time_diff( strtotime( $item->created_date ) )
			);
		} else {
			echo '&nbsp;';
		}
	}

	function column_user_login( $item ) {
		if ( $item->created ) {
			$url = esc_url( 'https://profiles.wordpress.org/' . $item->user_login . '/' );
			echo "<a href='$url'>" . esc_html( $item->user_login ) . '</a>';
		} else {
			echo esc_html( $item->user_login );
		}
	}

	function column_user_email( $item ) {
		list( $email_user, $domain ) = explode( '@', $item->user_email, 2 );

		printf(
			'%s@<a href="index.php?page=user-registrations&s=%s">%s</a>',
			esc_html( $email_user ),
			urlencode( $domain ),
			esc_html( $domain )
		);
	}


	function column_user_ip( $item ) {
		$meta = json_decode( $item->meta );

		echo implode( ', ',
			array_map(
				function( $ip ) {
					$url = add_query_arg(
						's',
						urlencode( $ip ),
						admin_url( 'index.php?page=user-registrations' )
					);
					return '<a href="' . $url . '">' . esc_html( $ip ) . '</a>';
				},
				array_filter( array_unique( [
					$meta->registration_ip ?? false,
					$meta->confirmed_ip ?? false
				] ) )
			)
		);
	}

	function column_scores( $item ) {
		foreach ( json_decode( $item->scores ) as $type => $val ) {
			printf(
				'<abbr title="%s">%s</abbr> ',
				esc_attr( $type ),
				esc_html( $val )
			);
		}
	}

}
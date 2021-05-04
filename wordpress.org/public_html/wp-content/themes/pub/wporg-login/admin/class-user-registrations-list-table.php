<?php

class User_Registrations_List_Table extends WP_List_Table {

	function get_views() {
		global $wpdb;

		$views = [
			[
				'all',
				'All',
			],
			[
				'pending',
				'Pending Email Confirmation',
			],
			[
				'registered',
				'Completed registration',
			],
			[
				'spam',
				'Caught in spam',
			],
			[
				'akismet',
				'Akismet said no',
			]
		];

		$default      = 'all';
		$current_view = $_REQUEST['view'] ?? $default;

		if ( isset( $_GET['s'] ) ) {
			$default = 'search';
			$views[0] = [
				'search', 'All search results'
			];

			array_unshift( $views, [ 'all', 'All' ] );

			if ( 'all' === $current_view ) {
				$current_view = 'search';
			}
		}

		return array_map(
			function( $item ) use ( $current_view ) {
				global $wpdb;

				$count = $wpdb->get_var(
					"SELECT count(*) FROM {$wpdb->base_prefix}user_pending_registrations WHERE " .
					$this->get_where_sql( $item[0] )
				);

				$url = admin_url( 'admin.php?page=user-registrations' );
				if ( !empty( $_GET['s'] ) && 'all' != $item[0] ) {
					$url = add_query_arg( 's', urlencode( $_GET['s'] ), $url );
				}

				$url = add_query_arg( 'view', $item[0], $url );

				return sprintf(
					'<a href="%s" class="%s">%s <span class="count">(%s)</span></a>',
					$url,
					$current_view === $item[0] ? 'current' : '',
					$item[1],
					number_format_i18n( $count ),
				);
			}, $views
		);
	}

	protected function get_view_sql_where( $view ) {
		switch ( $view ) {
			case 'pending':
				return 'created = 0 AND cleared = 1';
			case 'spam':
				return 'cleared = 0';
			case 'akismet':
				return "meta LIKE '%akismet_result\":\"spam%'";
			case 'registered':
				return 'created = 1';
			default:
			case 'all':
				return '1=1';
		}
	}

	protected function get_where_sql( $view = null ) {
		global $wpdb;

		$where = $this->get_view_sql_where( $view ?: ( $_REQUEST['view'] ?? 'all' ) );

		if ( isset( $_GET['s'] ) && 'all' != $view ) {
			 $search_like = '%' . $wpdb->esc_like( wp_unslash( $_GET['s'] ) ) . '%';
			 $where .= $wpdb->prepare(
				 " AND ( user_login LIKE %s OR user_email LIKE %s OR meta LIKE %s )",
				 $search_like, $search_like, $search_like
			);
		}

		return $where;
	}

	function get_columns() {
		return [
			'user_login'      => 'User Login',
			'meta'            => 'Meta',
			'scores'          => 'Anti-spam<br>reCaptcha Akismet',
			'user_registered' => 'Registered',
		];
	 }

	 public function get_sortable_columns() {
		return [
			'user_login'      => array( 'user_login', true ),
			'scores'          => array( 'scores', true ),
			'user_registered' => array( 'user_registered', true ),
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

	   $where = $this->get_where_sql();

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

	function column_user_registered( $item ) {
		printf(
			'<abbr title="%s">%s ago</abbr>',
			esc_attr( $item->user_registered ),
			human_time_diff( strtotime( $item->user_registered ) )
		);

		if ( $item->created_date && '0000-00-00 00:00:00' !== $item->created_date ) {
			printf(
				'<br>Created: <abbr title="%s">%s ago</abbr>',
				esc_attr( $item->created_date ),
				human_time_diff( strtotime( $item->created_date ) )
			);
		}
	}

	function column_user_login( $item ) {
		if ( $item->created ) {
			$url = esc_url( 'https://profiles.wordpress.org/' . $item->user_login . '/' );
			echo "<a href='$url'>" . esc_html( $item->user_login ) . '</a>';

			if (
				( $user = get_user_by( 'login', $item->user_login ) ) &&
				'BLOCKED' === substr( $user->user_pass, 0, 7 )
			) {
				echo ' <span class="delete-red">(blocked)</span>';
			}

		} else {
			echo esc_html( $item->user_login );
		}

		echo '<hr>';

		list( $email_user, $domain ) = explode( '@', $item->user_email, 2 );

		printf(
			'%s@<a href="admin.php?page=user-registrations&s=%s">%s</a>',
			esc_html( $email_user ),
			urlencode( $domain ),
			esc_html( $domain )
		);

		$row_actions = [];

		if ( ! $item->created && $item->cleared ) {
			$url = add_query_arg(
				'email',
				urlencode( $item->user_email ),
				admin_url( 'admin-post.php?action=login_resend_email' )
			);
			$url = wp_nonce_url( $url, 'resend_' . $item->user_email );

			$row_actions['resend'] = '<a href="' . esc_url( $url ) . '">Resend Email</a>';
		}

		if ( ! $item->created ) {
			if ( $item->user_activation_key ) {
				$url = add_query_arg(
					'email',
					urlencode( $item->user_email ),
					admin_url( 'admin-post.php?action=login_block' )
				);
				$url = wp_nonce_url( $url, 'block_' . $item->user_email );
	
				$row_actions['block'] = '<a href="' . esc_url( $url ) . '">Block Registration</a>';
			}

			$url = add_query_arg(
				'email',
				urlencode( $item->user_email ),
				admin_url( 'admin-post.php?action=login_delete' )
			);
			$url = wp_nonce_url( $url, 'delete_' . $item->user_email );

			$row_actions['delete'] = '<a href="' . esc_url( $url ) . '">Delete</a>';

		} else {
			// Account created, find the user.
			$user = get_user_by( 'login', $item->user_login );

			$url = add_query_arg(
				'user_id',
				urlencode( $user->ID ),
				admin_url( 'admin-post.php?action=login_block_account' )
			);
			$url = wp_nonce_url( $url, 'block_account_' . $user->ID );

			if ( $user && 'BLOCKED' !== substr( $user->user_pass, 0, 7 ) ) {
				$row_actions['block-account'] = '<a href="' . esc_url( $url ) . '">Block Account</a>';
			}

		}

		if ( $row_actions ) {
			echo $this->row_actions( $row_actions );
		}

	}


	function column_meta( $item ) {
		$meta = json_decode( $item->meta );

		echo implode( ', ',
			array_map(
				function( $ip ) {
					$url = add_query_arg(
						's',
						urlencode( $ip ),
						admin_url( 'admin.php?page=user-registrations' )
					);
					return '<a href="' . $url . '">' . esc_html( $ip ) . '</a>';
				},
				array_filter( array_unique( [
					$meta->registration_ip ?? false,
					$meta->confirmed_ip ?? false
				] ) )
			)
		);
		echo '<hr>';

		foreach ( [ 'url', 'from', 'occ', 'interests' ] as $field ) {
			if ( !empty( $meta->$field ) ) {
				printf( "%s: %s<br>", esc_html( $field ), esc_html( $meta->$field ) );
			}
		}
	}

	function column_scores( $item ) {

		echo ( $item->cleared ? 'Passed' : 'Failed' ) . '<br>';

		foreach ( json_decode( $item->scores ) as $type => $val ) {
			printf(
				'<abbr title="%s">%s</abbr> ',
				esc_attr( $type ),
				esc_html( $val )
			);
		}

		$meta    = json_decode( $item->meta );
		$akismet = $meta->akismet_result ?? '';
		if ( $akismet ) {
			printf(
				'<abbr title="%s">%s</abbr> ',
				esc_attr( 'Akismet' ),
				esc_html( strtolower( $akismet ) )
			);
		}

		$row_actions = [];

		if ( ! $item->created && $item->user_activation_key ) {
			$url = add_query_arg(
				'email',
				urlencode( $item->user_email ),
				admin_url( 'admin-post.php?action=login_block' )
			);
			$url = wp_nonce_url( $url, 'block_' . $item->user_email );

			$row_actions['block'] = '<a href="' . esc_url( $url ) . '">Block Registration</a>';
		}

		if ( ! $item->cleared ) {
			$url = add_query_arg(
				'email',
				urlencode( $item->user_email ),
				admin_url( 'admin-post.php?action=login_mark_as_cleared' )
			);
			$url = wp_nonce_url( $url, 'clear_' . $item->user_email );
			$row_actions['approve-reg'] = '<a href="' . esc_url( $url ) . '">Approve</a>';
		}

		if ( $row_actions ) {
			echo $this->row_actions( $row_actions );
		}
	}

}
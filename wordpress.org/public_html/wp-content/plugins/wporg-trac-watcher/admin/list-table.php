<?php
namespace WordPressdotorg\Trac\Watcher;
use function WordPressdotorg\Trac\Watcher\Trac\format_trac_markup as format_for_trac;
use WP_List_Table;

if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class Commits_List_Table extends WP_List_Table {

	protected $svn;

	public function __construct( $svn_details ) {
		$this->svn = $svn_details;

		parent::__construct();
	}

	public function prepare_items( $args = array() ) {
		global $wpdb;

		$this->_column_headers = [
			$this->get_columns(),
			$this->get_hidden_columns(),
			$this->get_sortable_columns()
		];

		$rev_table   = $this->svn['rev_table'];
		$props_table = $this->svn['props_table'];
		$page        = max( 0, ($args['paged'] ?? 0) -1 );
		$screen      = get_current_screen();
		$per_page    = $screen ? $screen->get_option( 'per_page', 'default' ) : 200;
		$offset      = $per_page * $page;

		$o_field = 'id';
		$o_order = 'DESC';
		if ( isset( $args['orderby'] ) && !empty( $this->get_sortable_columns()[ $args['orderby'] ] ) ) {
			$o_field = $args['orderby'];
		}
		if ( isset( $args['order'] ) && strtoupper( $args['order'] ) === 'ASC' ) {
			$o_order = 'ASC';
		}

		$join  = '';
		$where = '';

		// Filter to unknown props only.
		if ( ! empty( $args['unknown-props'] ) ) {
			$join .= "LEFT JOIN {$props_table} p ON r.id = p.revision";
			$where .= "AND p.id IS NOT NULL AND p.user_id IS NULL";
		}

		if ( ! empty( $args['revision'] ) ) {
			$where .= $wpdb->prepare( ' AND r.id = %d ', $args['revision'] );
		}

		if ( ! empty( $args['version'] ) ) {
			$where .= $wpdb->prepare( ' AND r.version LIKE %s', $wpdb->esc_like( $args['version'] ) . '%' );
		}
		if ( ! empty( $args['branch'] ) ) {
			$where .= $wpdb->prepare( ' AND r.branch LIKE %s', $wpdb->esc_like( $args['branch'] ) );
		}

		if ( ! empty( $args['revisions'] ) && preg_match( '!(?P<start>\d+)[:-](?P<end>(HEAD|\d+))!', $args['revisions'], $m ) ) {
			if ( 'HEAD' === $m['end'] ) {
				$where .= $wpdb->prepare( ' AND r.id > %d', $m['start'] );
			} else {
				$where .= $wpdb->prepare( ' AND r.id BETWEEN %d AND %d', $m['start'], $m['end'] );
			}
		}
		if ( ! empty( $args['revisions'] ) && preg_match( '!^[\d,]+$!', $args['revisions'], $m ) ) {
			$ids = implode(',', array_map( 'intval', explode( ',', $args['revisions'] ) ) );
			$where .= " AND r.id IN({$ids})";
		}

		if ( ! empty( $args['author'] ) ) {
			$where .= $wpdb->prepare( ' AND r.author = %s', $args['author'] );
		}

		if ( ! empty( $args['s'] ) ) {
			if ( ! $join ) {
				$join .= "LEFT JOIN {$props_table} p ON r.id = p.revision";
			}
			$where .= $wpdb->prepare(
				' AND ( r.message LIKE %s OR p.prop_name LIKE %s )',
				'%' . $wpdb->esc_like( $args['s'] ) . '%',
				'%' . $wpdb->esc_like( $args['s'] ) . '%',
			);
		}

		$this->items = $wpdb->get_results(
			"SELECT SQL_CALC_FOUND_ROWS r.* FROM {$rev_table} r
			{$join}
			WHERE 1=1 {$where}
			GROUP BY r.id
			ORDER BY r.{$o_field} {$o_order}
			LIMIT {$offset},{$per_page}"
		);
		$total_items = $wpdb->get_var( 'SELECT FOUND_ROWS()' );
		$this->fill_props();

		$this->set_pagination_args( [
			'total_items' => $total_items,
			'per_page'    => $per_page,
		] );
	}

	protected function fill_props() {
		global $wpdb;

		if ( ! $this->items ) {
			return;
		}

		$props_table = $this->svn['props_table'];

		$revisions = wp_list_pluck( $this->items, 'id' );
		$revisions = implode( ',', array_map( 'intval', $revisions ) );

		$props_list = $wpdb->get_results(
			"SELECT revision,user_id,prop_name
			FROM {$props_table}
			WHERE revision IN({$revisions})
			ORDER BY user_id IS NULL DESC, LENGTH(prop_name) DESC"
		);

		// Fill user caches, some commits have a lot of props, yay! :)
		$unique_user_ids = array_map( 'intval', array_filter( array_unique( wp_list_pluck( $props_list, 'user_id' ) ) ) );
		if ( $unique_user_ids ) {
			cache_users( $unique_user_ids );
		}

		foreach ( $this->items as $i => $details ) {
			$this->items[$i]->props = wp_list_pluck(
				wp_list_filter(
					$props_list,
					[
						'revision' => $details->id,
					]
				),
				'user_id',
				'prop_name'
			);
		}

	}

	public function extra_tablenav( $which ) {
		global $wpdb;
		$views = $this->get_views();

		if ( empty( $views ) )
			return;

		$this->views();

		if ( 'top' === $which ) {
			echo '<div class="actions alignleft">';
			if ( 'core' === $this->svn['slug'] ) {
				echo '<select name="version"><option value="">Version</option>';
				foreach ( get_wordpress_versions() as $v ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $v ),
						selected( $_REQUEST['version'] ?? '', $v ),
						esc_html( $v )
					);
				}
				
				echo '</select>';
			}

			$branches = get_branches_for( $this->svn );
			if ( $branches ) {
				echo '<select name="branch"><option value="">Branch</option>';
				foreach ( $branches as $b ) {
					printf(
						'<option value="%s" %s>%s</option>',
						esc_attr( $b ),
						selected( $_REQUEST['branch'] ?? '', $b ),
						esc_html( $b )
					);
				}
				echo '</select>';
			}

			echo '<select name="author"><option value="">Author</option>';
			$authors = get_transient( $this->svn['slug'] . '_authors' );
			if ( ! $authors ) {
				$authors = $wpdb->get_results( 'SELECT author, count(*) as count, max(date), max(date) > DATE_SUB( NOW(), INTERVAL 1 YEAR) as active FROM ' . $this->svn['rev_table'] . ' GROUP BY author ORDER BY author ASC' );
				set_transient( $this->svn['slug'] . '_authors', $authors, DAY_IN_SECONDS );
			}
			$last = 1;

			foreach ( [ 1 => 'Recently Active', 0 => 'Inactive' ] as $active_val => $group ) {
				printf( '<optgroup label="%s">', esc_attr( $group ) );
				$_authors = wp_list_filter( $authors, [ 'active' => $active_val ] );
				foreach ( $_authors as $a ) {
					printf(
						'<option value="%s" %s>%s (%s)</option>',
						esc_attr( $a->author ),
						selected( $_REQUEST['author'] ?? '', $a->author ),
						esc_html( $a->author ),
						esc_html( number_format_i18n( $a->count ) )
					);
				}
				echo '</optgroup>';
			}
			echo '</select>';

			echo '<input type="text" name="revisions" placeholder="Revs: 1:HEAD or 1,2,4,5" value="' . esc_attr( $_REQUEST['revisions'] ?? '' ) .'">';

			echo '<input type="submit" class="button button-secondary" value="Filter">';
			echo '</div>';
		}
	}

	function get_views() {
		$url = add_query_arg( 'page', $_REQUEST['page'], admin_url( 'admin.php' ) );

		$views = [
			'all' => '<a href="' . esc_url( $url ) . '">All</a>',
			'unknown-props' => '<a href="' . esc_url( add_query_arg( 'unknown-props', 1, $url ) ) . '">Unknown Props</a>',
		];

		if ( defined( 'WP_CORE_LATEST_RELEASE' ) && 'core' === $this->svn['slug'] ) {
			$v = sprintf( '%.1f', ((float)WP_CORE_LATEST_RELEASE+0.1) );
			$views['commits-to-trunk'] = '<a href="' . esc_url( add_query_arg( 'version', $v, $url ) ) . '">Commits to ' . $v .'</a>';
		}

		return $views;
	}


	public function get_columns() {
		$columns = array(
			'id'      => 'Revision',
			'author'  => 'Author',
			'message' => 'Message',
			'date'    => 'Date',
			'props'   => 'Props',
			'branch'  => 'Branch',
			'version' => 'Version'
		);

		return $columns;
	}

	public function get_hidden_columns() {
		return [
			'date',
			'author',
			'branch',
			'version',
		];
	}

	public function get_sortable_columns() {
		return [
			'id' => [
				'id',
				false
			]
		];
	}

	public function single_row( $item ) {
		printf(
			'<tr class="%s" data-revision="%d" data-svn="%s">',
			esc_attr( 'revision-' . $item->id ),
			esc_attr( $item->id ),
			esc_attr( $this->svn['slug'] )
		);

		$this->single_row_columns( $item );
		echo '</tr>';
	}

	public function column_default( $item, $column_name ) {
		switch( $column_name ) {
			case 'id':
				$output = '';
				$output .= sprintf(
					"<p><a href='%s'>%s</a></p>",
					esc_url( $this->svn['trac'] . '/changeset/' . $item->id ),
					"[{$item->id}]",
				);
				foreach ( [ 'date', 'branch', 'version' ] as $field ) {
					if ( !empty( $item->{$field} ) ) {
						$output .= sprintf(
							'<p><strong>%s</strong> %s</p>',
							$field,
							esc_html( $item->{$field} )
						);
					}
				}

				$user = get_user_by( 'login', $item->author );
				$output .= sprintf(
					'<br><a href="https://profiles.wordpress.org/%s/">%s %s<br>%s</a>',
					$user->user_nicename,
					get_avatar( $user, 32 ),
					esc_html( $user->display_name ),
					esc_html( $user->user_login )
				);


				return $output;

			case 'author':
				$user = get_user_by( 'login', $item->author );
				return sprintf(
					'<a href="https://profiles.wordpress.org/%s">%s %s</a>',
					$user->user_nicename,
					get_avatar( $user, 32 ),
					$user->display_name
				);

			case 'message':
				$message = format_for_trac( $item->message );

				// Highlight props.
				foreach ( $item->props as $prop => $user_id ) {
					$user = $user_id ? get_user_by( 'ID', $user_id ) : false;

					if ( false === stripos( $message, $prop ) ) {
						$message .= "<em>Missed Prop: $prop</em> ";
					}

					if ( $user && strtolower( $prop ) != strtolower( $user->user_login ) && strtolower( $prop ) != strtolower( $user->user_nicename ) ) {
						// User is a typo or mis-prop.
						$message = str_ireplace( $prop, "<del class='replace'>{$prop}</del><ins>{$user->user_nicename}</ins>", $message );
					} else {
						// All else.
						$tag     = $user ? 'ins' : 'del';
						$message = str_ireplace( $prop, "<{$tag}>{$prop}</{$tag}>", $message );
					}
				}

				return "<div>{$message}</div>";

			case 'props':
				$can_edit = current_user_can( 'publish_posts' );
				$output   = '<div class="propslist">';

				foreach ( $item->props as $prop => $user_id ) {
					$user    = $user_id ? get_user_by( 'ID', $user_id ) : false;
					$avatar  = $user_id ? get_avatar( $user, 32 ) : '<span class="dashicons dashicons-editor-help"></span>';
					$profile = $user ? 'https://profiles.wordpress.org/' . $user->user_nicename . '/' : '';

					$output .= sprintf(
						'<span class="user" data-prop="%s" data-user="%s">',
						esc_attr( $prop ),
						esc_attr( $user->user_login ?? '' )
					);
					if ( $user ) {
						$output .= '<a href="' . esc_url( $profile ) . '" target="_blank">';
					}

					$prop_is_different_from_user = ( $user && strtolower( $prop ) != strtolower( $user->user_login ) && strtolower( $prop ) != strtolower( $user->user_nicename ) );
					$prop_display_name_different = ( $user && $user->display_name != $prop );

					$output .= $avatar;
					$output .= $user ? $user->display_name : $prop;

					if ( $prop_is_different_from_user ) {
						$output .= " <em>typo</em>";
					} elseif ( false === stripos( $item->message, $prop ) ) {
						$output .= " <em>missed</em>";
					}

					if ( $user ) {
						$output .= '</a>';
					}
					if ( $can_edit ) {
						$output .= '<div class="overlay"><div class="actions"><a href="#" class="edit dashicons dashicons-edit"></a></div></div>';
					}

					if ( $prop_is_different_from_user || $prop_display_name_different ) {
						$output .= sprintf(
							'<br><a href="%s" target="_blank">%s</a>',
							esc_url( $profile ),
							esc_html( $user->user_login )
						);
					}

					$output .= '</span>';
				}

				if ( $can_edit ) {
					$output .= '<span class="user add"><a href="#" class="add dashicons dashicons-plus"></a>&nbsp;<a href="#" class="add">Add new</a></span>';
				}

				$output .= '</div>';

				if ( $can_edit ) {
					$output .= '<p class="actions"><a href="#" class="reparse">Reparse</a></p>';
				}

				return $output;

			default:
				return esc_html( $item->{$column_name} );
		}
	}
}
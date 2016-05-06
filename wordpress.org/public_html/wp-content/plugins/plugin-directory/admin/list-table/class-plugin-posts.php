<?php
namespace WordPressdotorg\Plugin_Directory\Admin\List_Table;
use \WordPressdotorg\Plugin_Directory\Tools;
use \WordPressdotorg\Plugin_Directory\Template;

_get_list_table( 'WP_Posts_List_Table' );

class Plugin_Posts extends \WP_Posts_List_Table {

	/**
	 *
	 * @global array     $avail_post_stati
	 * @global \WP_Query $wp_query
	 * @global int       $per_page
	 * @global string    $mode
	 */
	public function prepare_items() {
		global $avail_post_stati, $wp_query, $per_page, $mode;

		$this->set_hierarchical_display( is_post_type_hierarchical( $this->screen->post_type ) && 'menu_order title' === $wp_query->query['orderby'] );

		$post_type = $this->screen->post_type;
		$per_page = $this->get_items_per_page( 'edit_' . $post_type . '_per_page' );

		/** This filter is documented in wp-admin/includes/post.php */
 		$per_page = apply_filters( 'edit_posts_per_page', $per_page, $post_type );

		if ( $this->hierarchical_display ) {
			$total_items = $wp_query->post_count;
		} elseif ( $wp_query->found_posts || $this->get_pagenum() === 1 ) {
			$total_items = $wp_query->found_posts;
		} else {
			$post_counts = (array) wp_count_posts( $post_type, 'readable' );

			if ( isset( $_REQUEST['post_status'] ) && in_array( $_REQUEST['post_status'] , $avail_post_stati ) ) {
				$total_items = $post_counts[ $_REQUEST['post_status'] ];
			} elseif ( isset( $_REQUEST['show_sticky'] ) && $_REQUEST['show_sticky'] ) {
				$total_items = $this->sticky_posts_count;
			} elseif ( isset( $_GET['author'] ) && $_GET['author'] == get_current_user_id() ) {
				$total_items = $this->user_posts_count;
			} else {
				$total_items = array_sum( $post_counts );

				// Subtract post types that are not included in the admin all list.
				foreach ( get_post_stati( array( 'show_in_admin_all_list' => false ) ) as $state ) {
					$total_items -= $post_counts[ $state ];
				}
			}
		}

		if ( ! empty( $_REQUEST['mode'] ) ) {
			$mode = $_REQUEST['mode'] === 'excerpt' ? 'excerpt' : 'list';
			set_user_setting( 'posts_list_mode', $mode );
		} else {
			$mode = get_user_setting( 'posts_list_mode', 'list' );
		}

		$this->is_trash = isset( $_REQUEST['post_status'] ) && $_REQUEST['post_status'] === 'trash';

		$this->set_pagination_args( array(
			'total_items' => $total_items,
			'per_page' => $per_page
		) );
	}

	/**
	 *
	 * @return array
	 */
	public function get_columns() {
		$post_type     = $this->screen->post_type;
		$posts_columns = array(
			'cb'        => '<input type="checkbox" />',
			/* translators: manage posts column name */
			'title'     => _x( 'Title', 'column name', 'wporg-plugins' ),
			'tested'    => __( 'Tested up to', 'wporg-plugins' ),
			'rating'    => __( 'Rating', 'wporg-plugins' ),
			'installs'  => __( 'Active installs', 'wporg-plugins' ),
			'downloads' => __( 'Downloads', 'wporg-plugins' ),
			'support'   => __( 'Support', 'wporg-plugins' ),
			'date'      => __( 'Date', 'wporg-plugins' ),
		);

		/**
		 * Filter the columns displayed in the Plugins list table.
		 *
		 * @param array  $posts_columns An array of column names.
		 * @param string $post_type     The post type slug.
		 */
		$posts_columns = apply_filters( 'manage_posts_columns', $posts_columns, $post_type );

		/**
		 * Filter the columns displayed in the Plugins list table.
		 *
		 * The dynamic portion of the hook name, `$post_type`, refers to the post type slug.
		 *
		 * @param array $post_columns An array of column names.
		 */
		return apply_filters( "manage_{$post_type}_posts_columns", $posts_columns );
	}

	/**
	 * @global \WP_Post $post
	 *
	 * @param int|\WP_Post $post
	 * @param int         $level
	 */
	public function single_row( $post, $level = 0 ) {
		$global_post = get_post();

		$post = get_post( $post );
		$this->current_level = $level;

		$GLOBALS['post'] = $post;
		setup_postdata( $post );

		$classes = array(
			'iedit',
			'author-' . get_current_user_id() == $post->post_author ? 'self' : 'other',
		);

		$lock_holder = wp_check_post_lock( $post->ID );
		if ( $lock_holder ) {
			$classes[] = 'wp-locked';
		}

		if ( $post->post_parent ) {
			$count = count( get_post_ancestors( $post->ID ) );
			$classes[] = 'level-' . $count;
		} else {
			$classes[] = 'level-0';
		}
		?>
		<tr id="post-<?php echo $post->ID; ?>" class="<?php echo implode( ' ', get_post_class( $classes, $post->ID ) ); ?>">
			<?php $this->single_row_columns( $post ); ?>
		</tr>
		<?php
		$GLOBALS['post'] = $global_post;
	}

	/**
	 * Handles the tested up to column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_tested( $post ) {
		$tested = (string) get_post_meta( $post->ID, 'tested', true );

		if ( ! empty( $tested ) ) {
			$class = version_compare( $tested, WP_CORE_STABLE_BRANCH ) >= 0 ? 'current' :  'needs-update';

			printf( '<span class="%s">%s</span>', $class, $tested );
		}
	}

	/**
	 * Handles the rating column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_rating( $post ) {
		echo Template::dashicons_stars( get_post_meta( $post->ID, 'rating', true ) );
	}

	/**
	 * Handles the installs column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_installs( $post ) {
		echo Template::active_installs( false, $post );
	}

	/**
	 * Handles the downloads column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_downloads( $post ) {
		echo number_format_i18n( get_post_meta( $post->ID, 'downloads', true ) );
	}

	/**
	 * Handles the support column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_support( $post ) {
		$threads    = get_post_meta( $post->ID, 'support_threads', true );
		$resolved   = get_post_meta( $post->ID, 'support_threads_resolved', true );
		$unresolved = max( 0, $threads - $resolved );
		$link_text  = sprintf( __( '%d unresolved', 'wporg-plugins' ), $unresolved );

		printf( '<a href="%s">%s</a>', esc_url( 'https://wordpress.org/support/plugin/' . $post->post_name ), $link_text );
	}

	/**
	 * Generates and displays row action links.
	 *
	 * @access protected
	 *
	 * @param object $post        Post being acted upon.
	 * @param string $column_name Current column name.
	 * @param string $primary     Primary column name.
	 * @return string Row actions output for posts.
	 */
	protected function handle_row_actions( $post, $column_name, $primary ) {
		if ( $primary !== $column_name ) {
			return '';
		}

		$post_type_object = get_post_type_object( $post->post_type );
		$can_edit_post = current_user_can( 'edit_post', $post->ID );
		$actions = array();
		$title = _draft_or_post_title();

		if ( $can_edit_post && 'trash' != $post->post_status ) {
			$actions['edit'] = sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				get_edit_post_link( $post->ID ),
				/* translators: %s: post title */
				esc_attr( sprintf( __( 'Edit &#8220;%s&#8221;' ), $title ) ),
				__( 'Edit' )
			);

			if ( current_user_can( 'plugin_edit_others', $post->ID ) ) {
				$actions['inline hide-if-no-js'] = sprintf(
					'<a href="#" class="editinline" aria-label="%s">%s</a>',
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Quick edit &#8220;%s&#8221; inline' ), $title ) ),
					__( 'Quick&nbsp;Edit' )
				);
			} else {
				wp_dequeue_script( 'inline-edit-post' );
			}
		}

		if ( current_user_can( 'delete_post', $post->ID ) ) {
			if ( 'trash' === $post->post_status ) {
				$actions['untrash'] = sprintf(
					'<a href="%s" aria-label="%s">%s</a>',
					wp_nonce_url( admin_url( sprintf( $post_type_object->_edit_link . '&amp;action=untrash', $post->ID ) ), 'untrash-post_' . $post->ID ),
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Restore &#8220;%s&#8221; from the Trash' ), $title ) ),
					__( 'Restore' )
				);
			} elseif ( EMPTY_TRASH_DAYS ) {
				$actions['trash'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID ),
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Move &#8220;%s&#8221; to the Trash' ), $title ) ),
					_x( 'Trash', 'verb' )
				);
			}
			if ( 'trash' === $post->post_status || ! EMPTY_TRASH_DAYS ) {
				$actions['delete'] = sprintf(
					'<a href="%s" class="submitdelete" aria-label="%s">%s</a>',
					get_delete_post_link( $post->ID, '', true ),
					/* translators: %s: post title */
					esc_attr( sprintf( __( 'Delete &#8220;%s&#8221; permanently' ), $title ) ),
					__( 'Delete Permanently' )
				);
			}
		}

		if ( is_post_type_viewable( $post_type_object ) && 'publish' === $post->post_status ) {
			$actions['view'] = sprintf(
				'<a href="%s" rel="permalink" aria-label="%s">%s</a>',
				get_permalink( $post->ID ),
				/* translators: %s: post title */
				esc_attr( sprintf( __( 'View &#8220;%s&#8221;' ), $title ) ),
				__( 'View' )
			);
		}

		if ( is_post_type_hierarchical( $post->post_type ) ) {

			/**
			 * Filter the array of row action links on the Pages list table.
			 *
			 * The filter is evaluated only for hierarchical post types.
			 *
			 * @param array $actions An array of row action links. Defaults are
			 *                         'Edit', 'Quick Edit', 'Restore, 'Trash',
			 *                         'Delete Permanently', 'Preview', and 'View'.
			 * @param \WP_Post $post The post object.
			 */
			$actions = apply_filters( 'page_row_actions', $actions, $post );
		} else {

			/**
			 * Filter the array of row action links on the Posts list table.
			 *
			 * The filter is evaluated only for non-hierarchical post types.
			 *
			 * @param array $actions An array of row action links. Defaults are
			 *                         'Edit', 'Quick Edit', 'Restore, 'Trash',
			 *                         'Delete Permanently', 'Preview', and 'View'.
			 * @param \WP_Post $post The post object.
			 */
			$actions = apply_filters( 'post_row_actions', $actions, $post );
		}

		return $this->row_actions( $actions );
	}

	/**
	 * Prepares list view links, including plugins that the current user has commit access to.
	 *
	 * @global array $locked_post_status This seems to be deprecated.
	 * @global array $avail_post_stati
	 * @return array
	 */
	protected function get_views() {
		global $locked_post_status, $avail_post_stati, $wpdb;

		if ( ! empty( $locked_post_status ) ) {
			return array();
		}

		$post_type    = $this->screen->post_type;
		$status_links = array();
		$num_posts    = wp_count_posts( $post_type, 'readable' );
		$total_posts  = array_sum( (array) $num_posts );
		$class        = '';

		$current_user_id = get_current_user_id();
		$all_args        = array( 'post_type' => $post_type );
		$mine            = '';

		$plugins = Tools::get_users_write_access_plugins( $current_user_id );
		$plugins = array_map( 'sanitize_title_for_query', $plugins );
		$exclude_states   = get_post_stati( array(
			'show_in_admin_all_list' => false,
		) );

		if ( ! current_user_can( 'plugin_approve' ) ) {
			$exclude_states = array_merge( $exclude_states, array(
				'publish'  => 'publish',
				'closed'   => 'closed',
				'rejected' => 'rejected',
				'private'  => 'private',
			) );
		}

		$user_post_count = intval( $wpdb->get_var( $wpdb->prepare( "
			SELECT COUNT( 1 )
			FROM $wpdb->posts
			WHERE post_type = %s
			AND ( post_author = %d OR post_name IN ( '" . implode( "','", $plugins ) . "' ) )
		", $post_type, $current_user_id ) ) );

		// Subtract post types that are not included in the admin all list.
		foreach ( $exclude_states as $state ) {
			$total_posts -= $num_posts->$state;
		}

		if ( $user_post_count && $user_post_count !== $total_posts ) {
			if ( isset( $_GET['author'] ) && $_GET['author'] == $current_user_id ) {
				$class = 'current';
			}

			$mine_args = array(
				'post_type' => $post_type,
				'author'    => $current_user_id
			);

			$mine_inner_html = sprintf(
				_nx(
					'Mine <span class="count">(%s)</span>',
					'Mine <span class="count">(%s)</span>',
					$user_post_count,
					'posts',
					'wporg-posts'
				),
				number_format_i18n( $user_post_count )
			);

			if ( ! current_user_can( 'plugin_review' ) ) {
				$status_links['mine'] = $this->get_edit_link( $mine_args, $mine_inner_html, 'current' );;
				return $status_links;
			} else {
				$mine = $this->get_edit_link( $mine_args, $mine_inner_html, $class );
			}

			$all_args['all_posts'] = 1;
			$class = '';
		}

		if ( empty( $class ) && ( $this->is_base_request() || isset( $_REQUEST['all_posts'] ) ) ) {
			$class = 'current';
		}

		$all_inner_html = sprintf(
			_nx(
				'All <span class="count">(%s)</span>',
				'All <span class="count">(%s)</span>',
				$total_posts,
				'posts',
				'wporg-posts'
			),
			number_format_i18n( $total_posts )
		);

		$status_links['all'] = $this->get_edit_link( $all_args, $all_inner_html, $class );
		if ( $mine ) {
			$status_links['mine'] = $mine;
		}

		foreach ( get_post_stati(array('show_in_admin_status_list' => true), 'objects') as $status ) {
			$class = '';

			$status_name = $status->name;

			if ( ! in_array( $status_name, $avail_post_stati ) || empty( $num_posts->$status_name ) ) {
				continue;
			}

			if ( ! current_user_can( 'plugin_approve' ) && ! in_array( $status_name, array( 'draft', 'pending' ) ) ) {
				continue;
			}

			if ( isset($_REQUEST['post_status']) && $status_name === $_REQUEST['post_status'] ) {
				$class = 'current';
			}

			$status_args = array(
				'post_status' => $status_name,
				'post_type' => $post_type,
			);

			$status_label = sprintf(
				translate_nooped_plural( $status->label_count, $num_posts->$status_name ),
				number_format_i18n( $num_posts->$status_name )
			);

			$status_links[ $status_name ] = $this->get_edit_link( $status_args, $status_label, $class );
		}

		if ( ! empty( $this->sticky_posts_count ) ) {
			$class = ! empty( $_REQUEST['show_sticky'] ) ? 'current' : '';

			$sticky_args = array(
				'post_type'	=> $post_type,
				'show_sticky' => 1
			);

			$sticky_inner_html = sprintf(
				_nx(
					'Sticky <span class="count">(%s)</span>',
					'Sticky <span class="count">(%s)</span>',
					$this->sticky_posts_count,
					'posts',
					'wporg-posts'
				),
				number_format_i18n( $this->sticky_posts_count )
			);

			$sticky_link = array(
				'sticky' => $this->get_edit_link( $sticky_args, $sticky_inner_html, $class )
			);

			// Sticky comes after Publish, or if not listed, after All.
			$split = 1 + array_search( ( isset( $status_links['publish'] ) ? 'publish' : 'all' ), array_keys( $status_links ) );
			$status_links = array_merge( array_slice( $status_links, 0, $split ), $sticky_link, array_slice( $status_links, $split ) );
		}

		return $status_links;
	}
}

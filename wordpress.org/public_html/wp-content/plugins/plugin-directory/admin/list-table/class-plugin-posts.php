<?php
namespace WordPressdotorg\Plugin_Directory\Admin\List_Table;

_get_list_table( 'WP_Posts_List_Table' );

class Plugin_Posts extends \WP_Posts_List_Table {

	/**
	 * Plugin API response about the current plugin displayed.
	 *
	 * @var object
	 */
	 protected $plugin_meta;

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

		//@TODO: Switch to using the API class directly (once rewritten), or even better, post meta.
		require_once ABSPATH . '/wp-admin/includes/plugin-install.php';
		$this->plugin_meta = \plugins_api( 'plugin_information', array(
			'slug'   => $post->post_name,
			'fields' => array( 'active_installs' => true ),
		) );
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
		if ( ! empty( $this->plugin_meta->rating ) && function_exists( 'wporg_get_dashicons_stars' ) ) {
			echo wporg_get_dashicons_stars( $this->plugin_meta->rating / 20 );
		}
	}

	/**
	 * Handles the installs column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_installs( $post ) {
		if ( ! empty( $this->plugin_meta->active_installs ) ) {
			echo number_format_i18n( $this->plugin_meta->active_installs ) . '+';
		}
	}

	/**
	 * Handles the downloads column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_downloads( $post ) {
		if ( ! empty( $this->plugin_meta->downloaded ) ) {
			echo number_format_i18n( $this->plugin_meta->downloaded );
		}
	}

	/**
	 * Handles the support column output.
	 *
	 * @param \WP_Post $post The current WP_Post object.
	 */
	public function column_support( $post ) {
		//@todo: Check how many unresolved threads there are.
		$link_text = __( '0 open', 'wporg-plugins' );

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
			 * @param WP_Post $post The post object.
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
			 * @param WP_Post $post The post object.
			 */
			$actions = apply_filters( 'post_row_actions', $actions, $post );
		}

		return $this->row_actions( $actions );
	}
}

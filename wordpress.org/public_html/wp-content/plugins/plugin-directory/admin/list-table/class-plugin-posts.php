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
}

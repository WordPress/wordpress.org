<?php

class WPorg_Handbook_Pages_Widget extends WP_Widget_Pages {

	/**
	 * Base ID for the widget.
	 *
	 * @access protected
	 * @var string
	 */
	protected static $widget_id_base = 'handbook_pages';

	protected $post_types = array( 'handbook' );

	/**
	 * Gets the widget_id_base value.
	 *
	 * @return string
	 */
	public static function get_widget_id_base() {
		return self::$widget_id_base;
	}

	public function __construct() {
		$widget_ops = array(
			'classname' => 'widget_wporg_handbook_pages',
			'description' => __( 'Your site&#8217;s Handbook Pages', 'wporg' ),
			'customize_selective_refresh' => true,
		);
		WP_Widget::__construct( self::get_widget_id_base(), __( 'Handbook Pages', 'wporg' ), $widget_ops );
	}

	public function widget( $args, $instance ) {
		$args['after_title'] = '</h2>' . "\n" . '<div class="menu-table-of-contents-container">' . "\n";
		$args['after_widget'] = '</div>';

		add_filter( 'widget_pages_args',    array( $this, 'handbook_post_type' ), 10, 2 );
		add_filter( 'page_css_class',       array( $this, 'amend_page_css_class' ) );
		parent::widget( $args, $instance );
		remove_filter( 'page_css_class',    array( $this, 'amend_page_css_class' ) );
		remove_filter( 'widget_pages_args', array( $this, 'handbook_post_type' ) );
	}

	public function form( $instance ) {
		if ( empty( $instance['sortby'] ) ) {
			$instance['sortby'] = 'menu_order';
		}

		parent::form( $instance );

		$checked = checked( ! empty( $instance['show_home'] ), true, false );
		?>
		<p>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id('show_home') ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'show_home' ) ); ?>" type="checkbox" value="1" <?php echo $checked ?> />
			<label for="<?php echo esc_attr( $this->get_field_id( 'show_home' ) ); ?>"><?php _e( 'List the home page', 'wporg' ); ?></label>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$instance = parent::update( $new_instance, $old_instance );
		$instance['show_home'] = isset( $new_instance['show_home'] ) ? (bool) $new_instance['show_home'] : false;

		return $instance;
	}

	public function handbook_post_type( $args, $instance ) {
		$post = get_post();

		if ( ! isset( $instance['show_home'] ) ) {
			$instance['show_home'] = false;
		}

		$this->post_types = (array) apply_filters( 'handbook_post_types', $this->post_types );
		$this->post_types = array_map( array( $this, 'append_suffix' ), $this->post_types );

		$post_type = '';

		if ( $post && in_array( $post->post_type, $this->post_types ) ) {
			$post_type = $post->post_type;
		} elseif ( is_post_type_archive( $this->post_types ) ) {
			$post_type = reset( $this->post_types );
		}

		if ( $post_type ) {
			$args['post_type'] = $post_type;
		}

		$post_type_obj = get_post_type_object( $post_type );

		if ( current_user_can( $post_type_obj->cap->read_private_posts ) ) {
			$args['post_status'] = array( 'publish', 'private' );
		}

		// Exclude root handbook page from the table of contents.
		$page = get_page_by_path( $this->append_suffix( $post_type ), OBJECT, $post_type );
		if ( ! $page ) {
			$slug = substr( $post_type, 0, -9 );
			$page = get_page_by_path( $slug, OBJECT, $post_type );
		}
		if ( $page && ! $instance['show_home'] ) {
			$args['exclude'] = rtrim( $page->ID . ',' . $args['exclude'], ',' );
		}

		// Use custom walker that excludes display of orphaned pages. (An ancestor
		// of such a page is likely not published and thus this is not accessible.)
		$args['walker'] = new WPorg_Handbook_Walker;

		return $args;
	}

	public function append_suffix( $t ) {
		if ( in_array( $t, array( 'handbook', 'page' ) ) ) {
			return $t;
		}

		return $t . '-handbook';
	}

	/**
	 * Adds menu-related CSS tags that correspond to present page-related tags so
	 * styling is consistent for both without having to duplicate all CSS rules.
	 *
	 * @param array $css_class The CSS classes being applied to a given list item.
	 *
	 * @return array
	 */
	public function amend_page_css_class( $css_class ) {
		$class_name_map = array(
			'current_page_ancestor'  => 'current-menu-ancestor',
			'current_page_item'      => 'current-menu-item',
			'current_page_parent'    => 'current-menu-parent',
			'page_item'              => 'menu-item',
			'page_item_has_children' => 'menu-item-has-children',
		);

		foreach ( $class_name_map as $page_class => $menu_class ) {
			if ( in_array( $page_class, $css_class ) ) {
				$css_class[] = $menu_class;
			}
		}

		return $css_class;
	}

}

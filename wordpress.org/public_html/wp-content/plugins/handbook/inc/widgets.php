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

	function __construct() {
		$widget_ops = array('classname' => 'widget_wporg_handbook_pages', 'description' => __( 'Your site&#8217;s Handbook Pages', 'wporg' ) );
		WP_Widget::__construct( self::get_widget_id_base(), __( 'Handbook Pages', 'wporg' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		$args['after_title'] = '</h2>' . "\n" . '<div class="menu-table-of-contents-container">' . "\n";
		$args['after_widget'] = '</div>';

		add_filter( 'widget_pages_args',    array( $this, 'handbook_post_type' ) );
		add_filter( 'page_css_class',       array( $this, 'amend_page_css_class' ) );
		parent::widget( $args, $instance );
		remove_filter( 'page_css_class',    array( $this, 'amend_page_css_class' ) );
		remove_filter( 'widget_pages_args', array( $this, 'handbook_post_type' ) );
	}

	function handbook_post_type( $args ) {
		$post = get_post();

		$this->post_types = (array) apply_filters( 'handbook_post_types', $this->post_types );
		$this->post_types = array_map( array( $this, 'append_suffix' ), $this->post_types );

		if ( in_array( $post->post_type, $this->post_types ) ) {
			$args['post_type'] = $post->post_type;
		}

		// Exclude root handbook page from the table of contents.
		$page = get_page_by_path( $this->append_suffix( $post->post_type ), OBJECT, $post->post_type );
		if ( ! $page ) {
			$slug = substr( $post->post_type, 0, -9 );
			$page = get_page_by_path( $slug, OBJECT, $post->post_type );
		}
		if ( $page ) {
			$args['exclude'] = $page->ID;
		}

		return $args;
	}

	function append_suffix( $t ) {
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
	function amend_page_css_class( $css_class ) {
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

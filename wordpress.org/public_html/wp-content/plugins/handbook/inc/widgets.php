<?php

class WPorg_Handbook_Pages_Widget extends WP_Widget_Pages {

	protected $post_types = array( 'handbook' );

	function __construct() {
		$widget_ops = array('classname' => 'widget_wporg_handbook_pages', 'description' => __( 'Your site&#8217;s Handbook Pages', 'wporg' ) );
		WP_Widget::__construct( 'handbook_pages', __( 'Handbook Pages', 'wporg' ), $widget_ops );
	}

	function widget( $args, $instance ) {
		add_filter( 'widget_pages_args', array( $this, 'handbook_post_type' ) );
		parent::widget( $args, $instance );
		remove_filter( 'widget_pages_args', array( $this, 'handbook_post_type' ) );
	}

	function handbook_post_type( $args ) {
		$post = get_post();

		$this->post_types = (array) apply_filters( 'handbook_post_types', $this->post_types );
		$this->post_types = array_map( array( $this, 'append_suffix' ), $this->post_types );

		if ( in_array( $post->post_type, $this->post_types ) ) {
			$args['post_type'] = $post->post_type;
		}
		return $args;
	}

	function append_suffix( $t ) {
		if ( in_array( $t, array( 'handbook', 'page' ) ) ) {
			return $t;
		}

		return $t . '-handbook';
	}
}

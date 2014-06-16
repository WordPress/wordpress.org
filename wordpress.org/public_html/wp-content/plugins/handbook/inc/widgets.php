<?php

class WPorg_Handbook_Widget extends WP_Widget {

	protected $post_types = 'handbook';

	function __construct() {
		$this->post_types = apply_filters( 'handbook_post_types', $this->post_types );
		if ( ! is_array( $this->post_types ) ) {
			$this->post_types = (array) $this->post_types;
		}
		$this->post_types = array_map( array( $this, 'append_suffix' ), $this->post_types );
		parent::__construct( 'handbook', 'Handbook Tools', array( 'classname' => 'widget_wporg_handbook', 'description' => 'Shows watch/unwatch links for handbook pages.' ) );
	}

	function widget() {
		if ( ! is_user_logged_in() )
			return;

		$post = get_post();
		if ( $post->post_type == 'page' || ( in_array( $post->post_type, $this->post_types ) && ! is_post_type_archive( $this->post_types ) ) ) {
			$watchlist = get_post_meta( $post->ID, '_wporg_watchlist', true );
			if ( $watchlist && in_array( get_current_user_id(), $watchlist ) ) {
				printf( '<p>You are watching this page. <a href="%s">Unwatch</a></p>',
				wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&post_id=' . $post->ID ), 'unwatch-' . $post->ID ) );
			} else {
				printf( '<p><a href="%s">Watch this page</a></p>',
				wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&watch=1&post_id=' . $post->ID ), 'watch-' . $post->ID ) );
			}
		} else {
			return;
		}

	}

	function append_suffix( $t ) {
		if ( 'handbook' == $t )
			return $t;

		return $t . '-handbook';
	}
}

class WPorg_Handbook_Widget_for_Pages extends WPorg_Handbook_Widget {

	protected $post_type = 'page';

	function __construct() {
		WP_Widget::__construct( 'handbook_for_pages', 'Handbook Tools (for Pages)', array( 'classname' => 'widget_wporg_handbook', 'description' => 'Shows watch/unwatch links for Pages.' ) );
	}
}

class WPorg_Handbook_Pages_Widget extends WP_Widget_Pages {

	protected $post_types = 'handbook';

	function __construct() {
		$widget_ops = array('classname' => 'widget_wporg_handbook_pages', 'description' => __( 'Your site&#8217;s Handbook Pages') );
		WP_Widget::__construct('handbook_pages', __('Handbook Pages'), $widget_ops);
	}

	function widget( $args, $instance ) {
		add_filter( 'widget_pages_args', array( $this, 'handbook_post_type' ) );
		parent::widget( $args, $instance );
		remove_filter( 'widget_pages_args', array( $this, 'handbook_post_type' ) );
	}

	function handbook_post_type( $args ) {
		$post = get_post();

		$this->post_types = apply_filters( 'handbook_post_types', $this->post_types );
		if ( ! is_array( $this->post_types ) ) {
			$this->post_types = (array) $this->post_types;
		}
		$this->post_types = array_map( array( $this, 'append_suffix' ), $this->post_types );

		if ( in_array( $post->post_type, $this->post_types ) ) {
			$args['post_type'] = $post->post_type;
		}
		return $args;
	}

	function append_suffix( $t ) {
		if ( 'handbook' == $t )
			return $t;

		return $t . '-handbook';
	}
}
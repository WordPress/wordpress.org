<?php

class WPorg_Handbook_Widget extends WP_Widget {

	protected $post_types = array( 'handbook' );

	function __construct() {
		$this->post_types = (array) apply_filters( 'handbook_post_types', $this->post_types );
		$this->post_types = array_map( array( $this, 'append_suffix' ), $this->post_types );
		parent::__construct( 'handbook', __( 'Handbook Tools', 'wporg' ), array( 'classname' => 'widget_wporg_handbook', 'description' => __( 'Shows watch/unwatch links for handbook pages.', 'wporg' ) ) );
	}

	function widget( $args ) {
		if ( ! is_user_logged_in() )
			return;

		$post = get_post();
		if ( $post->post_type == 'page' || ( in_array( $post->post_type, $this->post_types ) && ! is_post_type_archive( $this->post_types ) ) ) {
			$watchlist = get_post_meta( $post->ID, '_wporg_watchlist', true );
			if ( isset( $args['before_widget'] ) && $args['before_widget'] ) {
				echo $args['before_widget'];
			}
			echo '<p>';
			if ( $watchlist && in_array( get_current_user_id(), $watchlist ) ) {
				printf( __( 'You are watching this page. <a href="%s">Unwatch</a>', 'wporg' ),
				wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&post_id=' . $post->ID ), 'unwatch-' . $post->ID ) );
			} else {
				printf( __( '<a href="%s">Watch this page</a>', 'wporg' ),
				wp_nonce_url( admin_url( 'admin-post.php?action=wporg_watchlist&watch=1&post_id=' . $post->ID ), 'watch-' . $post->ID ) );
			}
			echo '</p>';
			if ( isset( $args['after_widget'] ) && $args['after_widget'] ) {
				echo $args['after_widget'];
			}
		} else {
			return;
		}

	}

	function append_suffix( $t ) {
		if ( in_array( $t, array( 'handbook', 'page' ) ) ) {
			return $t;
		}

		return $t . '-handbook';
	}
}

class WPorg_Handbook_Widget_for_Pages extends WPorg_Handbook_Widget {

	protected $post_types = array( 'page' );

	function __construct() {
		WP_Widget::__construct( 'handbook_for_pages', __( 'Handbook Tools (for Pages)', 'wporg' ), array( 'classname' => 'widget_wporg_handbook', 'description' => __( 'Shows watch/unwatch links for Pages.', 'wporg' ) ) );
	}
}

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

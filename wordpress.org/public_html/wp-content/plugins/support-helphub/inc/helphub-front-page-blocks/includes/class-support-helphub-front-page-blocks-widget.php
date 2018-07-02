<?php
/**
 * Introduce the controller for our widget blocks.
 *
 * @package HelpHub
 */

/**
 * Class Support_HelpHub_Front_Page_blocks_Widget
 */
class Support_HelpHub_Front_Page_Blocks_Widget extends WP_Widget {
	public function __construct() {
		$widget_options = array(
			'classname'   => 'helphub-front-page-block',
			'description' => __( 'Add a link block to support pages', 'wporg-forums' ),
		);

		parent::__construct( 'helphub_front_page_block', __( '(HelpHub) Link block', 'wporg-forums' ), $widget_options );
	}

	/**
	 * Output the widget on the front end.
	 *
	 * @param array $args     The widget arguments, passed on from the themes widget area.
	 * @param array $instance This individual widgets settings.
	 *
	 * @return void
	 */
	public function widget( $args, $instance ) {
		include( dirname( __FILE__ ) . '/widget-front-end.php' );
	}

	/**
	 * Generate the widget settings.
	 *
	 * @param array $instance The widget instance and arguments.
	 *
	 * @return void
	 */
	public function form( $instance ) {
		include( dirname( __FILE__ ) . '/widget-back-end.php' );
	}

	/**
	 * Save the widget settings from the admin.
	 *
	 * @param array $new_instance The old widget instance, for comparison.
	 * @param array $old_instance The new widget instance, to be saved.
	 *
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$save_instance = array();

		$save_instance['icon']        = ( ! empty( $new_instance['icon'] ) ? strip_tags( $new_instance['icon'] ) : '' );
		$save_instance['title']       = ( ! empty( $new_instance['title'] ) ? strip_tags( $new_instance['title'] ) : '' );
		$save_instance['description'] = ( ! empty( $new_instance['description'] ) ? strip_tags( $new_instance['description'] ) : '' );
		$save_instance['categoryid']  = ( ! empty( $new_instance['categoryid'] ) ? strip_tags( $new_instance['categoryid'] ) : '' );
		$save_instance['menu']        = ( ! empty( $new_instance['menu'] ) ? strip_tags( $new_instance['menu'] ) : '' );

		return $save_instance;
	}
}

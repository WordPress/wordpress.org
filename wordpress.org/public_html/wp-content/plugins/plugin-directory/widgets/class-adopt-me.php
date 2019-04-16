<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

/**
 * A Widget to display adopt plugin link for a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Adopt_Me extends \WP_Widget {

	/**
	 * Adopt_Me constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_adopt_me', __( 'Plugin Adopt Me', 'wporg-plugins' ), array(
			'classname'   => 'plugin-adopt-me',
			'description' => __( 'Displays an adopt me panel.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Adopt this plugin', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );
		$is_adopt_me = has_term( 'adopt-me', 'plugin_section' );

		if ( $is_adopt_me ) {

			echo $args['before_widget'];
			echo $args['before_title'] . $title . $args['after_title'];
			?>

			<div>
				<p>
					<?php _e( 'This plugin is seeking new, active, developers. Are you interested in assuming that responsibility?', 'wporg-plugins' ); ?></p>
				<p>
					<a class="button" href="https://developer.wordpress.org/plugins/wordpress-org/take-over-an-existing-plugin/"><?php _e( 'Read more', 'wporg-plugins' ); ?></a>
				</p>
			</div>
			<?php

			echo $args['after_widget'];
		}
	}
}

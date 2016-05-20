<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display meta information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Meta extends \WP_Widget {

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_meta', __( 'Plugin Meta', 'wporg-plugins' ), array(
			'classname'   => 'plugin-meta',
			'description' => __( 'Displays plugin meta information.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		?>

		<ul>
			<li><?php printf( __( 'Last updated: %s ago', 'wporg-plugins' ), '<span itemprop="dateModified" content="' . esc_attr( get_post_modified_time( 'c' ) ) . '">' . human_time_diff( get_post_modified_time() ) . '</span>' ); ?></li>
			<li><?php printf( __( 'Active installs: %s', 'wporg-plugins' ), Template::active_installs( false ) ); ?></li>
			<li><?php printf( __( 'Category: %s', 'wporg-plugins' ), get_the_term_list( get_post()->ID, 'plugin_category', '', ', ' ) ); ?></li>
			<li><?php printf( __( 'Designed to work with: %s', 'wporg-plugins' ), get_the_term_list( get_post()->ID, 'plugin_category', '', ', ' ) ); ?></li>
		</ul>

		<?php
		echo $args['after_widget'];
	}
}

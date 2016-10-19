<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display committer information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Plugin_Review extends \WP_Widget {

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_review', __( 'Plugin Review Tools', 'wporg-plugins' ), array(
			'classname'   => 'plugin-review',
			'description' => __( 'Displays plugin review information.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$post = get_post();

		echo $args['before_widget'];
		?>

		<h3 class="screen-reader-text"><?php echo apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Committers', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base ); ?></h3>


		<?php
		echo $args['after_widget'];
	}
}

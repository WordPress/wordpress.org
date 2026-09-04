<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display contributor information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Contributors extends \WP_Widget {

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_contributors', __( 'Plugin Contributors', 'wporg-plugins' ), array(
			'classname'   => 'plugin-contributors',
			'description' => __( 'Displays contributor information.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$contributors = Template::get_plugin_contributors();

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Contributors', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];
		?>

		<ul id="contributors-list" class="contributors-list">
			<?php foreach ( $contributors as $contributor ) : ?>
			<li>
				<?php echo get_avatar( $contributor->ID, 32 ); ?>
				<a href="<?php echo esc_url( "https://profiles.wordpress.org/{$contributor->user_nicename}/" ); ?>">
					<?php echo $contributor->display_name ?: $contributor->user_nicename; ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>

		<?php
		echo $args['after_widget'];
	}
}

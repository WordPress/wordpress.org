<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display support information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Support extends \WP_Widget {

	/**
	 * Support constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_support', __( 'Plugin Support', 'wporg-plugins' ), array(
			'classname'   => 'plugin-support',
			'description' => __( 'Displays plugin support information.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Support', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );

		$post        = get_post();
		$threads     = get_post_meta( $post->ID, 'support_threads', true ) ?: 0;
		$resolved    = get_post_meta( $post->ID, 'support_threads_resolved', true ) ?: 0;
		$resolutions = (bool) $threads;
		$support_url = Template::get_support_url();

		if ( false === strpos( $support_url, 'https://wordpress.org' ) ) {
			$resolutions = false;
		}

		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];

		if ( $resolutions ) :
		?>
			<p class="aside"><?php _e( 'Issues resolved in last two months:', 'wporg-plugins' ); ?></p>
			<p class="counter-container">
				<span class="counter-back">
					<span class="counter-bar" style="width: <?php echo esc_attr( 100 * $resolved / $threads ); ?>%;"></span>
				</span>
				<span class="counter-count">
					<?php
					/* Translators: 1: Amount of resolved threads; 2: Amount of total threads; */
					printf( __( '%1$s out of %2$s', 'wporg-plugins' ), $resolved, $threads );
					?>
				</span>
			</p>

		<?php else : ?>
			<p><?php _e( 'Got something to say? Need help?', 'wporg-plugins' ); ?></p>
		<?php endif; ?>

		<p>
			<a class="button button-secondary" href="<?php echo esc_url( $support_url ); ?>"><?php _e( 'View support forum', 'wporg-plugins' ); ?></a>
		</p>

		<?php
		echo $args['after_widget'];
	}
}

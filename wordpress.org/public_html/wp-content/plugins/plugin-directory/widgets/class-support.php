<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

/**
 * A Widget to display support information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Support extends \WP_Widget {

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
		$post        = get_post();
		$resolutions = true;
		$support_url = 'https://wordpress.org/support/plugin/' . $post->post_name;

		/*
		 * bbPress and BuddyPress get special treatment here.
		 * In the future we could open this up to all plugins that define a custom support URL.
		 */
		if ( 'buddypress' === $post->post_name ) {
			$resolutions = false;
			$support_url = 'https://buddypress.org/support/';
		} else if ( 'bbpress' === $post->post_name ) {
			$resolutions = false;
			$support_url = 'https://bbpress.org/forums/';
		}

		echo $args['before_widget'];
		?>

		<h4><?php _e( 'Support', 'wporg-plugins' ); ?></h4>

		<?php if ( $resolutions ) : ?>
		<p>
			<?php
			/* translators: 1: Number of resolved threads; 2: Number of all threads; */
			printf( __( '%1$s of %2$s support threads in the last two months have been marked resolved.', 'wporg-plugins' ),
				get_post_meta( $post->ID, 'support_threads', true ),
				get_post_meta( $post->ID, 'support_threads_resolved', true )
			);
			?>
		</p>
		<?php endif; ?>

		<p><?php _e( 'Got something to say? Need help?', 'wporg-plugins' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( $support_url ); ?>"><?php _e( 'View support forum', 'wporg-plugins' ); ?></a></p>

		<?php
		echo $args['after_widget'];
	}
}

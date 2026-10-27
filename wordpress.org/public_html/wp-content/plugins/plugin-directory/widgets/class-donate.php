<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

/**
 * A Widget to display a donate link for a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Donate extends \WP_Widget {

	/**
	 * Donate constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_donate', __( 'Plugin Donate', 'wporg-plugins' ), array(
			'classname'   => 'plugin-donate',
			'description' => __( 'Displays a donate link.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$donate_link = get_post_meta( get_the_ID(), 'donate_link', true );

		if ( $donate_link ) {
			$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Donate', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );

			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Registered sidebar wrapper markup.
			echo $args['before_widget'];
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Registered sidebar wrapper markup.
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
			?>

			<p class="aside"><?php esc_html_e( 'Would you like to support the advancement of this plugin?', 'wporg-plugins' ); ?></p>
			<p>
				<a href="<?php echo esc_url( $donate_link ); ?>" rel="nofollow ugc">
					<?php esc_html_e( 'Donate to this plugin', 'wporg-plugins' ); ?>
				</a>
			</p>

			<?php
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Registered sidebar wrapper markup.
			echo $args['after_widget'];
		}
	}
}

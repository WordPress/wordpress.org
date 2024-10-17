<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display a plugins rating, and allow rating.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Ratings extends \WP_Widget {

	/**
	 * Ratings constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_ratings', __( 'Plugin Ratings', 'wporg-plugins' ), array(
			'classname'   => 'plugin-ratings',
			'description' => __( 'Displays the plugin ratings.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Ratings', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );
		$post  = get_post();

		if ( class_exists( '\WPORG_Ratings' ) ) {
			$rating  = \WPORG_Ratings::get_avg_rating( 'plugin', $post->post_name ) ?: 0;
		} else {
			$rating  = get_post_meta( $post->ID, 'rating', true ) ?: 0;
		}

		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];

		if ( $rating ) {
			echo do_blocks( '<!-- wp:wporg/ratings-stars /-->' );
			echo do_blocks( '<!-- wp:wporg/ratings-bars /-->' );
		} else { ?>

			<div class="rating">
				<p><?php _e( 'This plugin has not been rated yet.', 'wporg-plugins' ); ?></p>
			</div>

		<?php } // $rating ?>

		<?php if ( is_user_logged_in() ) : ?>
			<div class="ratings-footer">
				<div class="user-rating">
					<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/' . $post->post_name . '/reviews/#new-post' ); ?>"><?php _e( 'Add my review', 'wporg-plugins' ); ?></a>
				</div>
				<?php if ( $rating ) : ?>
					<div class="user-rating">
						<a href="<?php echo esc_url( 'https://wordpress.org/support/plugin/' . $post->post_name . '/reviews/' ); ?>"><?php _e( 'See all', 'wporg-plugins' ); ?></a>
					</div>
				<?php endif; ?>
			</div>

		<?php else: ?>
			<div class="user-rating">
				<a href="<?php echo esc_url( wp_login_url( 'https://wordpress.org/support/plugin/' . get_post()->post_name . '/reviews/#new-post' ) ); ?>" rel="nofollow" title="<?php esc_attr_e( 'Log in to WordPress.org', 'wporg-plugins' ); ?>"><?php _e( 'Log in to submit a review.', 'wporg-plugins' ); ?></a>
			</div>
		<?php
		endif;

		echo $args['after_widget'];
	}
}

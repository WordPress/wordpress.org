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

		$post        = get_post();
		$rating      = get_post_meta( $post->ID, 'rating', true );
		$ratings     = get_post_meta( $post->ID, 'ratings', true ) ?: array();
		$num_ratings = array_sum( $ratings );

		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];
		?>
		<meta itemprop="ratingCount" content="<?php echo esc_attr( $num_ratings ) ?>"/>

		<?php if ( $rating ) : ?>

			<div class="rating">
				<?php echo Template::dashicons_stars( $rating ); ?>
				<meta itemprop="ratingValue" content="<?php echo esc_attr( $rating ) ?>">
			</div>

			<ul class="ratings-list">
				<?php foreach ( range( 5, 1 ) as $stars ) :
					$rating_bar_width = $num_ratings ? 100 * $ratings[ $stars ] / $num_ratings : 0;
					?>
					<li class="counter-container">
						<a href="<?php echo esc_url( 'https://wordpress.org/support/view/plugin-reviews/' . $post->post_name . '?filter=' . $stars ); ?>">
							<span class="counter-label"><?php printf( _n( '%d star', '%d stars', $stars, 'wporg-plugin' ), $stars ); ?></span>
					<span class="counter-back">
						<span class="counter-bar" style="width: <?php echo $rating_bar_width; ?>%;"></span>
					</span>
							<span class="counter-count"><?php echo $ratings[ $stars ]; ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php else : ?>
			
			<div class="rating">
				<p><?php _e( 'This plugin has not been rated yet.', 'wporg-plugins' ); ?></p>
			</div>

		<?php endif; // $rating

		if ( is_user_logged_in() ) : ?>
			<div class="user-rating">
				<a class="button button-secondary" href="<?php echo esc_url( 'https://wordpress.org/support/view/plugin-reviews/' . $post->post_name . '#postform' ); ?>"><?php _e( 'Add your review', 'wporg-plugins' ); ?></a>
			</div>
		<?php
		endif;

		echo $args['after_widget'];
	}
}

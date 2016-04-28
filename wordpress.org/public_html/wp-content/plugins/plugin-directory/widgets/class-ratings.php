<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display a plugins rating, and allow rating.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Ratings extends \WP_Widget {

	public function __construct() {
		$widget_ops = array( 
			'classname' => 'plugin-ratings',
			'description' => 'Displays the plugin ratings.',
		);
		parent::__construct( 'plugin_ratings', 'Plugin Ratings', $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];

		$post = get_post();

		$rating      = get_post_meta( $post->ID, 'rating', true );
		$ratings     = get_post_meta( $post->ID, 'ratings', true ) ?: array();
		$num_ratings = array_sum( $ratings );

		$user_rating = 0;
		if ( is_user_logged_in() && function_exists( 'wporg_get_user_rating' ) ) {
			$user_rating = wporg_get_user_rating( 'plugin', $post->post_name, get_current_user_id() );
			if ( ! $user_rating ) {
				$user_rating = 0;
			}
		}

		?>

		<meta itemprop="ratingCount" content="<?php echo esc_attr( $num_ratings ) ?>"/>
		<h4><?php _e( 'Ratings', 'wporg-plugins' ); ?></h4>

		<?php if ( $rating ) : ?>
		<div class="rating">
			<?php echo Template::dashicons_stars( $rating ); ?>
			<p class="description"><?php printf( __( '%s out of 5 stars.', 'wporg-plugins' ), '<span itemprop="ratingValue">' . $rating . '</span>' ); ?></p>
		</div>
		<?php else : ?>
		<div class="rating">
			<div class="ratings"><?php _e( 'This plugin has not been rated yet.', 'wporg-plugins' ); ?></div>
		</div>
		<?php endif; // $rating

		if ( $ratings ) : ?>
		<ul class="ratings-list">
			<?php foreach ( range( 5, 1 ) as $stars ) :
				$rating_bar_width = $num_ratings ? 100 * $ratings[ $stars ] / $num_ratings : 0;
			?>
			<li class="counter-container">
				<a href="https://wordpress.org/support/view/plugin-reviews/<?php echo $post->post_name; ?>?filter=<?php echo $stars; ?>" title="<?php echo esc_attr( sprintf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $stars, 'wporg-plugins' ), $stars ) ); ?>">
					<span class="counter-label"><?php printf( _n( '%d star', '%d stars', $stars, 'wporg-plugin' ), $stars ); ?></span>
					<span class="counter-back">
						<span class="counter-bar" style="width: <?php echo $rating_bar_width; ?>%;"></span>
					</span>
					<span class="counter-count"><?php echo $ratings[ $stars ]; ?></span>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>
		<?php
		endif; // $ratings

		if ( is_user_logged_in() ) {
			echo '<div class="user-rating">';
			echo Template::dashicons_stars( array(
				'rating' => $user_rating,
				'template' => '<a class="%1$s" href="https://wordpress.org/support/view/plugin-reviews/' . $post->post_name . '?rate=%2$d#postform"></a>'
			) );
			echo '</div>';
			?>
			<script>
			jQuery(document).ready( function($) {
				var $rating = $( '.user-rating div' ),
					$stars = $rating.find( '.dashicons' ),
					current_rating = $rating.data( 'rating' ),
					rating_clear_timer = 0;

				$stars.mouseover( function() {
					var $this = $(this),
						$prev_items = $this.prevAll(),
						$next_items = $this.nextAll(),
						rating = $prev_items.length + 1;

					if ( rating_clear_timer ) {
						clearTimeout( rating_clear_timer );
						rating_clear_timer = 0;
					}

					if ( rating == current_rating ) {
						return;
					}

					$this.removeClass( 'dashicons-star-empty' ).addClass( 'dashicons-star-filled' );
					$prev_items.removeClass( 'dashicons-star-empty' ).addClass( 'dashicons-star-filled' );
					$next_items.removeClass( 'dashicons-star-filled' ).addClass( 'dashicons-star-empty' );

					$rating.prop( 'title', $rating.data( 'title-template' ).replace( '%s', rating ).replace( '%d', rating ) );
					current_rating = rating;
				} );
				$rating.mouseout( function() {
					var clear_callback = function() {
						var rating = $rating.data( 'rating' );
						if ( rating == current_rating ) {
							return;
						}
						if ( rating ) {
							$( $stars.get( rating-1 ) ).mouseover();
						} else {
							$stars.removeClass( 'dashicons-star-filled' ).addClass( 'dashicons-star-empty' );
						}
					};

					if ( ! rating_clear_timer ) {
						rating_clear_timer = setTimeout( clear_callback, 2000 );
					}
				} );
			} );
			</script>
			<?php
		}

		echo $args['after_widget'];
	}
}

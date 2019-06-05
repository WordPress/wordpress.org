<?php
namespace WordPressdotorg\Plugin_Directory\Shortcodes;

use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * The [wporg-plugins-reviews] shortcode handler to display plugin reviews.
 *
 * @package WordPressdotorg\Plugin_Directory\Shortcodes
 */
class Reviews {

	/**
	 * @return string
	 */
	static function display() {
		$reviews      = Tools::get_plugin_reviews( get_post()->post_name );
		$ratings      = get_post_meta( get_the_ID(), 'ratings', true ) ?: array();
		$review_count = array_sum( $ratings );

		if ( empty( $reviews ) ) {
			return '';
		}

		ob_start();
		?>

		<div class="plugin-reviews">
			<?php
			foreach ( $reviews as $review ) :
				setup_postdata( $review );
				?>
				<article class="plugin-review">
					<div class="review-avatar">
						<?php echo get_avatar( get_the_author_meta( 'ID' ), 60 ); ?>
					</div><div class="review">
						<header>
							<h3 class="review-title"><a class="url" href="<?php echo esc_url( 'https://wordpress.org/support/topic/' . $review->post_name . '/' ); ?>"><?php echo get_the_title( $review ); ?></a></h3>
							<?php echo Template::dashicons_stars( $review->post_rating ); ?>
							<span class="review-author author vcard"><?php the_author_posts_link(); ?></span>
						</header>
						<div class="review-content"><?php echo wp_strip_all_tags(get_the_content()); ?></div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
		<?php wp_reset_postdata(); ?>

		<a class="reviews-link" href="<?php echo esc_url( 'https://wordpress.org/support/plugin/' . get_post()->post_name . '/reviews/' ); ?>">
			<?php
				/* translators: %s: number of reviews */
				printf( _n( 'Read all %s review', 'Read all %s reviews', $review_count, 'wporg-plugins' ), number_format_i18n( $review_count ) );
			?>
		</a>

		<?php
		return ob_get_clean();
	}
}

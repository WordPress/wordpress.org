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
			return sprintf(
				'<div class="notice notice-warning notice-alt"><p>%s</p></div>',
				__( 'There are no reviews for this plugin.', 'wporg-plugins' )
			);
		}

		ob_start();
		?>

		<div class="plugin-reviews">
			<?php
			// Switch to the Support Forum so that Template functions that call get_post() work as intended.
			if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
				switch_to_blog( WPORG_SUPPORT_FORUMS_BLOGID );
			}

			foreach ( $reviews as $review ) {
				$GLOBALS['post'] = $review; // Override the_post();
				setup_postdata( $review );
				?>
				<article class="plugin-review">
					<div class="review-avatar">
						<?php echo get_avatar( get_the_author_meta( 'ID' ), 60 ); ?>
					</div><div class="review">
						<header>
							<div class="header-top">
								<?php echo Template::dashicons_stars( $review->post_rating ); ?>
								<h3 class="review-title"><a class="url" href="<?php echo esc_url( 'https://wordpress.org/support/topic/' . $review->post_name . '/' ); ?>"><?php echo get_the_title( $review ); ?></a></h3>
							</div>
							<div class="header-bottom">
								<span class="review-author author vcard"><?php the_author_posts_link(); ?></span>
								<span class="review-date"><?php echo date_i18n( get_option( 'date_format' ), strtotime( $review->post_modified ) ); ?></span>
							</div>
						</header>
						<div class="review-content"><?php echo wp_strip_all_tags( get_the_content() ); ?></div>
					</div>
				</article>
			<?php
			}

			// Reset back to the plugin post.
			wp_reset_postdata();
			if ( defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
				restore_current_blog();
			}
		?>
		</div>

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

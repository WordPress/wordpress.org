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
		$reviews = Tools::get_plugin_reviews( get_post()->post_name, array(
			'number' => 2,
		) );

		if ( empty( $reviews ) ) {
			return '';
		}

		ob_start();
		?>

		<div class="plugin-reviews">
			<?php
			foreach ( $reviews as $review ) :
				$reviewer = get_user_by( 'id', $review->topic_poster );
				if ( ! $reviewer ) :
					continue;
				endif;
				?>
				<article class="plugin-review">
					<div class="review-avatar">
						<?php echo get_avatar( $reviewer->ID, 60 ); ?>
					</div><div class="review">
						<header>
							<h3 class="review-title"><?php echo $review->topic_title; ?></h3>
							<?php echo Template::dashicons_stars( $review->rating ); ?>
							<span class="review-author author vcard"><a class="url fn n" href="<?php esc_url( 'https://profile.wordpress.org/' . $reviewer->user_nicename . '/' ); ?>"><?php echo Template::encode( $reviewer->display_name ); ?></a></span>
						</header>
						<p class="review-content"><?php echo $review->post_text; ?></p>
					</div>
				</article>
			<?php endforeach; ?>
		</div>

		<?php
		return ob_get_clean();
	}
}

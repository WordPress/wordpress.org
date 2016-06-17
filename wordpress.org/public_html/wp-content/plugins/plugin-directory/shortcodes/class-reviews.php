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
		$post    = get_post();
		$reviews = array(); //Tools::get_plugin_reviews( $post->post_name );

		if ( empty( $reviews ) ) {
			return '';
		}

		ob_start();
		?>

		<ul class="plugin-reviews">
			<?php
			foreach ( $reviews as $review ) :
				$reviewer = get_user_by( 'id', $review->user_id );
				if ( ! $reviewer ) :
					continue;
				endif;
				?>
				<li>
					<article class="plugin-review">
						<div class="review-avatar"><?php echo get_avatar( $reviewer->ID, 32 ); ?></div>
						<div class="review">
							<header>
								<h3><?php echo $review->topic_ctitle; ?></h3>
								<?php echo Template::dashicons_stars( $review->rating ); ?>
								<span class="byline"><?php printf( esc_html_x( 'By %s', 'post author', 'wporg-plugins' ), '<span class="author vcard"><a class="url fn n" href="' . esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ) . '">' . esc_html( Template::encode( get_the_author() ) ) . '</a></span>' ); ?></span>
							</header>
							<div class="review-content"><?php echo $review->topic_text; ?></div>
						</div>
					</article>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php
		return ob_get_clean();
	}
}

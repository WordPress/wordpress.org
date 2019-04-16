<?php
namespace WordCamp\Blocks\Sponsors;
defined( 'WPINC' ) || die();

use function WordCamp\Blocks\Shared\Content\{ get_all_the_content };
use function WordCamp\Blocks\Shared\Components\{ render_featured_image };

/** @var array     $attributes */
/** @var \WP_Post  $sponsor */
/** @var array     $sponsor_featured_urls */

setup_postdata( $sponsor );

?>
<div class="wordcamp-sponsor-details wordcamp-sponsor-details-<?php echo sanitize_html_class( $sponsor->post_name ); ?> ">

	<?php if ( $attributes['show_name'] ) { ?>
		<h3 class="wordcamp-sponsor-title wordcamp-item-title">
			<a href="<?php echo esc_attr( get_permalink( $sponsor->ID ) ); ?>"><?php echo esc_html( get_the_title( $sponsor ) ); ?></a>
		</h3>
	<?php } ?>

	<?php if ( $attributes['show_logo'] ) { ?>
		<?php echo wp_kses_post(
			render_featured_image(
				array( 'wordcamp-sponsor-featured-image', 'wordcamp-sponsor-logo' ),
				$sponsor,
				$attributes['featured_image_width'],
				get_permalink( $sponsor )
			)
		); ?>
	<?php } ?>

	<?php if ( 'none' !== $attributes['content'] ) { ?>
		<?php if ( 'full' === $attributes['content'] ) { ?>
			<?php echo wp_kses_post( wpautop( get_all_the_content( $sponsor ) ) ); ?>
		<?php } elseif ( 'excerpt' === $attributes['content'] ) { ?>
			<?php echo wp_kses_post( wpautop( apply_filters( 'the_excerpt', get_the_excerpt() ) ) ); ?>
		<?php } ?>
	<?php } ?>

	<?php if ( 'full' === $attributes['content'] ) : ?>
		<p class="wordcamp-item-permalink">
			<a href="<?php echo esc_url( get_permalink( $sponsor ) ); ?>">
				<?php esc_html_e( 'Visit sponsor page', 'wordcamporg' ); ?>
			</a>
		</p>
	<?php endif; ?>
</div>

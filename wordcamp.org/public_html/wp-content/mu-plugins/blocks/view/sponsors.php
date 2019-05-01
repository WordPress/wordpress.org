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

<?php if ( true === $attributes['show_name'] ) : ?>
	<h3 class="wordcamp-sponsor-title wordcamp-item-title">
		<a href="<?php echo esc_attr( get_permalink( $sponsor ) ); ?>">
			<?php echo wp_kses_post( get_the_title( $sponsor ) ); ?>
		</a>
	</h3>
<?php endif; ?>

<?php if ( true === $attributes['show_logo'] ) : ?>
	<?php echo wp_kses_post(
		render_featured_image(
			$sponsor,
			$attributes['featured_image_width'],
			[ 'wordcamp-sponsor-featured-image', 'wordcamp-sponsor-logo' ],
			get_permalink( $sponsor )
		)
	); ?>
<?php endif; ?>

<?php if ( 'none' !== $attributes['content'] ) : ?>
	<div class="wordcamp-item-content wordcamp-sponsor-content-<?php echo esc_attr( $attributes['content'] ); ?>">
		<?php if ( 'full' === $attributes['content'] ) : ?>
			<?php echo wp_kses_post( wpautop( get_all_the_content( $sponsor ) ) ); ?>
		<?php elseif ( 'excerpt' === $attributes['content'] ) : ?>
			<?php echo wp_kses_post( wpautop( apply_filters( 'the_excerpt', get_the_excerpt() ) ) ); ?>
		<?php endif; ?>
	</div>
<?php endif; ?>

<?php if ( 'full' === $attributes['content'] ) : ?>
	<p class="wordcamp-item-permalink">
		<a href="<?php echo esc_url( get_permalink( $sponsor ) ); ?>">
			<?php esc_html_e( 'Visit sponsor page', 'wordcamporg' ); ?>
		</a>
	</p>
<?php endif; ?>

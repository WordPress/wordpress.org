<?php

namespace WordCamp\Blocks\Organizers;
use function WordCamp\Blocks\Shared\Content\{ get_all_the_content };

defined( 'WPINC' ) || die();

/**
 * @var array  $attributes
 * @var array  $organizers
 * @var string $container_classes
 */

if ( empty( $organizers ) ) {
	return;
}

// Note that organizer posts are not 'public', so there are no permalinks.
?>

<ul class="<?php echo esc_attr( $container_classes ); ?>">
	<?php foreach ( $organizers as $organizer ) : ?>
		<?php setup_postdata( $organizer ); ?>

		<li class="wordcamp-block-post-list-item wordcamp-organizer wordcamp-organizer-<?php echo sanitize_html_class( $organizer->post_name ); ?> wordcamp-clearfix">
			<h3 class="wordcamp-item-title wordcamp-organizer-title">
				<?php echo wp_kses_post( get_the_title( $organizer ) ); ?>
			</h3>

			<?php if ( true === $attributes['show_avatars'] ) : ?>
				<div class="wordcamp-organizer-avatar-container align-<?php echo esc_attr( $attributes['avatar_align'] ); ?>">
					<?php echo get_avatar(
						$organizer->_wcpt_user_id,
						$attributes['avatar_size'],
						'',
						sprintf( __( 'Avatar of %s', 'wordcamporg'), get_the_title( $organizer ) ),
						[
							'class'         => 'wordcamp-organizer-avatar',
							'force_display' => true,
						]
					); ?>
				</div>
			<?php endif; ?>

			<?php if ( 'none' !== $attributes['content'] ) : ?>
				<div class="wordcamp-item-content wordcamp-organizer-content-<?php echo esc_attr( $attributes['content'] ); ?>">
					<?php if ( 'full' === $attributes['content'] ) : ?>
						<?php echo wp_kses_post( wpautop( get_all_the_content( $organizer ) ) ); ?>
					<?php elseif ( 'excerpt' === $attributes['content'] ) : ?>
						<?php echo wp_kses_post( wpautop( apply_filters( 'the_excerpt', get_the_excerpt() ) ) ); ?>
					<?php endif; ?>
				</div>
			<?php endif; ?>

		</li>
	<?php endforeach; ?>
	<?php wp_reset_postdata(); ?>
</ul>

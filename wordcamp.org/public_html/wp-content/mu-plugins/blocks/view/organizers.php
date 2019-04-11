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

?>

<ul class="<?php echo esc_attr( $container_classes ); ?>">
	<?php foreach ( $organizers as $organizer ) : ?>
		<?php setup_postdata( $organizer ); ?>

		<li class="wordcamp-block-post-list-item wordcamp-organizer wordcamp-organizer-<?php echo sanitize_html_class( $organizer->post_name ); ?> wordcamp-clearfix">
			<h3 class="wordcamp-item-title wordcamp-organizer-title">
				<a href="<?php echo esc_url( get_permalink( $organizer ) ); ?>">
					<?php echo wp_kses_post( get_the_title( $organizer ) ); ?>
				</a>
			</h3>

			<?php if ( true === $attributes['show_avatars'] ) : ?>
				<div class="wordcamp-organizer-avatar-container align-<?php echo esc_attr( $attributes['avatar_align'] ); ?>">
					<a href="<?php echo esc_url( get_permalink( $organizer ) ); ?>" class="wordcamp-organizer-avatar-link">
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
					</a>
				</div>
			<?php endif; ?>

			<?php if ( 'none' !== $attributes['content'] ) : ?>
				<div class="wordcamp-item-content wordcamp-organizer-content-<?php echo esc_attr( $attributes['content'] ); ?>">
					<?php if ( 'full' === $attributes['content'] ) : ?>
						<?php echo wp_kses_post( wpautop( get_all_the_content( $organizer ) ) ); ?>

						<p class="wordcamp-item-permalink">
							<a href="<?php echo esc_url( get_permalink( $organizer ) ); ?>">
								<?php esc_html_e( 'Visit organizer page', 'wordcamporg' ); ?>
							</a>
						</p>

					<?php elseif ( 'excerpt' === $attributes['content'] ) : ?>
						<?php wpautop( the_excerpt() ); ?>

						<?php if ( true === $attributes['excerpt_more'] ) : ?>
							<p class="wordcamp-item-permalink">
								<a href="<?php echo esc_url( get_permalink( $organizer ) ); ?>" class="wordcamp-organizer-permalink">
									<?php esc_html_e( 'Read more', 'wordcamporg' ); ?>
								</a>
							</p>
						<?php endif; ?>

					<?php endif; ?>
				</div>
			<?php endif; ?>

		</li>
	<?php endforeach; ?>
	<?php wp_reset_postdata(); ?>
</ul>

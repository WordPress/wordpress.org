<?php

namespace WordCamp\Blocks\Speakers;
defined( 'WPINC' ) || die();

/** @var array $attributes */
/** @var array $speakers */
/** @var array $sessions */

$container_classes = [
	'wordcamp-speakers-block',
	'layout-' . sanitize_html_class( $attributes['layout'] ),
	( 'grid' === $attributes['layout'] ) ? 'grid-columns-' . absint( $attributes['grid_columns'] ) : '',
	sanitize_html_class( $attributes['className'] ),
];
?>

<?php if ( ! empty( $speakers ) ) : ?>
	<ul class="<?php echo implode( ' ', $container_classes ); ?>">
		<?php foreach ( $speakers as $post ) : setup_postdata( $post ); ?>
			<li class="wordcamp-speaker wordcamp-speaker-<?php echo sanitize_html_class( $post->post_name ); ?>">
				<h3 class="wordcamp-speaker-name-heading">
					<?php echo get_the_title( $post ); ?>
				</h3>

				<?php if ( true === $attributes['show_avatars'] ) : ?>
					<?php
					echo get_avatar(
						$post->_wcb_speaker_email,
						$attributes['avatar_size'],
						'',
						sprintf( __( 'Avatar of %s', 'wordcamporg'), get_the_title( $post ) ),
						[
							'class'         => [
								'wordcamp-speaker-avatar',
								'align-' . sanitize_html_class( $attributes['avatar_align'] )
							],
							'force_display' => true,
						]
					);
					?>
				<?php endif; ?>

				<?php if ( 'none' !== $attributes['content'] || true === $attributes['speaker_link'] ) : ?>
					<div class="wordcamp-speaker-content">
						<?php if ( 'full' === $attributes['content'] ) : ?>
							<?php echo trim( apply_filters( 'the_content', maybe_add_more_link( get_the_content( '' ), $attributes['speaker_link'], $post ) ) ); ?>
						<?php elseif ( 'excerpt' === $attributes['content'] ) : ?>
							<?php echo trim( apply_filters( 'the_excerpt', maybe_add_more_link( get_the_excerpt(), $attributes['speaker_link'], $post ) ) ); ?>
						<?php elseif ( 'none' === $attributes['content'] ) : ?>
							<?php echo trim( maybe_add_more_link( '', $attributes['speaker_link'], $post ) ); ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( true === $attributes['show_session'] && ! empty( $sessions[ $post->ID ] ) ) : ?>
					<h4 class="wordcamp-speaker-session-heading">
						<?php echo esc_html( _n( 'Session', 'Sessions', count( $sessions[ $post->ID ] ), 'wordcamporg' ) ); ?>
					</h4>

					<ul class="wordcamp-speaker-session-list">
						<?php foreach ( $sessions[ $post->ID ] as $session ) : ?>
							<li class="wordcamp-speaker-session-content">
								<a class="wordcamp-speaker-session-link" href="<?php echo esc_url( get_permalink( $session ) ); ?>">
									<?php echo get_the_title( $session ); ?>
								</a>
								<span class="wordcamp-speaker-session-info">
									<?php if ( ! empty( $tracks = get_the_terms( $session, 'wcb_track' ) ) && ! is_wp_error( $tracks ) ) : ?>
										<?php
											printf(
												/* translators: 1: A date; 2: A time; 3: A location; */
												esc_html__( '%1$s at %2$s in %3$s', 'wordcamporg' ),
												date_i18n( get_option( 'date_format' ), $session->_wcpt_session_time ),
												date_i18n( get_option( 'time_format' ), $session->_wcpt_session_time ),
												esc_html( $tracks[0]->name )
											);
										?>
									<?php else : ?>
										<?php
											printf(
												/* translators: 1: A date; 2: A time; */
												esc_html__( '%1$s at %2$s', 'wordcamporg' ),
												date_i18n( get_option( 'date_format' ), $session->_wcpt_session_time ),
												date_i18n( get_option( 'time_format' ), $session->_wcpt_session_time )
											);
										?>
									<?php endif; ?>
								</span>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</li>
		<?php endforeach; wp_reset_postdata(); ?>
	</ul>
<?php endif; ?>

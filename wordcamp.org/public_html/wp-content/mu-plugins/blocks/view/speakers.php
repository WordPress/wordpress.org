<?php
namespace WordCamp\Blocks\Speakers;
defined( 'WPINC' ) || die();

use function WordCamp\Blocks\Shared\Content\{ get_all_the_content };

/** @var array  $attributes */
/** @var array  $speakers */
/** @var array  $sessions */
/** @var string $container_classes */

?>

<?php if ( ! empty( $speakers ) ) : ?>
	<ul class="<?php echo esc_attr( $container_classes ); ?>">
		<?php foreach ( $speakers as $speaker ) : ?>
			<?php setup_postdata( $speaker ); ?>

			<li class="wordcamp-block-post-list-item wordcamp-speaker wordcamp-speaker-<?php echo sanitize_html_class( $speaker->post_name ); ?> wordcamp-clearfix">
				<h3 class="wordcamp-item-title wordcamp-speaker-title">
					<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>">
						<?php echo wp_kses_post( get_the_title( $speaker ) ); ?>
					</a>
				</h3>

				<?php if ( true === $attributes['show_avatars'] ) : ?>
					<div class="wordcamp-speaker-avatar-container align-<?php echo esc_attr( $attributes['avatar_align'] ); ?>">
						<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>" class="wordcamp-speaker-avatar-link">
							<?php echo get_avatar(
								$speaker->_wcb_speaker_email,
								$attributes['avatar_size'],
								'',
								sprintf( __( 'Avatar of %s', 'wordcamporg'), get_the_title( $speaker ) ),
								[
									'class'         => 'wordcamp-speaker-avatar',
									'force_display' => true,
								]
							); ?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( 'none' !== $attributes['content'] ) : ?>
					<div class="wordcamp-item-content wordcamp-speaker-content-<?php echo esc_attr( $attributes['content'] ); ?>">
						<?php if ( 'full' === $attributes['content'] ) : ?>
							<?php echo wp_kses_post( wpautop( get_all_the_content( $speaker ) ) ); ?>
						<?php elseif ( 'excerpt' === $attributes['content'] ) : ?>
							<?php echo wp_kses_post( wpautop( apply_filters( 'the_excerpt', get_the_excerpt() ) ) ); ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( true === $attributes['show_session'] && ! empty( $sessions[ $speaker->ID ] ) ) : ?>
					<div class="wordcamp-item-meta wordcamp-speaker-sessions">
						<h4 class="wordcamp-speaker-sessions-heading">
							<?php echo esc_html( _n( 'Session', 'Sessions', count( $sessions[ $speaker->ID ] ), 'wordcamporg' ) ); ?>
						</h4>

						<ul class="wordcamp-speaker-sessions-list">
							<?php foreach ( $sessions[ $speaker->ID ] as $session ) : ?>
								<?php $tracks = get_the_terms( $session, 'wcb_track' ); ?>
								<li class="wordcamp-speaker-session-content">
									<a class="wordcamp-speaker-session-link" href="<?php echo esc_url( get_permalink( $session ) ); ?>">
										<?php echo wp_kses_post( get_the_title( $session ) ); ?>
									</a>

									<span class="wordcamp-speaker-session-info">
										<?php if ( ! is_wp_error( $tracks ) && ! empty( $tracks ) ) : ?>
											<?php
												printf(
													/* translators: 1: A date; 2: A time; 3: A location; */
													esc_html__( '%1$s at %2$s in %3$s', 'wordcamporg' ),
													esc_html( date_i18n( get_option( 'date_format' ), $session->_wcpt_session_time ) ),
													esc_html( date_i18n( get_option( 'time_format' ), $session->_wcpt_session_time ) ),
													esc_html( $tracks[0]->name )
												);
											?>

										<?php else : ?>
											<?php
												printf(
													/* translators: 1: A date; 2: A time; */
													esc_html__( '%1$s at %2$s', 'wordcamporg' ),
													esc_html( date_i18n( get_option( 'date_format' ), $session->_wcpt_session_time ) ),
													esc_html( date_i18n( get_option( 'time_format' ), $session->_wcpt_session_time ) )
												);
											?>
										<?php endif; ?>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( 'full' === $attributes['content'] ) : ?>
					<p class="wordcamp-item-permalink">
						<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>">
							<?php esc_html_e( 'Visit speaker page', 'wordcamporg' ); ?>
						</a>
					</p>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
		<?php wp_reset_postdata(); ?>
	</ul>
<?php endif; ?>

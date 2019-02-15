<?php
namespace WordCamp\Blocks\Speakers;
defined( 'WPINC' ) || die();

/** @var array  $attributes */
/** @var array  $speakers */
/** @var array  $sessions */
/** @var string $container_classes */

?>

<?php if ( ! empty( $speakers ) ) : ?>
	<ul class="<?php echo esc_attr( $container_classes ); ?>">
		<?php foreach ( $speakers as $speaker ) : setup_postdata( $speaker ); // phpcs:ignore Squiz.ControlStructures.ControlSignature ?>
			<li class="wordcamp-speaker wordcamp-speaker-<?php echo sanitize_html_class( $speaker->post_name ); ?> wordcamp-clearfix">
				<h3 class="wordcamp-speaker-name-heading">
					<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>">
						<?php echo get_the_title( $speaker ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
					</a>
				</h3>

				<?php if ( true === $attributes['show_avatars'] ) : ?>
					<div class="wordcamp-speaker-avatar-container align-<?php echo esc_attr( $attributes['avatar_align'] ); ?>">
						<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>" class="wordcamp-speaker-avatar-link">
							<?php
							echo get_avatar(
								$speaker->_wcb_speaker_email,
								$attributes['avatar_size'],
								'',
								sprintf( __( 'Avatar of %s', 'wordcamporg'), get_the_title( $speaker ) ),
								[
									'class'         => 'wordcamp-speaker-avatar',
									'force_display' => true,
								]
							);
							?>
						</a>
					</div>
				<?php endif; ?>

				<?php if ( 'none' !== $attributes['content'] ) : ?>
					<div class="wordcamp-speaker-content wordcamp-speaker-content-<?php echo esc_attr( $attributes['content'] ); ?>">
						<?php if ( 'full' === $attributes['content'] ) : ?>
							<?php echo wpautop( get_all_the_content( $speaker ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
							<p class="wordcamp-speaker-permalink">
								<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>">
									<?php esc_html_e( 'Visit speaker page', 'wordcamporg' ); ?>
								</a>
							</p>
						<?php elseif ( 'excerpt' === $attributes['content'] ) : ?>
							<?php wpautop( the_excerpt() ); ?>
							<?php if ( true === $attributes['excerpt_more'] ) : ?>
								<p class="wordcamp-speaker-permalink">
									<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>" class="wordcamp-speaker-permalink">
										<?php esc_html_e( 'Read more', 'wordcamporg' ); ?>
									</a>
								</p>
							<?php endif; ?>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( true === $attributes['show_session'] && ! empty( $sessions[ $speaker->ID ] ) ) : ?>
					<h4 class="wordcamp-speaker-session-heading">
						<?php echo esc_html( _n( 'Session', 'Sessions', count( $sessions[ $speaker->ID ] ), 'wordcamporg' ) ); ?>
					</h4>

					<ul class="wordcamp-speaker-session-list">
						<?php foreach ( $sessions[ $speaker->ID ] as $session ) :
							$tracks = get_the_terms( $session, 'wcb_track' );
							?>
							<li class="wordcamp-speaker-session-content">
								<a class="wordcamp-speaker-session-link" href="<?php echo esc_url( get_permalink( $session ) ); ?>">
									<?php echo get_the_title( $session ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
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
				<?php endif; ?>
			</li>
		<?php endforeach; wp_reset_postdata(); // phpcs:ignore Generic.Formatting.DisallowMultipleStatements,Squiz.PHP.EmbeddedPhp ?>
	</ul>
<?php endif; ?>

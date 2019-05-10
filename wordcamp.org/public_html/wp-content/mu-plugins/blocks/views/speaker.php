<?php
namespace WordCamp\Blocks\Speakers;

use WP_Post;
use function WordCamp\Blocks\Components\{ render_item_title, render_item_content, render_item_permalink };
use function WordCamp\Blocks\Utilities\{ get_all_the_content };

defined( 'WPINC' ) || die();

/** @var array   $attributes */
/** @var array   $sessions */
/** @var WP_Post $speaker */

setup_postdata( $speaker ); // This is necessary for generating an excerpt from content if the excerpt field is empty.
?>

<div class="wordcamp-speaker wordcamp-speaker-<?php echo esc_attr( $speaker->post_name ); ?>">
	<?php echo wp_kses_post(
		render_item_title(
			get_the_title( $speaker ),
			get_permalink( $speaker ),
			3,
			[ 'wordcamp-speaker-title' ]
		)
	); ?>

	<?php if ( true === $attributes['show_avatars'] ) : ?>
		<div class="wordcamp-image-container wordcamp-avatar-container align-<?php echo esc_attr( $attributes['avatar_align'] ); ?>">
			<a href="<?php echo esc_url( get_permalink( $speaker ) ); ?>" class="wordcamp-image-link wordcamp-avatar-link">
				<?php echo get_avatar(
					$speaker->_wcb_speaker_email,
					$attributes['avatar_size'],
					'',
					sprintf( __( 'Avatar of %s', 'wordcamporg'), get_the_title( $speaker ) ),
					[ 'force_display' => true ]
				); ?>
			</a>
		</div>
	<?php endif; ?>

	<?php if ( 'none' !== $attributes['content'] ) : ?>
		<?php echo wp_kses_post(
			render_item_content(
				'excerpt' === $attributes['content']
					? apply_filters( 'the_excerpt', get_the_excerpt( $speaker ) )
					: get_all_the_content( $speaker ),
				[ 'wordcamp-speaker-content-' . $attributes['content'] ]
			)
		); ?>
	<?php endif; ?>

	<?php if ( true === $attributes['show_session'] && ! empty( $sessions[ $speaker->ID ] ) ) : ?>
		<div class="wordcamp-item-meta wordcamp-speaker-sessions">
			<h4 class="wordcamp-speaker-sessions-heading">
				<?php echo esc_html( _n( 'Session', 'Sessions', count( $sessions[ $speaker->ID ] ), 'wordcamporg' ) ); ?>
			</h4>

			<ul class="wordcamp-speaker-sessions-list">
				<?php foreach ( $sessions[ $speaker->ID ] as $session ) : ?>
					<?php $tracks = get_the_terms( $session, 'wcb_track' ); ?>
					<li class="wordcamp-speaker-sessions-list-item">
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
		<?php echo wp_kses_post(
			render_item_permalink(
				get_permalink( $speaker ),
				__( 'Visit speaker page', 'wordcamporg' ),
				[ 'wordcamp-speaker-permalink' ]
			)
		); ?>
	<?php endif; ?>
</div>

<?php
wp_reset_postdata();

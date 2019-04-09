<?php

namespace WordCamp\Blocks\Sessions;
use WP_Post;
use function WordCamp\Blocks\Shared\{ get_all_the_content, array_to_human_readable_list };
use function WordCamp\Blocks\Shared\Components\{ render_featured_image };

defined( 'WPINC' ) || die();

/** @var array   $attributes */
/** @var WP_Post $session */
/** @var array   $speakers */
/** @var string  $container_classes */

setup_postdata( $session );

?>

<div class="wordcamp-block-post-list-item wordcamp-session wordcamp-session-<?php echo sanitize_html_class( $session->post_name ); ?> wordcamp-clearfix">
	<h3 class="wordcamp-item-title wordcamp-session-title">
		<a href="<?php echo esc_url( get_permalink( $session ) ); ?>">
			<?php echo wp_kses_post( get_the_title( $session ) ); ?>
		</a>
	</h3>

	<?php if ( true === $attributes['show_speaker'] && ! empty( $speakers[ $session->ID ] ) ) :
		$speaker_linked_names = array_map(
			function( $speaker ) {
				return sprintf(
					'<a href="%s">%s</a>',
					get_permalink( $speaker ),
					get_the_title( $speaker )
				);
			},
			$speakers[ $session->ID ]
		);
		?>

		<div class="wordcamp-item-meta wordcamp-session-speakers">
			<?php
			printf(
				/* translators: %s is a list of names. */
				wp_kses_post( __( 'Presented by %s', 'wordcamporg' ) ),
				wp_kses_post( array_to_human_readable_list( $speaker_linked_names ) )
			);
			?>
		</div>
	<?php endif; ?>

	<?php if ( true === $attributes['show_images'] ) : ?>
		<?php
			echo wp_kses_post( render_featured_image(
				array( 'wordcamp-session-image-container', 'align-' . esc_attr( $attributes['image_align'] ) ),
				$session,
				$attributes['featured_image_width'],
				get_permalink( $session )
			) );
		?>
	<?php endif; ?>

	<?php if ( 'none' !== $attributes['content'] ) : ?>
		<div class="wordcamp-item-content wordcamp-session-content-<?php echo esc_attr( $attributes['content'] ); ?>">
			<?php if ( 'full' === $attributes['content'] ) : ?>
				<?php echo wp_kses_post( wpautop( get_all_the_content( $session ) ) ); ?>
				<p class="wordcamp-item-permalink">
					<a href="<?php echo esc_url( get_permalink( $session ) ); ?>">
						<?php esc_html_e( 'Visit session page', 'wordcamporg' ); ?>
					</a>
				</p>

			<?php elseif ( 'excerpt' === $attributes['content'] ) : ?>
				<?php wp_kses_post( wpautop( the_excerpt() ) ); ?>
				<?php if ( true === $attributes['excerpt_more'] ) : ?>
					<p class="wordcamp-item-permalink">
						<a href="<?php echo esc_url( get_permalink( $session ) ); ?>" class="wordcamp-session-permalink">
							<?php esc_html_e( 'Read more', 'wordcamporg' ); ?>
						</a>
					</p>
				<?php endif; ?>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $attributes['show_meta'] || $attributes['show_category'] ) : ?>
		<div class="wordcamp-item-meta wordcamp-session-details">
			<?php if ( $attributes['show_meta'] ) : ?>
				<?php $tracks = get_the_terms( $session, 'wcb_track' ); ?>

				<div class="wordcamp-session-time-location">
					<?php if ( ! is_wp_error( $tracks ) && ! empty( $tracks ) ) :
						printf(
							/* translators: 1: A date; 2: A time; 3: A location; */
							esc_html__( '%1$s at %2$s in %3$s', 'wordcamporg' ),
							esc_html( date_i18n( get_option( 'date_format' ), $session->_wcpt_session_time ) ),
							esc_html( date_i18n( get_option( 'time_format' ), $session->_wcpt_session_time ) ),
							sprintf(
								'<span class="wordcamp-session-track wordcamp-session-track-%s">%s</span>',
								esc_attr( $tracks[0]->slug ),
								esc_html( $tracks[0]->name )
							)
						);

					else :
						printf(
							/* translators: 1: A date; 2: A time; */
							esc_html__( '%1$s at %2$s', 'wordcamporg' ),
							esc_html( date_i18n( get_option( 'date_format' ), $session->_wcpt_session_time ) ),
							esc_html( date_i18n( get_option( 'time_format' ), $session->_wcpt_session_time ) )
						);
					endif; ?>
				</div>
			<?php endif; ?>

			<?php if ( $attributes['show_category'] && has_term( null, 'wcb_session_category', $session ) ) :
				$categories = array_map(
					function( $category ) {
						return sprintf(
							'<span class="wordcamp-session-category wordcamp-session-category-%s">%s</span>',
							esc_attr( $category->slug ),
							esc_html( $category->name )
						);
					},
					get_the_terms( $session, 'wcb_session_category' )
				);
				?>

				<div class="wordcamp-session-categories">
					<?php
					/* translators: used between list items, there is a space after the comma */
					echo wp_kses_post( implode( __( ', ', 'wordcamporg' ), $categories ) );
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

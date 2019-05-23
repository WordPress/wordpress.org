<?php
namespace WordCamp\Blocks\Sessions;

use WP_Post;
use function WordCamp\Blocks\Components\{ render_featured_image, render_item_title, render_item_content, render_item_permalink };
use function WordCamp\Blocks\Utilities\{ get_all_the_content, array_to_human_readable_list };

defined( 'WPINC' ) || die();

/** @var array   $attributes */
/** @var array   $speakers */
/** @var WP_Post $session */

setup_postdata( $session );
?>

<div class="wordcamp-sessions__post has-slug-<?php echo sanitize_html_class( $session->post_name ); ?>">
	<?php echo wp_kses_post(
		render_item_title(
			get_the_title( $session ),
			get_permalink( $session ),
			3,
			[ 'wordcamp-sessions__title' ]
		)
	); ?>

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

		<div class="wordcamp__item-meta wordcamp-sessions__speakers">
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
		<?php echo wp_kses_post(
			render_featured_image(
				$session,
				$attributes['featured_image_width'],
				[ 'wordcamp-sessions__featured-image', 'align-' . esc_attr( $attributes['image_align'] ) ],
				get_permalink( $session )
			)
		); ?>
	<?php endif; ?>

	<?php if ( 'none' !== $attributes['content'] ) : ?>
		<?php echo wp_kses_post(
			render_item_content(
				'excerpt' === $attributes['content']
					? apply_filters( 'the_excerpt', get_the_excerpt( $session ) )
					: get_all_the_content( $session ),
				[ 'wordcamp-sessions__content-' . 'is-' . $attributes['content'] ]
			)
		); ?>
	<?php endif; ?>

	<?php if ( $attributes['show_meta'] || $attributes['show_category'] ) : ?>
		<div class="wordcamp__item-meta wordcamp-sessions__details">
			<?php if ( $attributes['show_meta'] ) : ?>
				<?php $tracks = get_the_terms( $session, 'wcb_track' ); ?>

				<div class="wordcamp-sessions__time-location">
					<?php if ( ! is_wp_error( $tracks ) && ! empty( $tracks ) ) :
						printf(
							/* translators: 1: A date; 2: A time; 3: A location; */
							esc_html__( '%1$s at %2$s in %3$s', 'wordcamporg' ),
							esc_html( date_i18n( get_option( 'date_format' ), $session->_wcpt_session_time ) ),
							esc_html( date_i18n( get_option( 'time_format' ), $session->_wcpt_session_time ) ),
							sprintf(
								'<span class="wordcamp-sessions__track has-slug-%s">%s</span>',
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
							'<span class="wordcamp-sessions__category has-slug-%s">%s</span>',
							esc_attr( $category->slug ),
							esc_html( $category->name )
						);
					},
					get_the_terms( $session, 'wcb_session_category' )
				);
				?>

				<div class="wordcamp-sessions__categories">
					<?php
					/* translators: used between list items, there is a space after the comma */
					echo wp_kses_post( implode( __( ', ', 'wordcamporg' ), $categories ) );
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( 'full' === $attributes['content'] ) : ?>
		<?php echo wp_kses_post(
			render_item_permalink(
				get_permalink( $session ),
				__( 'Visit session page', 'wordcamporg' ),
				[ 'wordcamp-sessions__permalink' ]
			)
		); ?>
	<?php endif; ?>
</div>

<?php
wp_reset_postdata();

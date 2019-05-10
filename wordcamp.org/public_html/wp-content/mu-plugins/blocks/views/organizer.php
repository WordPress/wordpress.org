<?php
namespace WordCamp\Blocks\Organizers;

use WP_Post;
use function WordCamp\Blocks\Components\{ render_item_title, render_item_content };
use function WordCamp\Blocks\Utilities\{ get_all_the_content };

defined( 'WPINC' ) || die();

/** @var array   $attributes */
/** @var WP_Post $organizer */

// Note that organizer posts are not 'public', so there are no permalinks.

setup_postdata( $organizer ); // This is necessary for generating an excerpt from content if the excerpt field is empty.
?>

<div class="wordcamp-organizer wordcamp-organizer-<?php echo esc_attr( $organizer->post_name ); ?>">
	<?php echo wp_kses_post(
		render_item_title(
			get_the_title( $organizer ),
			'',
			3,
			[ 'wordcamp-organizer-title' ]
		)
	); ?>

	<?php if ( true === $attributes['show_avatars'] ) : ?>
		<div class="wordcamp-image-container wordcamp-avatar-container align-<?php echo esc_attr( $attributes['avatar_align'] ); ?>">
			<?php echo get_avatar(
				$organizer->_wcpt_user_id,
				$attributes['avatar_size'],
				'',
				sprintf( __( 'Avatar of %s', 'wordcamporg'), get_the_title( $organizer ) ),
				[ 'force_display' => true ]
			); ?>
		</div>
	<?php endif; ?>

	<?php if ( 'none' !== $attributes['content'] ) : ?>
		<?php echo wp_kses_post(
			render_item_content(
				'excerpt' === $attributes['content']
					? apply_filters( 'the_excerpt', get_the_excerpt( $organizer ) )
					: get_all_the_content( $organizer ),
				[ 'wordcamp-organizer-content-' . $attributes['content'] ]
			)
		); ?>
	<?php endif; ?>
</div>

<?php
wp_reset_postdata();

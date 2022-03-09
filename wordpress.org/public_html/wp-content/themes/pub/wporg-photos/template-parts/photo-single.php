<?php
/**
 * Template part for displaying photos.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;
use WordPressdotorg\Photo_Directory\Template_Tags;

?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php printf(
			'<a href="%s" class="photo-author">%s</a>',
			esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
			get_avatar( get_the_author_meta( 'ID' ), 32 ) . get_the_author_meta( 'display_name' )
		);
		?>
		<div class="photos-download">
			<button type="button" class="download-title download-button button button-large" aria-expanded="false" aria-controls="downloads-dropdown">
				<?php _e( 'Download', 'wporg-photos' ); ?>
			</button>
			<?php
				$photo_sizes = [
					'medium_large' => [ 'label' => __( 'Small %s', 'wporg-photos' ) ],
					'1536x1536'    => [ 'label' => __( 'Medium %s', 'wporg-photos' ) ],
					'2048x2048'    => [ 'label' => __( 'Large %s', 'wporg-photos' ) ],
					'full'         => [ 'label' => __( 'Original Size %s', 'wporg-photos' ) ],
				];
				foreach ( array_keys( $photo_sizes ) as $size ) {
					$src = wp_get_attachment_image_src( get_post_thumbnail_id(), $size );
					$photo_sizes[ $size ] = array_merge( $photo_sizes[ $size ], [
						'width'  => $src[ 1 ],
						'height' => $src[ 2 ],
						'url'    => $src[ 0 ],
					] );
				}
			?>
			<ul class="download-menu" id="downloads-dropdown">
				<?php
					foreach ( $photo_sizes as $size => $info ) {
						printf(
							'<li><a href="%s" rel="nofollow" download target="_blank">%s</a></li>',
							$info[ 'url' ],
							sprintf( $info[ 'label' ], sprintf( '<span class="photo-dimensions">(%s&times;%s)</span>', $info['width'], $info['height'] ) )
						);
					}
				?>
			</ul>
		</div>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<figure>
			<a href="<?php echo wp_get_attachment_url( get_post_thumbnail_id() ); ?>" aria-label="<?php esc_attr_e( 'View larger photo', 'wporg-photos' ); ?>">
				<img class="single-photo" src="<?php echo get_the_post_thumbnail_url( get_the_ID(), 'medium'); ?>" srcset="<?php echo esc_attr( wp_get_attachment_image_srcset( get_post_thumbnail_id() ) ); ?>" alt="">
			</a>

			<?php
			$caption = get_the_content();
			if ( $caption ) {
			?>

			<figcaption class="wp-caption-text"><?php echo wp_kses_post( $caption ); ?></figcaption>

			<?php } ?>
		</figure>
	</div><!-- .entry-content -->

	<footer class="entry-footer">
		<div class="photo-meta">
			<div class="column">
				<?php Template_Tags\show_colors(); ?>
				<?php Template_Tags\show_categories(); ?>
				<?php Template_Tags\show_tags(); ?>
				<?php Template_Tags\show_moderation_flags(); ?>

				<?php Template_Tags\show_dimensions(); ?>
				<?php Template_Tags\show_orientation(); ?>
				<?php Template_Tags\show_publish_date(); ?>
			</div>
			<div class="column">
				<?php Template_Tags\show_exif(); ?>
			</div>
		</div>

		<?php
		edit_post_link(
			sprintf(
				/* translators: %s: Name of current post */
				esc_html__( 'Edit %s', 'wporg-photos' ),
				the_title( '<span class="screen-reader-text">"', '"</span>', false )
			),
			'<span class="edit-link">',
			'</span>'
		);
		?>
	</footer><!-- .entry-footer -->

</article><!-- #post-## -->

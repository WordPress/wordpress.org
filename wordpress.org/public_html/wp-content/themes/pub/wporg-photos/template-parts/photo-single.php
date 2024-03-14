<?php
/**
 * Template part for displaying photos.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Photo_Directory\Theme
 */

namespace WordPressdotorg\Photo_Directory\Theme;
use WordPressdotorg\Photo_Directory\Random;
use WordPressdotorg\Photo_Directory\Template_Tags;

$photo_id = get_post_thumbnail_id();
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<?php if ( Random::was_photo_random() ) : ?>
			<div class="randomly-chosen-photo"><?php
				printf(
					/* translators: %s: Link to load another random photo. */
					__( 'This photo was randomly chosen. %s', 'wporg-photos' ),
					sprintf(
						'<a href="%s">' . __( 'Load another random photo?', 'wporg-photos' ) . '</a>',
						home_url( '/' . Random::PATH . '/' )
					)
				);
			?></div>
		<?php endif; ?>
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

				$photo_meta = wp_get_attachment_metadata( $photo_id );
				foreach ( array_keys( $photo_sizes ) as $size ) {
					$src = wp_get_attachment_image_src( $photo_id, $size );
					if ( 'full' === $size ) {
						$filesize = $photo_meta['filesize'] ?? '';
					} else {
						$filesize = $photo_meta['sizes'][ $size ]['filesize'] ?? '';
					}
					$photo_sizes[ $size ] = array_merge( $photo_sizes[ $size ], [
						'filesize' => size_format( $filesize ),
						'width'    => $src[ 1 ],
						'height'   => $src[ 2 ],
						'url'      => $src[ 0 ],
					] );
				}
			?>
			<ul class="download-menu" id="downloads-dropdown">
				<?php
					foreach ( $photo_sizes as $size => $info ) {
						printf(
							'<li><a href="%s" rel="nofollow" download target="_blank">%s<span class="photo-filesize">%s</span></a></li>',
							esc_url( $info[ 'url' ] ),
							sprintf( $info[ 'label' ], sprintf( '<span class="photo-dimensions">(%s&times;%s)</span>', $info['width'], $info['height'] ) ),
							$info['filesize']
						);
					}
				?>
			</ul>
		</div>
	</header><!-- .entry-header -->

	<div class="entry-content">
		<?php $alt_text = get_the_content(); ?>

		<a href="<?php echo wp_get_attachment_url( $photo_id ); ?>">
			<?php
			printf(
				'<img class="single-photo" src="%s" srcset="%s" alt="%s">',
				esc_url( get_the_post_thumbnail_url( get_the_ID(), 'medium') ),
				esc_attr( wp_get_attachment_image_srcset( $photo_id ) ),
				sprintf(
					/* translators: %s: The alternative text for the photo. */
					'View larger photo: %s',
					esc_attr( $alt_text )
				)
			);
			?>

		</a>

		<?php if ( $alt_text ) : ?>

		<p class="photo-alt-text">
			<span><?php _e( 'Alternative Text: ', 'wporg-photos' ); ?></span><?php echo wp_kses_post( $alt_text ); ?>

		</p>

		<?php endif; ?>

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

		<div class="attribution">
			<h3><?php _e( 'Attribution', 'wporg-photos' ); ?></h3>
			<div class="attribution-label">
				<?php _e( "Photo attribution is not necessary, but appreciated. If you'd like to give credit to the photographer, feel free to use this text:", 'wporg-photos' ); ?>
			</div>
			<div class="attribution-text">
				<div class="tabs">
					<button class="active"><?php _e( 'Rich Text', 'wporg-photos' ); ?></button>
					<button><?php _e( 'HTML', 'wporg-photos' ); ?></button>
					<button><?php _e( 'Plain text', 'wporg-photos' ); ?></button>
				</div>
				<div class="tab-content">
				<div class="tab tab-rich-text active">
				<?php printf(
					/* translators: 1: URL to CC0 license, 2: URL to photo's page, 3: URL to contributor's profile, 4: Contributor's display name, 5: URL to Photo Directory. */
					__( '<a href="%1$s">CC0</a> licensed <a href="%2$s">photo</a> by <a href="%3$s">%4$s</a> from the <a href="%5$s">WordPress Photo Directory</a>.', 'wporg-photos' ),
					'https://creativecommons.org/share-your-work/public-domain/cc0/',
					esc_url( get_the_permalink() ),
					esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
					esc_html( get_the_author_meta( 'display_name' ) ),
					esc_url( home_url( '/' ) )
				); ?>
				</div>
				<div class="tab tab-html">
					<?php printf(
						/* translators: 1: URL to CC0 license, 2: URL to photo's page, 3: URL to contributor's profile, 4: Contributor's display name, 5: URL to Photo Directory. */
						htmlentities( '<p class="attribution">' . __( '<a href="%1$s">CC0</a> licensed <a href="%2$s">photo</a> by <a href="%3$s">%4$s</a> from the <a href="%5$s">WordPress Photo Directory</a>.', 'wporg-photos' ) . '</p>' ),
						'https://creativecommons.org/share-your-work/public-domain/cc0/',
						esc_url( get_the_permalink() ),
						esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ),
						esc_html( get_the_author_meta( 'display_name' ) ),
						esc_url( home_url( '/' ) )
					); ?>
				</div>
				<div class="tab tab-plain-text">
				<?php printf(
					/* translators: 1: Contributor's display name, 4: URL to photo's page. */
					__( 'CC0 licensed photo by %1$s from the WordPress Photo Directory: %2$s', 'wporg-photos' ),
					esc_html( get_the_author_meta( 'display_name' ) ),
					esc_url( get_the_permalink() )
				); ?>
				</div>
				<button class="attribution-copy"><?php _e( 'Copy to clipboard', 'wporg-photos' ); ?></button>
				</div>
			</div>
		</div>

		<div class="photo-license">
			<h3><?php _e( 'License / Usage', 'wporg-photos' ); ?></h3>
			<p><?php printf(
				/* translators: %s: URL to CC0 license. */
				__( 'Photo contributors submit their original content under the <a href="%s">CC0 license</a>. This license allows everyone to use the photos anywhere, for any purpose, without the need for permission, attribution, or payment. However, you cannot claim ownership or authorship of any photos in the WordPress Photo Directory, out of respect for the original photographers. Submissions are moderated by a team of volunteers who recommend prior to use that you verify that the work is actually under the CC0 license and abides by any applicable local laws.', 'wporg-photos' ),
				'https://creativecommons.org/share-your-work/public-domain/cc0/'
			); ?></p>
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

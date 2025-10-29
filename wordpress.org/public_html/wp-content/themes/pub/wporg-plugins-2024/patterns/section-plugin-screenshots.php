<?php
/**
 * Title: Screenshots Section
 * Slug: wporg-plugins-2024/section-screenshots
 * Inserter: no
 *
 * This is dynamically included by the sectioning code in `split_post_content_into_pages`.
 */

namespace WordPressdotorg\Plugin_Directory\Theme\Pattern\Screenshot;

use WordPressdotorg\Plugin_Directory\Template;

$screenshots = Template::get_screenshots();
?>
<!-- wp:gallery {"linkTo":"lightbox","sizeSlug":"full","align":"wide"} -->
<figure class="wp-block-gallery alignwide has-nested-images columns-default is-cropped">

<?php foreach ( $screenshots as $image ) : ?>
	<!-- wp:image {"lightbox":{"enabled":true},"id":null,"sizeSlug":"full","linkDestination":"none"} -->
	<figure class="wp-block-image size-full">
		<?php
			printf(
				'<img class="wp-image-55474" src="%1$s" alt="" />',
				esc_url( $image['src'] )
			);
		?>
		<?php if ( ! empty( $image['caption'] ) ) : ?>
			<figcaption class="wp-element-caption"><?php echo esc_html( $image['caption'] ); ?></figcaption>
		<?php endif; ?>
	</figure>
	<!-- /wp:image -->
<?php endforeach; ?>

</figure>
<!-- /wp:gallery -->

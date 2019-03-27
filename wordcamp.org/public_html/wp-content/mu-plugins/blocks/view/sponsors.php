<?php
namespace WordCamp\Blocks\Sponsors;
defined( 'WPINC' ) || die();

use function WordCamp\Blocks\Shared\{ get_all_the_content };
use function WordCamp\Blocks\Shared\Components\{ render_featured_image };

/** @var array     $attributes */
/** @var \WP_Post  $sponsor */
/** @var array     $sponsor_featured_urls */

setup_postdata( $sponsor );

?>
<div class="wordcamp-sponsor-details wordcamp-sponsor-details-<?php echo sanitize_html_class( $sponsor->post_name ); ?> ">

	<?php if ( $attributes['show_name'] ) { ?>
		<h3 class="wordcamp-sponsor-title wordcamp-item-title">
			<a href="<?php esc_attr_e( get_permalink( $sponsor->ID ) ) ?>"><?php echo get_the_title( $sponsor ) ?></a>
		</h3>
	<?php } ?>

	<?php if ( $attributes['show_logo'] ) { ?>
		<?php echo render_featured_image(
			array( 'wordcamp-sponsor-featured-image' ),
			$sponsor,
			$attributes['featured_image_height'],
			$attributes['featured_image_width']
		); ?>
	<?php } ?>

	<?php if ( $attributes['show_desc'] ) { ?>
		<?php echo wpautop( get_all_the_content( $sponsor ) ) ?>
	<?php } ?>

</div>

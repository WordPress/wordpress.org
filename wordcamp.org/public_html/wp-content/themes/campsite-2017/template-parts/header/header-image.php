<?php
/**
 * Displays header media
 *
 * @package WordPress
 * @subpackage CampSite_2017
 * @since 1.0
 * @version 1.0
 */

namespace WordCamp\CampSite_2017;

?>

<div class="custom-header">
	<div class="custom-header-media">
		<?php the_custom_header_markup(); ?>
	</div>

	<?php get_template_part( 'template-parts/header/site', 'branding' ); ?>
</div>

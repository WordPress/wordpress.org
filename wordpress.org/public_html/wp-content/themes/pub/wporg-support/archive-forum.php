<?php

/**
 * Template Name: bbPress - Support (Index)
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>


	<main id="main" class="site-main" role="main">

		<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>

	</main>

	<?php echo do_blocks( '<!-- wp:pattern {"slug":"wporg-support/forums-homepage-footer"} /-->' ); ?>

<?php
get_footer();

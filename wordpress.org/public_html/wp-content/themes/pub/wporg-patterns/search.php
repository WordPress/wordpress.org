<?php
/**
 * Template Name: Search
 *
 * The main search file.
 *
 * @package WordPressdotorg\Pattern_Directory\Theme
 */

namespace WordPressdotorg\Pattern_Directory\Theme;

get_header();
?> 

<div id="patterns-search__container">
	<div class="pattern-grid">
		<?php
		if ( have_posts() ) :
			/* Start the Loop */
			while ( have_posts() ) :
				the_post();
				get_template_part( 'template-parts/content', 'grid' );
			endwhile;
		endif;
		?>
	</div>
</div>


<?php
get_footer();

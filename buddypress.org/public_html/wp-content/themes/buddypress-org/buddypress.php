<?php

/**
 * BuddyPress Wrapper
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

	<div id="buddypress-community">
		<?php while ( have_posts() ) :
			the_post();
			the_content();
		endwhile; ?>
	</div>

<?php get_sidebar();
get_footer();
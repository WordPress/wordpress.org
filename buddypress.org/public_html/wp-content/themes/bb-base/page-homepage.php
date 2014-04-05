<?php
/*
Template Name: Homepage
*/
get_header(); ?>

<h3 id="post-home"><?php _e( 'Recent Topics', 'bbporg' ); ?></h3>

<?php if ( function_exists( 'is_bbpress' )  ) : ?>
<div id="bbpress-forums">

	<?php if ( bbp_has_topics( array( 's' => '', 'posts_per_page' => 5, 'max_num_pages' => 1, 'paged' => 1, 'show_stickies' => false ) ) ) : ?>

		<?php bbp_get_template_part( 'loop',     'topics'    ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'feedback', 'no-topics' ); ?>

	<?php endif; ?>

</div>
<?php endif; ?>

<hr class="hidden" />

<?php

get_sidebar();
get_footer();

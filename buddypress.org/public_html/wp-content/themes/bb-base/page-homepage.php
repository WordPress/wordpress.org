<?php
/*
Template Name: Homepage
*/
get_header(); ?>

<h2 id="post-home"><?php _e( 'Recent Topics', 'bbporg' ); ?></h2>

<?php if ( function_exists( 'is_bbpress' )  ) : ?>
<div id="bbpress-forums">

	<?php bb_base_homepage_topics(); ?>

</div>
<?php endif; ?>

<hr class="hidden" />

<?php

get_sidebar();
get_footer();

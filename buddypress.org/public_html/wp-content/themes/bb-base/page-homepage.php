<?php
/*
Template Name: Homepage
*/
get_header(); ?>

<h3 id="post-home"><?php _e( 'Recent Topics', 'bbporg' ); ?></h3>

<?php if ( function_exists( 'is_bbpress' )  ) : ?>
<div id="bbpress-forums">

	<?php bb_base_homepage_topics(); ?>

</div>
<?php endif; ?>

<hr class="hidden" />

<?php

get_sidebar();
get_footer();

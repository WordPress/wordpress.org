<?php
/*
Template Name: Support Index
*/
get_header(); ?>

<h3 id="post-home"><?php _e( 'Support', 'bb-base' ); ?></h3>

<?php if ( bbp_get_paged() > 1 ) : ?>

	<?php bbp_get_template_part( 'content', 'archive-topic' ); ?>

<?php else : ?>

	<?php bb_base_support_topics(); ?>

<?php endif; ?>

<hr class="hidden" />

<?php

get_sidebar();
get_footer();

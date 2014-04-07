<?php get_header(); ?>

<h3 id="post-home"><?php _e( 'Support', 'bb-base' ); ?></h3>

<?php if ( 1 === bbp_get_paged() ) : // cached first page ?>

	<?php bb_base_support_topics(); ?>

<?php else : // all other pages not cached ?>

	<?php bbp_get_template_part( 'content', 'archive-topic' ); ?>

<?php endif; ?>

<hr class="hidden" />

<?php

get_sidebar();
get_footer();

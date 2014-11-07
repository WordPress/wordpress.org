<?php if ( bbp_is_forum_archive() || bbp_is_topic_archive() || bbp_is_search() ) : ?>

	<div class="bbp-search-form">

		<?php bbp_get_template_part( 'form', 'search' ); ?>

	</div>

<?php endif; ?>

<div id="bbpress-forums">

	<?php bbp_set_query_name( 'bbp_search' ); ?>

	<?php do_action( 'bbp_template_before_search' ); ?>

	<?php if ( bbp_has_search_results() ) : ?>

		 <?php bbp_get_template_part( 'pagination', 'search' ); ?>

		 <?php bbp_get_template_part( 'loop',       'search' ); ?>

		 <?php bbp_get_template_part( 'pagination', 'search' ); ?>

	<?php elseif ( bbp_get_search_terms() ) : ?>

		 <?php bbp_get_template_part( 'feedback',   'no-search' ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'feedback',   'search' ); ?>

	<?php endif; ?>

	<?php do_action( 'bbp_template_after_search_results' ); ?>

</div>


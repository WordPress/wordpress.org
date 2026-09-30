<?php do_action( 'bbp_template_before_search_results_loop' ); ?>

<ul id="bbp-search-results" class="forums bbp-search-results">

	<li class="bbp-body">

		<?php while ( bbp_search_results() ) : bbp_the_search_result(); ?>

			<?php if ( 'topic' === get_post_type() ) : ?>

				<?php bbp_get_template_part( 'content', 'single-topic-lead' ); ?>

			<?php else : ?>

				<?php bbp_get_template_part( 'loop', 'single-reply' ); ?>

			<?php endif; ?>

		<?php endwhile; ?>

	</li><!-- .bbp-body -->

</ul><!-- #bbp-search-results -->

<?php do_action( 'bbp_template_after_search_results_loop' ); ?>

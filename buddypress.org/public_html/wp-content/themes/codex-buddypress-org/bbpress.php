<?php get_header() ?>

	<div id="content">

		<div id="focus" class="focus-small">
			<h2>Codex Discussion Forums</h2>

			<p class="description">
				<?php echo wp_filter_post_kses( get_post_meta( get_the_ID(), 'page_excerpt', true ) ); ?>
			</p>
		</div>

		<div class="padder">

			<div class="page" id="blog-page">

				<?php if (have_posts()) : while (have_posts()) : the_post(); ?>

					<div class="post" id="post-<?php the_ID(); ?>">
						<div class="entry">
							<?php the_content(); ?>
						</div>
					</div>

				<?php endwhile; endif; ?>

			</div><!-- .page -->

		</div><!-- .padder -->

	</div><!-- #content -->

<?php

get_footer();
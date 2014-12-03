<div class="secondary-content">
	<ul>
		<li>
			<h3><a href="http://blog.wordpress.tv"><?php esc_html_e( 'From the Blog', 'wptv' ); ?></a></h3>
			<ul>
				<?php
					// Make it easier to contribute to this theme by not assuming multisite context.
					if ( function_exists( 'switch_to_blog' ) ) {
						switch_to_blog( 5310177 ); // blog.wordpress.tv
					}

					query_posts( 'posts_per_page=5' );

					while ( have_posts() ) :
						the_post();
				?>
					<li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
				<?php
					endwhile;

					if ( function_exists( 'restore_current_blog' ) ) {
						restore_current_blog();
					}
				?>
			</ul>
		</li>
		<li>
			<h3><?php esc_html_e( 'Resources', 'wptv' ); ?></h3>

			<ul>
				<?php wp_list_bookmarks( 'title_li=&categorize=0' ); ?>
			</ul>
		</li>
	<ul/>
</div><!-- .secondary_content -->
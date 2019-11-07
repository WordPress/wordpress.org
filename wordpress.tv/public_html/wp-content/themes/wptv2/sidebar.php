<div class="secondary-content">
	<ul>
		<li class="widget">
			<h3><?php esc_html_e( 'Resources', 'wptv' ); ?></h3>

			<ul>
				<?php wp_list_bookmarks( 'title_li=&categorize=0' ); ?>
			</ul>
		</li>
		<?php
			the_widget(
				'WP_Widget_Custom_HTML',
				[
					'content' => '<a rel="license" href="https://creativecommons.org/licenses/by-sa/4.0/"><img alt="Creative Commons License" style="border-width:0;" src="https://i.creativecommons.org/l/by-sa/4.0/88x31.png" /></a><br />This work is licensed under a <a rel="license" href="https://creativecommons.org/licenses/by-sa/4.0/">Creative Commons Attribution-ShareAlike 4.0 International License</a>.',
				],
				[
					'before_widget' => '<li class="widget %s">',
					'after_widget'  => '</li>',
				]
			);
		?>
	</ul>
</div><!-- .secondary_content -->

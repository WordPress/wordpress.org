<script id="tmpl-theme-single" type="text/template">
	<div class="theme-backdrop"></div>
	<div class="theme-wrap">
		<div class="theme-header">
			<button class="close dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Close overlay' ); ?></span></button>
			<button class="left dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show previous theme' ); ?></span></button>
			<button class="right dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show next theme' ); ?></span></button>
		</div>
		<div class="theme-about">
			<div class="theme-info hentry">
				<h3 class="theme-name entry-title">{{{ data.name }}}</h3>
				<span class="theme-version"><?php printf( __( 'Version: %s' ), '{{{ data.version }}}' ); ?></span>
				<h4 class="theme-author"><?php printf( __( 'By %s' ), '<span class="author">{{{ data.author }}}</span>' ); ?></h4>

				<p class="theme-description entry-summary">{{{ data.description }}}</p>

				<div class="rating rating-{{ Math.round( data.rating / 10 ) * 10 }}">
					<span class="one"></span>
					<span class="two"></span>
					<span class="three"></span>
					<span class="four"></span>
					<span class="five"></span>

					<p class="votes"><?php printf( __( 'Based on %s ratings.' ), '{{{ data.num_ratings }}}' ); ?></p>
				</div>

				<div class="theme-stats">
					<div><strong><?php _e( 'Last updated:' ); ?></strong> <span class="updated">{{ data.last_updated }}</span></div>
					<div><strong><?php _e( 'Downloads:' ); ?></strong> {{ data.downloaded }}</div>
					<div><a href="{{ data.homepage }}"><?php _e( 'Theme Homepage &raquo;' ); ?></a></div>
				</div>

				<# if ( data.tags ) { #>
				<p class="theme-tags">
					<span><?php _e( 'Tags:' ); ?></span>
					<# _.each( data.tags, function( tag ) { #>
						<a href="">{{{ tag }}}</a>
					<# }); #>
				</p>
				<# } #>
			</div><!-- .theme-info -->

			<div class="theme-screenshots">
				<# if ( data.screenshot_url ) { #>
				<div class="screenshot"><img src="{{ data.screenshot_url }}" alt=""/></div>
				<# } else { #>
				<div class="screenshot blank"></div>
				<# } #>
			</div><!-- .theme-screenshot -->

			<div class="theme-downloads">
				<h4><?php _e( 'Downloads Per Day' ); ?></h4>
				<div id="theme-download-stats-{{data.id}}" class="chart"></div>
			</div>

			<div class="theme-devs">
				<h4><?php _e( 'Developers', 'wporg-themes' ); ?></h4>
				<h5><?php _e( 'Subscribe', 'wporg-themes' ); ?></h5>
				<ul class="unmarked-list">
					<li>
						<a href="//themes.trac.wordpress.org/log/{{data.id}}?limit=100&mode=stop_on_copy&format=rss">
							<img src="//s.w.org/style/images/feedicon.png" style="vertical-align:text-top;" />
							<?php _e( 'Development Log', 'wporg' ); ?>
						</a>
					</li>
				</ul>

				<h5><?php _e( 'Browse the Code', 'wporg-themes' ); ?></h5>
				<ul class="unmarked-list">
					<li><a href='//themes.trac.wordpress.org/log/{{data.id}}/' rel='nofollow'><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
					<li><a href='//themes.svn.wordpress.org/{{data.id}}/' rel='nofollow'><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
					<li><a href='//themes.trac.wordpress.org/browser/{{data.id}}/' rel='nofollow'><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
				</ul>
			</div>
		</div>

		<div class="theme-actions">
			<a href="" class="button button-primary"><?php _e( 'Download' ); ?></a>
			<a href="{{{ data.preview_url }}}" class="button button-secondary"><?php _e( 'Preview' ); ?></a>
		</div>
	</div>
</script>

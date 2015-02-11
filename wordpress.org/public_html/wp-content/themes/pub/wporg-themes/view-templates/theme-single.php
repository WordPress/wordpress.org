<script id="tmpl-theme-single" type="text/template">
	<div class="theme-backdrop"></div>
	<div class="theme-wrap">
		<div class="theme-header">
			<button class="close dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Close overlay' ); ?></span></button>
			<button class="left dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show previous theme' ); ?></span></button>
			<button class="right dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show next theme' ); ?></span></button>
		</div>
		<div class="theme-about">
			<# if ( data.is_outdated ) { #>
			<div class="theme-notice notice notice-warning">
				<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
			</div><!-- .theme-notice -->
			<# } #>

				<div class="theme-screenshots">
					<# if ( data.screenshot_url ) { #>
						<div class="screenshot"><img src="{{ data.screenshot_url }}?w=732&strip=all" alt=""/></div>
					<# } else { #>
						<div class="screenshot blank"></div>
					<# } #>


					<div class="theme-actions">
						<a href="//downloads.wordpress.org/theme/{{ data.slug }}.{{ data.version }}.zip" class="button button-primary"><?php _e( 'Download' ); ?></a>
						<a href="{{{ data.preview_url }}}" class="button button-secondary"><?php _e( 'Preview' ); ?></a>
					</div>
				</div><!-- .theme-screenshot -->

				<div class="theme-info">
				<div class="theme hentry">
					<h3 class="theme-name entry-title">{{{ data.name }}}</h3>
					<span class="theme-version">
						<?php printf( __( 'Version: %s' ), sprintf( '<abbr title="%1$s">%2$s</abbr>', esc_attr( sprintf( __( 'Last updated: %s' ), '{{ new Date(data.last_updated).toLocaleDateString() }}' ) ), '{{{ data.version }}}' ) ); ?>
					</span>
					<h4 class="theme-author"><?php printf( __( 'By %s' ), '<a href="https://profiles.wordpress.org/{{ data.author }}"><span class="author">{{{ data.author }}}</span></a>' ); ?></h4>

					<p class="theme-description entry-summary">{{{ data.description }}}</p>

					<# if ( data.parent ) { #>
					<div class="theme-notice notice notice-info">
						<p class="parent"><?php printf( __( 'This is a child theme of %s.' ), sprintf( '<a href="/%1$s">%2$s</a>', '{{{ data.parent.slug }}}', '{{{ data.parent.name }}}' ) ); ?></p>
					</div>
					<# } #>
				</div><!-- .theme-info -->

				<div class="theme-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
					<meta itemprop="ratingValue" content="{{ (data.rating/20).toFixed(1) }}"/>
					<meta itemprop="ratingCount" content="{{ data.num_ratings }}"/>
					<h4><?php _e( 'Ratings', 'wporg-themes' ); ?></h4>
					<div class="star-holder">
						<div class="star-rating" style="width: {{ (data.rating).toFixed(1) }}%"><?php printf( __( '%d stars', 'wporg-themes' ), '{{ Math.round( data.rating ) }}' ); ?></div>
					</div>
					<span><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), '{{ (data.rating/20).toFixed(1) }}' ); ?></span>

					<# if ( data.ratings ) { #>
						<div class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=5" title="<?php printf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 5 ); ?>">
								<span class="counter-label"><?php printf( __( '%d stars', 'wporg-themes' ), 5 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 92 * data.ratings[5] / data.num_ratings }}px;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[5] }}</span>
							</a>
						</div>
						<div class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=4" title="<?php printf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 4 ); ?>">
								<span class="counter-label"><?php printf( __( '%d stars', 'wporg-themes' ), 4 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 92 * data.ratings[4] / data.num_ratings }}px;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[4] }}</span>
							</a>
						</div>
						<div class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=3" title="<?php printf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 3 ); ?>">
								<span class="counter-label"><?php printf( __( '%d stars', 'wporg-themes' ), 3 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 92 * data.ratings[3] / data.num_ratings }}px;"></span>
								</span>
							</a>
							<span class="counter-count">{{ data.ratings[3] }}</span>
						</div>
						<div class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=2" title="<?php printf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 2 ); ?>">
								<span class="counter-label"><?php printf( __( '%d stars', 'wporg-themes' ), 2 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 92 * data.ratings[2] / data.num_ratings }}px;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[2] }}</span>
							</a>
						</div>
						<div class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=1" title="<?php printf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 1 ); ?>">
								<span class="counter-label"><?php printf( __( '%d stars', 'wporg-themes' ), 1 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 92 * data.ratings[1] / data.num_ratings }}px;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[1] }}</span>
							</a>
						</div>
					<# } #>
				</div><!-- .theme-rating -->

					<div class="theme-devs">
						<h4><?php _e( 'Development', 'wporg-themes' ); ?></h4>
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
							<li><a href="//themes.trac.wordpress.org/log/{{data.id}}/" rel="nofollow"><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
							<li><a href="//themes.svn.wordpress.org/{{data.id}}/" rel="nofollow"><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
							<li><a href="//themes.trac.wordpress.org/browser/{{data.id}}/" rel="nofollow"><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
						</ul>
					</div><!-- .theme-devs -->

				<div class="theme-downloads">
					<h4><?php _e( 'Downloads Per Day' ); ?></h4>
					<div id="theme-download-stats-{{data.id}}" class="chart"></div>
					<p class="total-downloads"><?php printf( __( 'Total downloads: %s' ), '<strong>{{ new Number(data.downloaded).toLocaleString() }}</strong>' ); ?></p>
				</div><!-- .theme-downloads -->

				<# if ( data.tags ) { #>
					<div class="theme-tags">
						<h4><?php _e( 'Tags:' ); ?></h4>
						{{{ data.tags }}}
					</div><!-- .theme-tags -->
				<# } #>
			</div>
		</div>
	</div>
</script>

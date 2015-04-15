<script id="tmpl-theme-single" type="text/template">
	<div class="theme-navigation">
		<button class="close"><?php _e( 'Return to Themes List', 'wporg-themes' ); ?></button>
		<div class="navigation post-navigation">
			<button class="left dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show previous theme', 'wporg-themes' ); ?></span></button>
			<button class="right dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show next theme', 'wporg-themes' ); ?></span></button>
		</div>
	</div>
	<div class="theme-wrap">
		<div class="theme-about hentry" itemscope itemtype="http://schema.org/CreativeWork">
			<# if ( data.is_outdated ) { #>
			<div class="theme-notice notice notice-warning">
				<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
			</div><!-- .theme-notice -->
			<# } #>

			<div>
				<h3 class="theme-name entry-title" itemprop="name">{{{ data.name }}}</h3>
				<h4 class="theme-author"><?php printf( __( 'by %s', 'wporg-themes' ), '<a href="https://wordpress.org/themes/author/{{ data.author.user_nicename }}/"><span class="author" itemprop="author">{{{ data.author.display_name }}}</span></a>' ); ?></h4>
			</div>

			<div class="theme-head">
				<div class="theme-actions clear">
					<a href="{{{ data.preview_url }}}" class="button button-secondary alignleft"><?php _e( 'Preview', 'wporg-themes' ); ?></a>
					<a href="//downloads.wordpress.org/theme/{{ data.slug }}.{{ data.version }}.zip" class="button button-primary alignright"><?php _e( 'Download', 'wporg-themes' ); ?></a>
				</div>

				<# if ( data.parent ) { #>
				<div class="theme-notice notice notice-info">
					<p class="parent"><?php printf( __( 'This is a child theme of %s.', 'wporg-themes' ), sprintf( '<a href="/themes/%1$s/">%2$s</a>', '{{{ data.parent.slug }}}', '{{{ data.parent.name }}}' ) ); ?></p>
				</div>
				<# } #>

				<div class="theme-meta-info">
					<p class="updated"><?php printf( __( 'Last updated: %s', 'wporg-themes' ), '<strong>{{ data.last_updated }}</strong>' ); ?></p>
					<# if ( data.theme_url ) { #>
					<a href="{{ data.theme_url }}"><?php _e( 'Theme Homepage', 'wporg-themes' ); ?></a>
					<# } #>
				</div>
			</div><!-- .theme-head -->

			<div class="theme-info">
				<# if ( data.screenshot_url ) { #>
				<div class="screenshot"><img src="{{ data.screenshot_url }}?w=1142&strip=all" alt=""/></div>
				<# } else { #>
				<div class="screenshot blank"></div>
				<# } #>

				<div class="theme-description entry-summary" itemprop="description"><p>{{{ data.description }}}</p></div>

				<# if ( data.tags ) { #>
				<div class="theme-tags">
					<h4><?php _e( 'Tags:', 'wporg-themes' ); ?></h4>
					{{{ data.tags }}}
				</div><!-- .theme-tags -->
				<# } #>

				<div class="theme-downloads">
					<h4><?php _e( 'Downloads Per Day', 'wporg-themes' ); ?></h4>
					<div id="theme-download-stats-{{data.id}}" class="chart"></div>
					<p class="total-downloads"><?php printf( __( 'Total downloads: %s', 'wporg-themes' ), '<strong>{{ data.downloaded }}</strong>' ); ?></p>
				</div><!-- .theme-downloads -->
			</div>

			<div class="theme-meta">
				<div class="theme-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
					<meta itemprop="ratingCount" content="{{ data.num_ratings }}"/>
					<h4><?php _e( 'Ratings', 'wporg-themes' ); ?></h4>

					<# if ( data.rating ) { #>
					<div class="rating rating-{{ Math.round( data.rating / 10 ) * 10 }}">
						<span class="one"></span>
						<span class="two"></span>
						<span class="three"></span>
						<span class="four"></span>
						<span class="five"></span>
						<p class="description"><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), '<span itemprop="ratingValue">{{ ( data.rating / 20 ).toFixed( 1 ) }}</span>' ); ?></p>
					</div>
					<# } else { #>
					<div class="rating">
						<div class="ratings"><?php _e( 'This theme has not been rated yet.', 'wporg-themes' ); ?></div>
					</div>
					<# } #>

					<# if ( data.ratings ) { #>
					<ul>
						<li class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=5" title="<?php echo esc_attr( sprintf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 5 ) ); ?>">
								<span class="counter-label"><?php printf( _n( '%d star', '%d stars', 5, 'wporg-themes' ), 5 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 100 * data.ratings[5] / data.num_ratings }}%;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[5] }}</span>
							</a>
						</li>
						<li class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=4" title="<?php echo esc_attr( sprintf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 4 ) ); ?>">
								<span class="counter-label"><?php printf( _n( '%d star', '%d stars', 4, 'wporg-themes' ), 4 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 100 * data.ratings[4] / data.num_ratings }}%;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[4] }}</span>
							</a>
						</li>
						<li class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=3" title="<?php echo esc_attr( sprintf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 3 ) ); ?>">
								<span class="counter-label"><?php printf( _n( '%d star', '%d stars', 3, 'wporg-themes' ), 3 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 100 * data.ratings[3] / data.num_ratings }}%;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[3] }}</span>
							</a>
						</li>
						<li class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=2" title="<?php echo esc_attr( sprintf( __( 'Click to see reviews that provided a rating of %d stars', 'wporg-themes' ), 2 ) ); ?>">
								<span class="counter-label"><?php printf( _n( '%d star', '%d stars', 2, 'wporg-themes' ), 2 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 100 * data.ratings[2] / data.num_ratings }}%;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[2] }}</span>
							</a>
						</li>
						<li class="counter-container">
							<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=1" title="<?php esc_attr_e( 'Click to see reviews that provided a rating of 1 star', 'wporg-themes' ); ?>">
								<span class="counter-label"><?php printf( _n( '%d star', '%d stars', 1, 'wporg-themes' ), 1 ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 100 * data.ratings[1] / data.num_ratings }}%;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[1] }}</span>
							</a>
						</li>
					</ul>
					<# } #>

					<a class="button button-secondary" href="https://wordpress.org/support/view/theme-reviews/{{ data.id }}#postform"><?php _e( 'Add your review', 'wporg-themes' ); ?></a>
				</div><!-- .theme-rating -->

				<div class="theme-support">
					<h4><?php _e( 'Support', 'wporg-themes' ); ?></h4>
					<p><?php _e( 'Got something to say? Need help?', 'wporg-themes' ); ?></p>
					<a href="//wordpress.org/support/theme/{{ data.slug }}" class="button button-secondary"><?php _e( 'View support forum', 'wporg-themes' ); ?></a>
				</div><!-- .theme-support -->

				<div class="theme-devs">
					<h4><?php _e( 'Development', 'wporg-themes' ); ?></h4>
					<h5><?php _e( 'Subscribe', 'wporg-themes' ); ?></h5>
					<ul class="unmarked-list">
						<li>
							<a href="//themes.trac.wordpress.org/log/{{data.id}}?limit=100&mode=stop_on_copy&format=rss">
								<img src="//s.w.org/style/images/feedicon.png" />
								<?php _e( 'Development Log', 'wporg-themes' ); ?>
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
			</div>
		</div>
	</div>
</script>

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

			<div class="theme-info hentry">
				<h3 class="theme-name entry-title">{{{ data.name }}}</h3>
				<span class="theme-version">
					<?php printf( __( 'Version: %s' ), sprintf( '<abbr title="%1$s">%2$s</abbr>', esc_attr( sprintf( __( 'Last updated: %s' ), '{{ new Date(data.last_updated).toLocaleDateString() }}' ) ), '{{{ data.version }}}' ) ); ?>
				</span>
				<h4 class="theme-author"><?php printf( __( 'By %s' ), '<span class="author">{{{ data.author }}}</span>' ); ?></h4>

				<p class="theme-description entry-summary">{{{ data.description }}}</p>

				<# if ( data.parent ) { #>
					<p class="parent-theme"><?php printf( __( 'This is a child theme of %s.' ), '<strong>{{{ data.parent }}}</strong>' ); ?></p>
				<# } #>

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

			<div class="theme-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
				<meta itemprop="ratingValue" content="{{ (data.rating/20).toFixed(1) }}"/>
				<meta itemprop="ratingCount" content="{{ data.num_ratings }}"/>
				<h4><?php _e( 'Ratings', 'wporg-themes' ); ?></h4>
				<div class="star-holder">
					<div class="star-rating" style="width: {{ (data.rating).toFixed(1) }}%"><?php printf( __( '%d stars', 'wporg-themes' ), '{{ Math.round( data.rating ) }}' ); ?></div>
				</div>
				<span><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), '{{ (data.rating/20).toFixed(1) }}' ); ?></span>
				<?php
				$ratingcount = array(); // TODO: Rating counts
				foreach ( range( 1, 5 ) as $val ) {
					if ( empty( $ratingcount[ $val ] ) ) {
						$ratingcount[ $val ] = 0;
					}
				}
				krsort( $ratingcount );
				foreach ( $ratingcount as $key => $ratecount ) :
					?>
					<div class="counter-container">
						<a href="//wordpress.org/support/view/theme-reviews/{{ data.id }}?filter=<?php echo $key; ?>" title="<?php printf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $key, 'wporg-themes' ), $key ); ?>">
							<span class="counter-label" style="float:left; margin-right:5px;"><?php printf( __( '%d stars', 'wporg-themes' ), $key ); ?></span>
						<span class="counter-back" style="height:17px;width:92px;background-color:#ececec;float:left;">
							<span class="counter-bar" style="width: <?php echo 92 * ( $ratecount / count( 1 ) ); ?>px;height:17px;background-color:#fddb5a;float:left;"></span>
						</span>
						</a>
						<span class="counter-count" style="margin-left:5px;"><?php echo $ratecount; ?></span>
					</div>
				<?php endforeach; ?>
			</div><!-- .theme-rating -->

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
					<li><a href="//themes.trac.wordpress.org/log/{{data.id}}/" rel="nofollow"><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
					<li><a href="//themes.svn.wordpress.org/{{data.id}}/" rel="nofollow"><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
					<li><a href="//themes.trac.wordpress.org/browser/{{data.id}}/" rel="nofollow"><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
				</ul>
			</div><!-- .theme-devs -->

			<div class="theme-downloads">
				<h4><?php _e( 'Downloads Per Day' ); ?></h4>
				<div id="theme-download-stats-{{data.id}}" class="chart"></div>
			</div><!-- .theme-downloads -->
		</div>

		<div class="theme-actions">
			<a href="" class="button button-primary"><?php _e( 'Download' ); ?></a>
			<a href="{{{ data.preview_url }}}" class="button button-secondary"><?php _e( 'Preview' ); ?></a>
		</div>
	</div>
</script>

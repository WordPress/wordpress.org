<script id="tmpl-theme-single" type="text/template">
	<div class="theme-navigation">
		<button class="close"><?php _e( 'Return to Themes List', 'wporg-themes' ); ?></button>
		<div class="navigation post-navigation">
			<button class="left dashicons dashicons-arrow-left-alt2"><span class="screen-reader-text"><?php _e( 'Show previous theme', 'wporg-themes' ); ?></span></button>
			<button class="right dashicons dashicons-arrow-right-alt2"><span class="screen-reader-text"><?php _e( 'Show next theme', 'wporg-themes' ); ?></span></button>
		</div>
	</div>
	<div class="theme-wrap">
		<div class="theme-about hentry" data-slug="{{{ data.slug }}}">
			<# if ( data.is_outdated ) { #>
			<div class="theme-notice notice notice-warning notice-alt">
				<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
			</div><!-- .theme-notice -->
			<# } #>

			<div>
				<h2 class="theme-name entry-title">{{{ data.name }}}</h2>
				<# if ( data.author.display_name ) { #>
				<div class="theme-author"><?php printf( _x( 'By %s', 'theme author', 'wporg-themes' ), '<a href="{{{ data.path }}}author/{{ data.author.user_nicename }}/"><span class="author">{{{ data.author.display_name }}}</span></a>' ); ?></div>
				<# } #>

				<# if ( data.show_favorites && data.is_favorited ) { #>
					<span class="dashicons dashicons-heart favorite favorited"></span>
				<# } else if ( data.show_favorites ) { #>
					<span class="dashicons dashicons-heart favorite"></span>
				<# } #>
			</div>

			<div class="theme-head">
				<# if ( data.is_community ) { #>
				<div class="widget categorization-widget categorization-widget-community">
					<div class="widget-head">
						<h3><?php esc_html_e( 'Community Theme', 'wporg-themes' ); ?></h3>
						<a href="{{{ data.external_repository_url }}}" rel="nofollow"><?php _e( 'Contribute', 'wporg-themes' ); ?></a>
					</div>
					<p><?php esc_html_e( 'This theme is developed and supported by a community.', 'wporg-themes' ); ?></p>
				</div>
				<# } #>

				<# if ( data.is_commercial ) { #>
				<div class="widget categorization-widget categorization-widget-commercial">
					<div class="widget-head">
						<h3><?php esc_html_e( 'Commercial Theme', 'wporg-themes' ); ?></h3>
						<a href="{{{ data.external_support_url }}}" rel="nofollow"><?php _e( 'Support', 'wporg-themes' ); ?></a>
					</div>
					<p><?php esc_html_e( 'This theme is free but offers additional paid commercial upgrades or support.', 'wporg-themes' ); ?></p>
				</div>
				<# } #>

				<div class="theme-actions clear">
					<a href="{{{ data.preview_url }}}" class="button button-secondary alignleft"><?php _e( 'Preview', 'wporg-themes' ); ?></a>
					<a href="{{ data.download_link }}" class="button button-primary alignright"><?php _e( 'Download', 'wporg-themes' ); ?></a>
				</div>

				<# if ( data.parent ) { #>
				<div class="theme-notice notice notice-info notice-alt">
					<p class="parent"><?php printf( __( 'This is a child theme of %s.', 'wporg-themes' ), sprintf( '<a href="%1$s">%2$s</a>', '{{{ data.path }}}{{{ data.parent.slug }}}/', '{{{ data.parent.name }}}' ) ); ?></p>
				</div>
				<# } #>

				<div class="theme-meta-info">
					<p class="version"><?php printf( __( 'Version: %s', 'wporg-themes' ), '<strong>{{ data.version }}</strong>' ); ?></p>
					<p class="updated"><?php printf( __( 'Last updated: %s', 'wporg-themes' ), '<strong>{{ data.last_updated }}</strong>' ); ?></p>
					<p class="active_installs"><?php printf( __( 'Active Installations: %s', 'wporg-themes' ), '<strong>{{ data.active_installs }}</strong>' ); ?></p>
					<# if ( data.requires ) { #>
					<p class="requires"><?php printf( __( 'WordPress Version: %s', 'wporg-themes' ), '<strong>' . sprintf( __( '%s or higher', 'wporg-themes' ), '{{ data.requires }}' ) . '</strong>' ); ?></p>
					<# } #>
					<# if ( data.requires_php ) { #>
					<p class="requires_php"><?php printf( __( 'PHP Version: %s', 'wporg-themes' ), '<strong>' . sprintf( __( '%s or higher', 'wporg-themes' ), '{{ data.requires_php }}' ) . '</strong>' ); ?></p>
					<# } #>
					<# if ( data.theme_url ) { #>
					<p class="theme_homepage"><a href="{{ data.theme_url }}"><?php _e( 'Theme Homepage', 'wporg-themes' ); ?></a></p>
					<# } #>
				</div>
			</div><!-- .theme-head -->

			<div class="theme-info">
				<# if ( data.screenshot_url ) { #>
				<div class="screenshot">
					<picture>
						<source media="(min-width: 782px)" srcset="{{ data.screenshot_url }}?w=572&strip=all, {{ data.screenshot_url }}?w=1144&strip=all 2x">
						<source media="(min-width: 481px) and (max-width: 782px)" srcset="{{ data.screenshot_url }}?w=700&strip=all, {{ data.screenshot_url }}?w=1400&strip=all 2x">
						<source media="(min-width: 401px) and (max-width: 480px)" srcset="{{ data.screenshot_url }}?w=420&strip=all, {{ data.screenshot_url }}?w=840&strip=all 2x">
						<source media="(max-width: 400px)" srcset="{{ data.screenshot_url }}?w=344&strip=all, {{ data.screenshot_url }}?w=688&strip=all 2x">
						<img src="{{ data.screenshot_url }}?w=572&strip=all" srcset="{{ data.screenshot_url }}?w=1144&strip=all 2x" loading="lazy" alt="">
					</picture>
				</div>
				<# } else { #>
				<div class="screenshot blank"></div>
				<# } #>

				<div class="wporg-style-variations wporg-horizontal-slider-js"></div>

				<div class="theme-description entry-summary"><p>{{{ data.description }}}</p></div>

				<# if ( data.tags ) { #>
				<div class="theme-tags">
					<h3><?php _e( 'Tags:', 'wporg-themes' ); ?></h3>
					{{{ data.tags }}}
				</div><!-- .theme-tags -->
				<# } #>

				<div id="theme-patterns-js" class="theme-patterns" style="display: none;">
					<h3><?php _e( 'Patterns:', 'wporg-themes' ); ?></h3>	
					<div id="theme-patterns-grid-js" class="theme-patterns-grid"></div>
					<a id="theme-patterns-button-js" class="theme-patterns-button" href="#" style="display:none"><?php _e( 'Show all patterns', 'wporg-themes' ); ?></a>
				</div>

				<div class="theme-downloads">
					<h3><?php _e( 'Downloads Per Day', 'wporg-themes' ); ?></h3>
					<div id="theme-download-stats-{{data.id}}" class="chart"></div>
				</div><!-- .theme-downloads -->

				<# if ( data.can_configure_categorization_options && data.is_community ) { #>
				<div class="theme-categorization-options">
					<h3><?php _e( 'Community Options', 'wporg-themes' ); ?></h3>
					<p><?php esc_html_e( 'This theme is developed and supported by a community.', 'wporg-themes' ); ?></p>
					<form id="community" class="categorization" method="POST">
					<p>
						<label for="external_repository_url"><?php esc_html_e( 'Development repository URL', 'wporg-themes' ); ?></label>
						<input id="external_repository_url" type="text" name="external_repository_url" value="{{{ data.external_repository_url }}}" data-original-value="{{{ data.external_repository_url}}}">
						<span class="help"><?php esc_html_e( 'Optional. The URL where development happens, such as at github.com.', 'wporg-themes' ); ?></span>
					</p>
					<p>
						<button class="button button-secondary" type="submit"><?php esc_html_e( 'Save', 'wporg-themes' ); ?></button>
						<span class="success-msg"><?php esc_html_e( 'Saved! Please wait for the caches to update.', 'wporg-themes' ) ?></span>
					</p>
					</form>
				</div><!-- .theme-categorization-options -->
				<# } #>

				<# if ( data.can_configure_categorization_options && data.is_commercial ) { #>
				<div class="theme-categorization-options">
					<h3><?php _e( 'Commercial Options', 'wporg-themes' ); ?></h3>
					<p><?php esc_html_e( 'This theme is free but offers paid upgrades, support, and/or add-ons.', 'wporg-themes' ); ?></p>
					<form id="commercial" class="categorization" method="POST">
					<p>
						<label for="external_support_url"><?php esc_html_e( 'Commercial support URL', 'wporg-themes' ); ?></label>
						<input id="external_support_url" type="text" name="external_support_url" value="{{{ data.external_support_url }}}" data-original-value="{{{ data.external_support_url }}}">
						<span class="help"><?php esc_html_e( 'Optional. The URL for theme support, other than its support forum on wordpress.org.', 'wporg-themes' ); ?></span>
					</p>
					<p>
						<button class="button button-secondary" type="submit"><?php esc_html_e( 'Save', 'wporg-themes' ); ?></button>
						<span class="success-msg"><?php esc_html_e( 'Saved! Please wait for the caches to update.', 'wporg-themes' ) ?></span>
					</p>
					</form>
				</div><!-- .theme-categorization-options -->
				<# } #>
			</div>

			<div class="theme-meta">
				<div class="theme-ratings">
					<h3><?php _e( 'Ratings', 'wporg-themes' ); ?></h3>

					<a class="reviews-link" href="//wordpress.org/support/theme/{{ data.id }}/reviews/"><?php esc_html_e( 'See all', 'wporg-themes' ); ?></a>

					<# if ( data.rating ) { #>
					<div class="rating rating-{{ Math.round( data.rating / 10 ) * 10 }}">
						<span class="one"></span>
						<span class="two"></span>
						<span class="three"></span>
						<span class="four"></span>
						<span class="five"></span>
						<p class="description"><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), '<span>{{ Math.round( data.rating / 20 / 0.5 )*0.5 }}</span>' ); ?></p>
					</div>
					<# } else { #>
					<div class="rating">
						<div class="ratings"><?php _e( 'This theme has not been rated yet.', 'wporg-themes' ); ?></div>
					</div>
					<# } #>

					<# if ( data.ratings ) { #>
					<ul>
						<?php foreach ( range( 5, 1 ) as $stars ) : ?>
						<li class="counter-container">
							<a href="//wordpress.org/support/theme/{{ data.id }}/reviews/?filter=<?php echo $stars; ?>" title="<?php echo esc_attr( sprintf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $stars, 'wporg-themes' ), $stars ) ); ?>">
								<span class="counter-label"><?php printf( _n( '%d star', '%d stars', $stars, 'wporg-themes' ), $stars ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: {{ 100 * data.ratings[<?php echo $stars; ?>] / data.num_ratings }}%;"></span>
								</span>
								<span class="counter-count">{{ data.ratings[<?php echo $stars; ?>] }}</span>
							</a>
						</li>
						<?php endforeach; ?>
					</ul>
					<# } #>

					<a class="button button-secondary" href="https://wordpress.org/support/theme/{{ data.id }}/reviews/#new-post"><?php _e( 'Add my review', 'wporg-themes' ); ?></a>
				</div><!-- .theme-rating -->

				<div class="theme-support">
					<h3><?php _e( 'Support', 'wporg-themes' ); ?></h3>
					<p><?php _e( 'Got something to say? Need help?', 'wporg-themes' ); ?></p>
					<a href="//wordpress.org/support/theme/{{ data.slug }}" class="button button-secondary"><?php _e( 'View support forum', 'wporg-themes' ); ?></a>
				</div><!-- .theme-support -->

				<div class="theme-report">
        				<h3><?php _e( 'Report', 'wporg-themes' ); ?></h3>
        				<p><?php _e( 'Does this theme have major issues?', 'wporg-themes' ); ?></p>
        				<a rel="nofollow" href="https://make.wordpress.org/themes/report-theme/?rep-name={{ data.current_user }}&rep-theme={{ data.homepage }}&rep-subject=Reported+Theme:+{{ data.name }}" class="button button-secondary"><?php _e( 'Report this theme', 'wporg-themes' ); ?></a>
				</div><!-- .theme-report -->

				<div class="theme-translations">
					<h3><?php _e( 'Translations', 'wporg-themes' ); ?></h3>
					<p>
						<a href="https://translate.wordpress.org/projects/wp-themes/{{ data.slug }}">
							<?php printf( __( 'Translate %s', 'wporg-themes' ), '{{ data.name }}' ); ?>
						</a>
					</p>
				</div><!-- .theme-translations -->

				<div class="theme-devs">
					<h3><?php _e( 'Subscribe', 'wporg-themes' ); ?></h3>
					<ul class="unmarked-list">
						<li>
							<a href="//themes.trac.wordpress.org/log/{{data.id}}?limit=100&mode=stop_on_copy&format=rss">
								<span class="dashicons dashicons-rss"></span><?php _e( 'Development Log', 'wporg-themes' ); ?>
							</a>
						</li>
					</ul>

					<h3><?php _e( 'Browse the Code', 'wporg-themes' ); ?></h3>
					<ul class="unmarked-list">
						<li><a href="//themes.trac.wordpress.org/log/{{data.id}}/" rel="nofollow"><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.svn.wordpress.org/{{data.id}}/" rel="nofollow"><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.trac.wordpress.org/browser/{{data.id}}/" rel="nofollow"><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.trac.wordpress.org/query?keywords=~theme-{{data.id}}" rel="nofollow"><?php _e( 'Trac Tickets', 'wporg-themes' ); ?></a></li>
					</ul>
				</div><!-- .theme-devs -->
			</div>
		</div>
	</div>
</script>

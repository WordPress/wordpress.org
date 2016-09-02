<div>
	<div class="theme-navigation">
		<a class="close" href="<?php echo home_url('/'); ?>"><?php _e( 'Return to Themes List', 'wporg-themes' ); ?></a>
		<div class="navigation post-navigation">
			<button class="left dashicons dashicons-arrow-left-alt2 disabled"><span class="screen-reader-text"><?php _e( 'Show previous theme', 'wporg-themes' ); ?></span></button>
			<button class="right dashicons dashicons-arrow-right-alt2 disabled"><span class="screen-reader-text"><?php _e( 'Show next theme', 'wporg-themes' ); ?></span></button>
		</div>
	</div>
	<div class="theme-wrap">
		<div class="theme-about hentry" itemscope itemtype="http://schema.org/CreativeWork">
			<?php if ( time() - strtotime( $theme->last_updated ) > 2 * YEAR_IN_SECONDS ) { ?>
			<div class="theme-notice notice notice-warning">
				<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
			</div><!-- .theme-notice -->
			<?php } ?>

			<div>
				<h3 class="theme-name entry-title" itemprop="name"><?php echo esc_html( $theme->name ); ?></h3>
				<h4 class="theme-author"><?php printf( _x( 'By %s', 'theme author', 'wporg-themes' ), '<a href="https://wordpress.org/themes/author/' . $theme->author->user_nicename . '/"><span class="author" itemprop="author">' . esc_html( $theme->author->display_name ) . '</span></a>' ); ?></h4>
				<?php if ( is_user_logged_in() && wporg_themes_is_favourited( $theme->slug ) ) { ?>
					<span class="dashicons dashicons-heart favorite favorited"></span>
				<?php } elseif ( is_user_logged_in() ) { ?>
					<span class="dashicons dashicons-heart favorite"></span>
				<?php } ?>
			</div>

			<div class="theme-head">
				<div class="theme-actions clear">
					<a href="<?php echo esc_url( $theme->preview_url ); ?>" class="button button-secondary alignleft"><?php _e( 'Preview', 'wporg-themes' ); ?></a>
					<a href="<?php echo esc_url( $theme->download_link); ?>" class="button button-primary alignright"><?php _e( 'Download', 'wporg-themes' ); ?></a>
				</div>

				<?php if ( !empty( $theme->parent ) ) { ?>
				<div class="theme-notice notice notice-info">
					<p class="parent"><?php printf( __( 'This is a child theme of %s.', 'wporg-themes' ), sprintf( '<a href="%1$s">%2$s</a>', home_url( $theme->parent['slug'] . '/' ), esc_html( $theme->parent['name'] ) ) ); ?></p>
				</div>
				<?php } ?>

				<div class="theme-meta-info">
					<p class="updated"><?php printf( __( 'Last updated: %s', 'wporg-themes' ), '<strong>' . date_i18n( 'F j, Y', strtotime( $theme->last_updated ) ) . '</strong>' ); ?></p>
					<?php
						$active_installs = $theme->active_installs;
						if ( $active_installs < 10 ) {
							$active_installs = __( 'Less than 10', 'wporg-themes' );
						} elseif ( $active_installs >= 1000000 ) {
							$active_installs = __( '1+ million', 'wporg-themes' );
						} else {
							$active_installs = number_format_i18n( $active_installs ) . '+';
						}
					?>
					<p class="active_installs"><?php printf( __( 'Active Installs: %s', 'wporg-themes' ), '<strong>' . $active_installs . '</strong>' ); ?></p>
					<?php if ( $theme->theme_url ) { ?>
					<a href="<?php echo esc_url( $theme->theme_url ); ?>"><?php _e( 'Theme Homepage', 'wporg-themes' ); ?></a>
					<?php } ?>
				</div>
			</div><!-- .theme-head -->

			<div class="theme-info">
				<?php if ( $theme->screenshot_url ) { ?>
					<div class="screenshot"><img src="<?php echo esc_url( $theme->screenshot_url ); ?>?w=1142&strip=all" alt=""/></div>
				<?php } else { ?>
					<div class="screenshot blank"></div>
				<?php } ?>

				<div class="theme-description entry-summary" itemprop="description"><p><?php echo esc_html( $theme->description ); ?></p></div>

				<?php if ( $theme->tags ) { ?>
				<div class="theme-tags">
					<h4><?php _e( 'Tags:', 'wporg-themes' ); ?></h4>
					<?php
					$tag_links = array();
					foreach ( $theme->tags as $slug => $tagname ) {
						$tag_links[] = sprintf(
							"<a href='%s'>%s</a>",
							esc_url( home_url( "/tags/$slug/" ) ),
							esc_html( translate( $tagname, 'wporg-themes' ) )
						);
					}
					echo implode( ', ', $tag_links );
					?>
				</div><!-- .theme-tags -->
				<?php } ?>

				<div class="theme-downloads">
				</div><!-- .theme-downloads -->
			</div>

			<div class="theme-meta">
				<div class="theme-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
					<meta itemprop="ratingCount" content="<?php echo $theme->num_ratings; ?>"/>
					<h4><?php _e( 'Ratings', 'wporg-themes' ); ?></h4>

					<?php if ( $theme->rating ) { ?>
					<div class="rating rating-<?php echo round( $theme->rating / 10 ) * 10; ?>">
						<span class="one"></span>
						<span class="two"></span>
						<span class="three"></span>
						<span class="four"></span>
						<span class="five"></span>
						<p class="description"><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), '<span itemprop="ratingValue">' . round( $theme->rating / 20 / 0.5 )*0.5 . '</span>' ); ?></p>
					</div>
					<?php } else { ?>
					<div class="rating">
						<div class="ratings"><?php _e( 'This theme has not been rated yet.', 'wporg-themes' ); ?></div>
					</div>
					<?php } ?>

					<?php if ( $theme->ratings ) { ?>
					<ul>
						<?php foreach ( range( 5, 1 ) as $stars ) :
							$rating_bar_width = $theme->num_ratings ? 100 * $theme->ratings[$stars] / $theme->num_ratings : 0;
						?>
						<li class="counter-container">
							<a href="//wordpress.org/support/theme/<?php echo $theme->slug; ?>/reviews/?filter=<?php echo $stars; ?>" title="<?php echo esc_attr( sprintf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $stars, 'wporg-themes' ), $stars ) ); ?>">
								<span class="counter-label"><?php printf( _n( '%d star', '%d stars', $stars, 'wporg-themes' ), $stars ); ?></span>
								<span class="counter-back">
									<span class="counter-bar" style="width: <?php echo $rating_bar_width; ?>%;"></span>
								</span>
								<span class="counter-count"><?php echo $theme->ratings[$stars]; ?></span>
							</a>
						</li>
						<?php endforeach; ?>
					</ul>
					<?php } ?>

					<a class="button button-secondary" href="https://wordpress.org/support/theme/<?php echo $theme->slug; ?>/reviews/#new-post"><?php _e( 'Add your review', 'wporg-themes' ); ?></a>
				</div><!-- .theme-rating -->

				<div class="theme-support">
					<h4><?php _e( 'Support', 'wporg-themes' ); ?></h4>
					<p><?php _e( 'Got something to say? Need help?', 'wporg-themes' ); ?></p>
					<a href="//wordpress.org/support/theme/<?php echo $theme->slug; ?>" class="button button-secondary"><?php _e( 'View support forum', 'wporg-themes' ); ?></a>
				</div><!-- .theme-support -->

				<div class="theme-translations">
					<h4><?php _e( 'Translations', 'wporg-themes' ); ?></h4>
					<p>
						<a href="<?php echo esc_url( "https://translate.wordpress.org/projects/wp-themes/{$theme->slug}" ); ?>">
							<?php printf( __( 'Translate %s', 'wporg-themes' ), $theme->name ); ?>
						</a>
					</p>
				</div><!-- .theme-translations -->

				<div class="theme-devs">
					<h4><?php _e( 'Development', 'wporg-themes' ); ?></h4>
					<h5><?php _e( 'Subscribe', 'wporg-themes' ); ?></h5>
					<ul class="unmarked-list">
						<li>
							<a href="//themes.trac.wordpress.org/log/<?php echo $theme->slug; ?>?limit=100&amp;mode=stop_on_copy&amp;format=rss">
								<img src="//s.w.org/style/images/feedicon.png" />
								<?php _e( 'Development Log', 'wporg-themes' ); ?>
							</a>
						</li>
					</ul>

					<h5><?php _e( 'Browse the Code', 'wporg-themes' ); ?></h5>
					<ul class="unmarked-list">
						<li><a href="//themes.trac.wordpress.org/log/<?php echo $theme->slug; ?>/" rel="nofollow"><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.svn.wordpress.org/<?php echo $theme->slug; ?>/" rel="nofollow"><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.trac.wordpress.org/browser/<?php echo $theme->slug; ?>/" rel="nofollow"><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
					</ul>
				</div><!-- .theme-devs -->
			</div>
		</div>
	</div>
</div>

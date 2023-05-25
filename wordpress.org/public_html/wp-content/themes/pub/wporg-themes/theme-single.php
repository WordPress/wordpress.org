<?php
	// $post = WP_Post, $theme = Theme info object

	$is_commercial = has_term( 'commercial', 'theme_business_model', $post );
	$external_support_url = $is_commercial ? get_post_meta( $post->ID, 'external_support_url', true ) : '';
	$is_community = has_term( 'community', 'theme_business_model', $post );
	$external_repository_url = $is_community ? get_post_meta( $post->ID, 'external_repository_url', true ) : '';
?>
<div>
	<div class="theme-navigation">
		<a class="close" href="<?php echo home_url('/'); ?>"><?php _e( 'Return to Themes List', 'wporg-themes' ); ?></a>
		<div class="navigation post-navigation">
			<button class="left dashicons dashicons-arrow-left-alt2 disabled"><span class="screen-reader-text"><?php _e( 'Show previous theme', 'wporg-themes' ); ?></span></button>
			<button class="right dashicons dashicons-arrow-right-alt2 disabled"><span class="screen-reader-text"><?php _e( 'Show next theme', 'wporg-themes' ); ?></span></button>
		</div>
	</div>
	<div class="theme-wrap">
		<?php printf( '<div class="theme-about hentry" data-slug="%s">' . "\n", $theme->slug ); ?>
			<?php if ( time() - strtotime( $theme->last_updated ) > 2 * YEAR_IN_SECONDS ) { ?>
			<div class="theme-notice notice notice-warning notice-alt">
				<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
			</div><!-- .theme-notice -->
			<?php } ?>

			<div>
				<h1 class="theme-name entry-title"><?php echo esc_html( $theme->name ); ?></h1>
				<?php if ( $theme->author->display_name ) { ?>
				<span class="theme-author"><?php printf( _x( 'By %s', 'theme author', 'wporg-themes' ), '<a href="https://wordpress.org/themes/author/' . $theme->author->user_nicename . '/"><span class="author">' . esc_html( $theme->author->display_name ) . '</span></a>' ); ?></span>
				<?php } ?>

				<?php if ( is_user_logged_in() && wporg_themes_is_favourited( $theme->slug ) ) { ?>
					<span class="dashicons dashicons-heart favorite favorited"></span>
				<?php } elseif ( is_user_logged_in() ) { ?>
					<span class="dashicons dashicons-heart favorite"></span>
				<?php } ?>
			</div>

			<div class="theme-head">
				<?php if ( $is_community ) : ?>
				<div class="widget categorization-widget categorization-widget-community">
					<h3><?php esc_html_e( 'Community Theme', 'wporg-themes' ); ?></h3>
					<?php
					if ( $external_repository_url ) : ?>
						<a href="<?php echo esc_url( $external_repository_url ); ?>" rel="nofollow"><?php _e( 'Contribute', 'wporg-themes' ); ?></a>
					<?php endif; ?>
					<p><?php esc_html_e( 'This theme is developed and supported by a community.', 'wporg-themes' ); ?></p>
				</div>
				<?php endif; ?>

				<?php if ( $is_commercial ) : ?>
				<div class="widget categorization-widget categorization-widget-commercial">
					<h3><?php esc_html_e( 'Commercial Theme', 'wporg-themes' ); ?></h3>
					<?php
					if ( $external_support_url ) : ?>
						<a href="<?php echo esc_url( $external_support_url ); ?>" rel="nofollow"><?php _e( 'Support', 'wporg-themes' ); ?></a>
					<?php endif; ?>
					<p><?php esc_html_e( 'This theme is free but offers additional paid commercial upgrades or support.', 'wporg-themes' ); ?></p>
				</div>
				<?php endif; ?>

				<div class="theme-actions clear">
					<a href="<?php echo esc_url( $theme->preview_url ); ?>" class="button button-secondary alignleft"><?php _e( 'Preview', 'wporg-themes' ); ?></a>
					<a href="<?php echo esc_url( $theme->download_link); ?>" class="button button-primary alignright"><?php _e( 'Download', 'wporg-themes' ); ?></a>
				</div>

				<?php if ( !empty( $theme->parent ) ) { ?>
				<div class="theme-notice notice notice-info notice-alt">
					<p class="parent"><?php printf( __( 'This is a child theme of %s.', 'wporg-themes' ), sprintf( '<a href="%1$s">%2$s</a>', home_url( $theme->parent['slug'] . '/' ), esc_html( $theme->parent['name'] ) ) ); ?></p>
				</div>
				<?php } ?>

				<div class="theme-meta-info">
					<p class="version">
						<?php printf( __( 'Version: %s', 'wporg-themes' ), '<strong>' . esc_html( $theme->version ) . '</strong>' ); ?>
					</p>
					<p class="updated">
						<?php printf( __( 'Last updated: %s', 'wporg-themes' ),
							/* translators: localized date format, see http://php.net/date */
							'<strong>' . date_i18n( _x( 'F j, Y', 'last update date format', 'wporg-themes' ), strtotime( $theme->last_updated ) ) . '</strong>'
						); ?>
					</p>
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
					<p class="active_installs"><?php printf( __( 'Active Installations: %s', 'wporg-themes' ), '<strong>' . $active_installs . '</strong>' ); ?></p>
					<?php if ( ! empty( $theme->requires ) ) { ?>
					<p class="requires">
						<?php printf( __( 'WordPress Version: %s', 'wporg-themes' ), '<strong>' . sprintf( __( '%s or higher', 'wporg-themes' ), esc_html( $theme->requires ) ) . '</strong>' ); ?>
					</p>
					<?php } ?>
					<?php if ( ! empty( $theme->requires_php ) ) { ?>
					<p class="requires_php">
						<?php printf( __( 'PHP Version: %s', 'wporg-themes' ), '<strong>' . sprintf( __( '%s or higher', 'wporg-themes' ), esc_html( $theme->requires_php ) ) . '</strong>' ); ?>
					</p>
					<?php } ?>
					<?php if ( $theme->theme_url ) { ?>
					<p class="theme_homapge"><a href="<?php echo esc_url( $theme->theme_url ); ?>"><?php _e( 'Theme Homepage', 'wporg-themes' ); ?></a></p>
					<?php } ?>
				</div>
			</div><!-- .theme-head -->

			<div class="theme-info">
				<?php if ( $theme->screenshot_url ) { ?>
					<?php $escaped_screenshot_url = esc_url( $theme->screenshot_url ); ?>
					<div class="screenshot">
						<picture>
							<source media="(min-width: 782px)" srcset="<?php echo $escaped_screenshot_url; ?>?w=572&strip=all, <?php echo $escaped_screenshot_url; ?>?w=1144&strip=all 2x">
							<source media="(min-width: 481px) and (max-width: 782px)" srcset="<?php echo $escaped_screenshot_url; ?>?w=700&strip=all, <?php echo $escaped_screenshot_url; ?>?w=1400&strip=all 2x">
							<source media="(min-width: 401px) and (max-width: 480px)" srcset="<?php echo $escaped_screenshot_url; ?>?w=420&strip=all, <?php echo $escaped_screenshot_url; ?>?w=840&strip=all 2x">
							<source media="(max-width: 400px)" srcset="<?php echo $escaped_screenshot_url; ?>?w=344&strip=all, <?php echo $escaped_screenshot_url; ?>?w=688&strip=all 2x">
							<img src="<?php echo $escaped_screenshot_url; ?>?w=572&strip=all" srcset="<?php echo $escaped_screenshot_url; ?>?w=1144&strip=all 2x" loading="lazy" alt="">
						</picture>
					</div>
				<?php } else { ?>
					<div class="screenshot blank"></div>
				<?php } ?>

				<div class="theme-description entry-summary"><p><?php echo esc_html( $theme->description ); ?></p></div>

				<?php if ( $theme->tags ) { ?>
				<div class="theme-tags">
					<h2><?php _e( 'Tags:', 'wporg-themes' ); ?></h2>
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
				<div class="theme-ratings">
					<h2><?php _e( 'Ratings', 'wporg-themes' ); ?></h2>

					<a class="reviews-link" href="//wordpress.org/support/theme/<?php echo $theme->slug; ?>/reviews/"><?php esc_html_e( 'See all', 'wporg-themes' ); ?></a>

					<?php if ( $theme->rating ) { ?>
					<div class="rating rating-<?php echo round( $theme->rating / 10 ) * 10; ?>">
						<span class="one"></span>
						<span class="two"></span>
						<span class="three"></span>
						<span class="four"></span>
						<span class="five"></span>
						<p class="description"><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), '<span>' . round( $theme->rating / 20 / 0.5 )*0.5 . '</span>' ); ?></p>
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

					<a class="button button-secondary" href="https://wordpress.org/support/theme/<?php echo $theme->slug; ?>/reviews/#new-post"><?php _e( 'Add my review', 'wporg-themes' ); ?></a>
				</div><!-- .theme-rating -->

				<div class="theme-support">
					<h2><?php _e( 'Support', 'wporg-themes' ); ?></h2>
					<p><?php _e( 'Got something to say? Need help?', 'wporg-themes' ); ?></p>
					<a href="//wordpress.org/support/theme/<?php echo $theme->slug; ?>" class="button button-secondary"><?php _e( 'View support forum', 'wporg-themes' ); ?></a>
				</div><!-- .theme-support -->

				<div class="theme-report">
					<h2><?php _e( 'Report', 'wporg-themes' ); ?></h2>
					<p><?php _e( 'Does this theme have major issues?', 'wporg-themes' ); ?></p>
					<?php
					$report_url = add_query_arg(
						urlencode_deep( array_filter( array(
							'rep-theme'   => "https://wordpress.org/themes/{$theme->slug}/",
							'rep-subject' => "Reported Theme: {$theme->name}", // Not translated, email subject.
							'rep-name'    => wp_get_current_user()->user_login,
						) ) ),
						'https://make.wordpress.org/themes/report-theme/'
					);
					?>
					<a rel="nofollow" href="<?php echo esc_url( $report_url ); ?>" class="button button-secondary"><?php _e( 'Report this theme', 'wporg-themes' ); ?></a>
				</div><!-- .theme-report -->

				<div class="theme-translations">
					<h2><?php _e( 'Translations', 'wporg-themes' ); ?></h2>
					<p>
						<a href="<?php echo esc_url( "https://translate.wordpress.org/projects/wp-themes/{$theme->slug}" ); ?>">
							<?php printf( __( 'Translate %s', 'wporg-themes' ), $theme->name ); ?>
						</a>
					</p>
				</div><!-- .theme-translations -->

				<div class="theme-devs">
					<h2><?php _e( 'Subscribe', 'wporg-themes' ); ?></h2>
					<ul class="unmarked-list">
						<li>
							<a href="//themes.trac.wordpress.org/log/<?php echo $theme->slug; ?>?limit=100&amp;mode=stop_on_copy&amp;format=rss">
								<span class="dashicons dashicons-rss"></span><?php _e( 'Development Log', 'wporg-themes' ); ?>
							</a>
						</li>
					</ul>

					<h2><?php _e( 'Browse the Code', 'wporg-themes' ); ?></h2>
					<ul class="unmarked-list">
						<li><a href="//themes.trac.wordpress.org/log/<?php echo $theme->slug; ?>/" rel="nofollow"><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.svn.wordpress.org/<?php echo $theme->slug; ?>/" rel="nofollow"><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.trac.wordpress.org/browser/<?php echo $theme->slug; ?>/" rel="nofollow"><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
						<li><a href="//themes.trac.wordpress.org/query?keywords=~theme-<?php echo $theme->slug; ?>" rel="nofollow"><?php _e( 'Trac Tickets', 'wporg-themes' ); ?></a></li>
					</ul>
				</div><!-- .theme-devs -->
			</div>
		</div>
	</div>
</div>

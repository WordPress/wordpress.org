<?php
$slug  = get_post()->post_name;
$theme = wporg_themes_query_api( 'theme_information', array( 'slug' => $slug, 'fields' => array( 'ratings' => true ) ) );
?>
<div class="theme-navigation">
	<a class="close" rel="home" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php _e( 'Return to Themes List', 'wporg-themes' ); ?></a>
	<div class="navigation post-navigation">
		<button class="left dashicons dashicons-no disabled"><span class="screen-reader-text"><?php _e( 'Show previous theme', 'wporg-themes' ); ?></span></button>
		<button class="right dashicons dashicons-no disabled"><span class="screen-reader-text"><?php _e( 'Show next theme', 'wporg-themes' ); ?></span></button>
	</div>
</div>
<div class="theme-wrap">
	<div class="theme-about hentry" itemscope itemtype="http://schema.org/CreativeWork">

		<?php if ( strtotime( '-2 years' ) > get_post_modified_time() ) : ?>
		<div class="theme-notice notice notice-warning">
			<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
		</div><!-- .theme-info -->
		<?php endif; ?>

		<div>
			<h3 class="theme-name entry-title" itemprop="name"><?php the_title(); ?></h3>
			<h4 class="theme-author">
				<?php printf( _x( 'By %s', 'theme author', 'wporg-themes' ), sprintf( '<a href="https://wordpress.org/themes/author/%s/"><span class="author" itemprop="author">%s</span></a>', get_the_author_meta( 'nicename' ), esc_html( get_the_author() ) ) ); ?>
			</h4>
		</div>

		<div class="theme-head">
			<div class="theme-actions clear">
				<a href="<?php echo esc_url( '//wp-themes.com/' . $slug ); ?>" class="button button-secondary alignleft"><?php _e( 'Preview', 'wporg-themes' ); ?></a>
				<a href="<?php echo esc_url( '//downloads.wordpress.org/theme/' . $slug . '.' . $theme->version . '.zip' ); ?>" class="button button-primary alignright"><?php _e( 'Download', 'wporg-themes' ); ?></a>
			</div>

			<?php
				if ( ! empty( get_post()->post_parent ) ) :
					$parent = get_post( get_post()->post_parent );
			?>
			<div class="theme-notice notice notice-info">
				<p class="parent-theme"><?php printf( __( 'This is a child theme of %s.', 'wporg-themes' ), sprintf( '<a href="%1$s">%2$s</a>', get_permalink( $parent->ID ), $parent->post_title ) ); ?></p>
			</div>
			<?php endif; ?>

			<div class="theme-meta-info">
				<p class="updated"><?php printf( __( 'Last updated: %s', 'wporg-themes' ), '<strong>' . date_i18n( get_option( 'date_format' ), strtotime( $theme->last_updated ) ) . '</strong>' ); ?></p>
				<?php
					$theme_url = wporg_themes_get_version_meta( get_the_ID(), '_theme_url', $theme->version );
					if ( ! empty( $theme_url ) ) :
				?>
				<a href="<?php echo esc_url( $theme_url ); ?>"><?php _e( 'Theme Homepage', 'wporg-themes' ); ?></a>
				<?php endif; ?>
			</div>
		</div><!-- .theme-head -->

		<div class="theme-info">
			<div class="screenshot"><?php the_post_thumbnail( '1142' ); ?></div>

			<div class="theme-description entry-summary" itemprop="description"><?php the_content(); ?></div>

			<div class="theme-tags">
				<?php the_tags( '<h4>' . __( 'Tags:', 'wporg-themes' ) . '</h4>' ); ?>
			</div><!-- .theme-tags -->

			<div class="theme-downloads">
				<h4><?php _e( 'Downloads', 'wporg-themes' ); ?></h4>

				<div id="theme-download-stats-<?php echo esc_attr( $slug ); ?>" class="chart"></div>
				<p class="total-downloads"><?php printf( __( 'Total downloads: %s', 'wporg-themes' ), '<strong>' . number_format_i18n( $theme->downloaded ) . '</strong>' ); ?></p>
			</div><!-- .theme-downloads -->
		</div><!-- .theme-info -->

		<div class="theme-meta">
			<div class="theme-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
				<meta itemprop="ratingCount" content="<?php echo esc_attr( $theme->num_ratings ); ?>"/>
				<h4><?php _e( 'Ratings', 'wporg-themes' ); ?></h4>

				<?php if ( ! empty( $theme->ratings ) ) : ?>
					<div class="rating rating-<?php echo esc_attr( round( $theme->rating / 10, 0) * 10 ); ?>">
						<span class="one"></span>
						<span class="two"></span>
						<span class="three"></span>
						<span class="four"></span>
						<span class="five"></span>
						<p class="description"><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), '<span itemprop="ratingValue">' . number_format_i18n( round($theme->rating / 20 / 0.5)*0.5, 1 )  . '</span>' ); ?></p>
					</div>
				<?php else : ?>
					<div class="rating">
						<div class="ratings"><?php _e( 'This theme has not been rated yet.', 'wporg-themes' ); ?></div>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $theme->ratings ) && ! empty( $theme->num_ratings ) ) : ?>
				<ul>
					<?php
						foreach ( $theme->ratings as $key => $rate_count ) :
							// Hack to have descending key/value pairs.
							$key = 6 - $key;
					?>
					<li class="counter-container">
						<a href="//wordpress.org/support/view/theme-reviews/<?php echo esc_attr( $slug ); ?>?filter=<?php echo $key; ?>" title="<?php echo esc_attr( sprintf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $key, 'wporg-themes' ), $key ) ); ?>">
							<span class="counter-label"><?php printf( _n( '%d star', '%d stars', $key, 'wporg-themes' ), $key ); ?></span>
							<span class="counter-back">
								<span class="counter-bar" style="width: <?php echo 100 * ( $theme->ratings[ $key ] / $theme->num_ratings ); ?>%;"></span>
							</span>
							<span class="counter-count"><?php echo $theme->ratings[ $key ]; ?></span>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>

				<a class="button button-secondary" href="https://wordpress.org/support/view/theme-reviews/<?php echo esc_attr( $slug ); ?>#postform"><?php _e( 'Add your review', 'wporg-themes' ); ?></a>
			</div><!-- .theme-rating -->

			<div class="theme-support">
				<h4><?php _e( 'Support', 'wporg-themes' ); ?></h4>
				<p><?php _e( 'Got something to say? Need help?', 'wporg-themes' ); ?></p>
				<a href="//wordpress.org/support/theme/<?php echo esc_attr( $slug ); ?>" class="button button-secondary"><?php _e( 'View support forum', 'wporg-themes' ); ?></a>
			</div><!-- .theme-support -->

			<div class="theme-devs">
				<h4><?php _e( 'Development', 'wporg-themes' ); ?></h4>
				<h5><?php _e( 'Subscribe', 'wporg-themes' ); ?></h5>
				<ul class="unmarked-list">
					<li>
						<a href="//themes.trac.wordpress.org/log/<?php echo esc_attr( $slug ); ?>?limit=100&mode=stop_on_copy&format=rss">
							<img src="//s.w.org/style/images/feedicon.png" />
							<?php _e( 'Development Log', 'wporg-themes' ); ?>
						</a>
					</li>
				</ul>

				<h5><?php _e( 'Browse the Code', 'wporg-themes' ); ?></h5>
				<ul class="unmarked-list">
					<li><a href="//themes.trac.wordpress.org/log/<?php echo esc_attr( $slug ); ?>/" rel="nofollow"><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
					<li><a href="//themes.svn.wordpress.org/<?php echo esc_attr( $slug ); ?>/" rel="nofollow"><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
					<li><a href="//themes.trac.wordpress.org/browser/<?php echo esc_attr( $slug ); ?>/" rel="nofollow"><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
				</ul>
			</div><!-- .theme-devs -->
		</div><!-- .theme-meta -->
	</div>
</div>

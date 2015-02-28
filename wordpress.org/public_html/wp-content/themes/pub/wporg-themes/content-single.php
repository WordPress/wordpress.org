<?php
$slug  = get_post()->post_name;
$theme = themes_api('theme_information', array( 'slug' => $slug ) );
?>
<div class="theme-wrap">
	<div class="theme-about hentry" itemscope itemtype="http://schema.org/CreativeWork">

		<?php if ( strtotime( '-2 years' ) > get_post_modified_time() ) : ?>
		<div class="theme-notice notice notice-warning">
			<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
		</div><!-- .theme-info -->
		<?php endif; ?>

		<div class="theme-head">
			<h3 class="theme-name entry-title" itemprop="name"><?php the_title(); ?></h3>
			<h4 class="theme-author">
				<?php printf( _x( 'By %s', 'post author', 'wporg-themes' ), sprintf( '<a href="https://profiles.wordpress.org/%s"><span class="author" itemprop="author">%s</span></a>', get_the_author_meta( 'nicename' ), esc_html( get_the_author() ) ) ); ?>
			</h4>

			<div class="theme-actions">
				<a href="<?php echo esc_url( '//wp-themes.com/' . $slug ); ?>" class="button button-secondary"><?php _e( 'Preview' ); ?></a>
				<a href="<?php echo esc_url( '//downloads.wordpress.org/theme/' . $slug . '.' . $theme->version . '.zip' ); ?>" class="button button-primary"><?php _e( 'Download' ); ?></a>
			</div>

			<?php
				if ( ! empty( get_post()->post_parent ) ) :
					$parent = get_post( get_post()->post_parent );
			?>
			<div class="theme-notice notice notice-info">
				<p class="parent-theme"><?php printf( __( 'This is a child theme of %s.' ), sprintf( '<a href="/themes/%1$s">%2$s</a>', get_permalink( $parent->ID ), $parent->post_title ) ); ?></p>
			</div>
			<?php endif; ?>
		</div><!-- .theme-head -->

		<div class="theme-info">
			<div class="screenshot"><?php the_post_thumbnail( '798' ); ?></div>

			<div class="theme-description entry-summary" itemprop="description"><?php the_content(); ?></div>

			<div class="theme-tags">
				<?php the_tags( '<h4>' . __( 'Tags:' ) . '</h4>' ); ?>
			</div><!-- .theme-tags -->

			<div class="theme-downloads">
				<h4><?php _e( 'Downloads', 'wporg-themes' ); ?></h4>

				<div id="theme-download-stats-<?php echo esc_attr( $slug ); ?>" class="chart"></div>
				<script type="text/javascript">
					google.load("visualization", "1", {packages:["corechart"]});
					google.setOnLoadCallback(drawThemeDownloadsChart);

					function drawThemeDownloadsChart() {
						jQuery(document).ready(function($){
							$.getJSON('https://api.wordpress.org/stats/themes/1.0/downloads.php?slug=<?php echo $slug; ?>&limit=365&callback=?', function (downloads) {
								var data = new google.visualization.DataTable(),
									count = 0;

								data.addColumn('string', _wpThemeSettings.l10n.date);
								data.addColumn('number', _wpThemeSettings.l10n.downloads);

								$.each(downloads, function (key, value) {
									data.addRow();
									data.setValue(count, 0, new Date(key).toLocaleDateString() );
									data.setValue(count, 1, Number(value));
									count++;
								});

								new google.visualization.ColumnChart(document.getElementById('theme-download-stats-<?php echo esc_attr( $slug ); ?>')).draw(data, {
									colors: ['#253578'],
									legend: {
										position: 'none'
									},
									titlePosition: 'in',
									axisTitlesPosition: 'in',
									chartArea: {
										height: 280,
										left: 35,
										width: '98%'
									},
									hAxis: {
										textStyle: {color: 'black', fontSize: 9}
									},
									vAxis: {
										format: '###,###',
										textPosition: 'out',
										viewWindowMode: 'explicit',
										viewWindow: {min: 0}
									},
									bar: {
										groupWidth: ( data.getNumberOfRows() > 100 ) ? "100%" : null
									},
									height: 350
								});
							});
						});
					}
				</script>
				<p class="total-downloads"><?php printf( __( 'Total downloads: %s' ), '<strong>' . $theme->downloaded . '</strong>' ); ?></p>
			</div><!-- .theme-downloads -->
		</div><!-- .theme-info -->

		<div class="theme-meta">
			<div class="theme-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
				<meta itemprop="ratingValue" content="<?php echo esc_attr( number_format_i18n( $theme->rating / 20, 1 ) ); ?>"/>
				<meta itemprop="ratingCount" content="<?php echo esc_attr( $theme->num_ratings ); ?>"/>
				<h4><?php _e( 'Ratings', 'wporg-themes' ); ?></h4>

				<div class="rating">
					<div class="star-holder">
						<div class="star-rating" style="width: <?php echo esc_attr( number_format_i18n( $theme->rating, 1 ) ); ?>%">
							<?php printf( __( '%d stars', 'wporg-themes' ), number_format_i18n( $theme->rating / 20 ) ); ?>
						</div>
					</div>
					<p class="description"><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), number_format_i18n( $theme->rating / 20, 1 ) ); ?></p>
				</div>

				<?php if ( ! empty( $theme->ratings ) && ! empty( $theme->num_ratings ) ) : ?>
				<ul>
					<?php
						foreach ( $theme->ratings as $key => $rate_count ) :
							// Hack to have descending key/value pairs.
							$key        = 6 - $key;
							$rate_count = $theme->ratings[ $key ];
					?>
					<li class="counter-container">
						<a href="//wordpress.org/support/view/theme-reviews/<?php echo esc_attr( $slug ); ?>?filter=<?php echo $key; ?>" title="<?php printf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $key, 'wporg-themes' ), $key ); ?>">
							<span class="counter-label"><?php printf( __( '%d stars', 'wporg-themes' ), $key ); ?></span>
							<span class="counter-back">
								<span class="counter-bar" style="width: <?php echo 100 * ( $rate_count / $theme->num_ratings ); ?>px;"></span>
							</span>
							<span class="counter-count"><?php echo $rate_count; ?></span>
						</a>
					</li>
					<?php endforeach; ?>
				</ul>
				<?php endif; ?>
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
							<?php _e( 'Development Log', 'wporg' ); ?>
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
	<div class="theme-footer">
		<a class="index-link" rel="home" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php _e( 'Return to Themes List', 'wporg-themes' ); ?></a>
		<?php the_post_navigation( array(
			'prev_text' => '<span class="screen-reader-text">' . __( 'Next', 'wporg-themes' ) . '</span>',
			'next_text' => '<span class="screen-reader-text">' . __( 'Previous', 'wporg-themes' ) . '</span>',
		) ); ?>
	</div>
</div>

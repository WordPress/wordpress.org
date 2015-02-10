<?php
	global $theme;
	$theme = wporg_themes_photon_screen_shot( $theme );
?>
<div class="theme-wrap">
	<div class="theme-about">

		<?php if ( strtotime( '-2 years' ) > strtotime( $theme->last_updated ) ) : ?>
			<div class="theme-notice notice notice-warning">
				<p><?php _e( 'This theme <strong>hasn&#146;t been updated in over 2 years</strong>. It may no longer be maintained or supported and may have compatibility issues when used with more recent versions of WordPress.', 'wporg-themes' ); ?></p>
			</div><!-- .theme-info -->
		<?php endif; ?>

		<div class="theme-screenshots">
			<div class="screenshot"><?php echo esc_url( $theme->screenshot_url . '?w=732&strip=all' ); ?></div>

			<div class="theme-actions">
				<a href="<?php echo esc_url( '//downloads.wordpress.org/theme/' . $theme->slug . '.' . $theme->version . '.zip' ); ?>" class="button button-primary"><?php _e( 'Download' ); ?></a>
				<a href="<?php echo esc_url( $theme->preview_url ); ?>" class="button button-secondary"><?php _e( 'Preview' ); ?></a>
			</div>
		</div><!-- .theme-screenshots -->

		<div class="theme-info">
			<div class="hentry">
				<h3 class="theme-name entry-title"><?php the_title(); ?></h3>
				<span class="theme-version">
					<?php
						printf( __( 'Version: %s' ),
							sprintf( '<abbr title="%1$s">%2$s</abbr>',
								esc_attr( sprintf( __( 'Last updated: %s' ), date_i18n( get_option( 'date_format' ), strtotime( $theme->last_updated ) ) ) ),
								$theme->version
							)
						);
					?>
				</span>
				<h4 class="theme-author"><?php printf( __( 'By %s' ), '<span class="author">' . $theme->author . '</span>' ); ?></h4>

				<div class="theme-description entry-summary"><?php the_content(); ?></div>

				<?php if ( ! empty( $theme->parent ) ) : ?>
				<div class="theme-notice notice notice-info">
					<p class="parent-theme"><?php printf( __( 'This is a child theme of %s.' ), sprintf( '<a href="/%1$s">%2$s</a>', $theme->parent->slug, $theme->parent->name ) ); ?></p>
				</div>
				<?php endif; ?>
			</div><!-- .theme-info -->

			<div class="theme-ratings" itemprop="aggregateRating" itemscope itemtype="http://schema.org/AggregateRating">
				<meta itemprop="ratingValue" content="<?php echo esc_attr( number_format_i18n( $theme->rating / 20, 1 ) ); ?>"/>
				<meta itemprop="ratingCount" content="<?php echo esc_attr( $theme->num_ratings ); ?>"/>
				<h4><?php _e( 'Ratings', 'wporg-themes' ); ?></h4>

				<div class="star-holder">
					<div class="star-rating" style="width: <?php echo esc_attr( number_format_i18n( $theme->rating, 1 ) ); ?>%">
						<?php printf( __( '%d stars', 'wporg-themes' ), number_format_i18n( $theme->rating / 20 ) ); ?>
					</div>
				</div>
				<span><?php printf( __( '%s out of 5 stars.', 'wporg-themes' ), number_format_i18n( $theme->rating / 20, 1 ) ); ?></span>

				<?php
					if ( ! empty( $theme->ratings ) && ! empty( $theme->num_ratings ) ) :
						foreach ( $theme->ratings as $key => $rate_count ) :
				?>
				<div class="counter-container">
					<a href="//wordpress.org/support/view/theme-reviews/<?php echo esc_attr( $theme->slug ); ?>?filter=<?php echo $key; ?>" title="<?php printf( _n( 'Click to see reviews that provided a rating of %d star', 'Click to see reviews that provided a rating of %d stars', $key, 'wporg-themes' ), $key ); ?>">
						<span class="counter-label"><?php printf( __( '%d stars', 'wporg-themes' ), $key ); ?></span>
						<span class="counter-back">
							<span class="counter-bar" style="width: <?php echo 92 * ( $rate_count / $theme->num_ratings ); ?>px;"></span>
						</span>
						<span class="counter-count"><?php echo $rate_count; ?></span>
					</a>
				</div>
				<?php
						endforeach;
					endif;
				?>
			</div><!-- .theme-rating -->

			<div class="theme-devs">
				<h4><?php _e( 'Development', 'wporg-themes' ); ?></h4>
				<h5><?php _e( 'Subscribe', 'wporg-themes' ); ?></h5>
				<ul class="unmarked-list">
					<li>
						<a href="//themes.trac.wordpress.org/log/<?php echo esc_attr( $theme->slug ); ?>?limit=100&mode=stop_on_copy&format=rss">
							<img src="//s.w.org/style/images/feedicon.png" style="vertical-align:text-top;"/>
							<?php _e( 'Development Log', 'wporg' ); ?>
						</a>
					</li>
				</ul>

				<h5><?php _e( 'Browse the Code', 'wporg-themes' ); ?></h5>
				<ul class="unmarked-list">
					<li><a href="//themes.trac.wordpress.org/log/<?php echo esc_attr( $theme->slug ); ?>/" rel="nofollow"><?php _e( 'Development Log', 'wporg-themes' ); ?></a></li>
					<li><a href="//themes.svn.wordpress.org/<?php echo esc_attr( $theme->slug ); ?>/" rel="nofollow"><?php _e( 'Subversion Repository', 'wporg-themes' ); ?></a></li>
					<li><a href="//themes.trac.wordpress.org/browser/<?php echo esc_attr( $theme->slug ); ?>/" rel="nofollow"><?php _e( 'Browse in Trac', 'wporg-themes' ); ?></a></li>
				</ul>
			</div><!-- .theme-devs -->

			<div class="theme-downloads">
				<h4><?php _e( 'Downloads', 'wporg-themes' ); ?></h4>

				<div id="theme-download-stats-<?php echo esc_attr( $theme->slug ); ?>" class="chart"></div>
				<script type="text/javascript">
					google.load("visualization", "1", {packages:["corechart"]});
					google.setOnLoadCallback(drawThemeDownloadsChart);

					function drawThemeDownloadsChart() {
						jQuery(document).ready(function($){
							jQuery.getJSON('https://api.wordpress.org/stats/themes/1.0/downloads.php?slug=<?php echo $theme->slug; ?>&limit=730&callback=?', function (downloads) {
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

								new google.visualization.ColumnChart(document.getElementById('theme-download-stats-<?php echo esc_attr( $theme->slug ); ?>')).draw(data, {
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
				<p class="total-downloads"><?php printf( __( 'Total downloads: %s' ), '<strong>' . number_format_i18n( $theme->downloaded ) . '</strong>' ); ?></p>
			</div><!-- .theme-downloads -->

			<div class="theme-tags">
				<h4><?php _e( 'Tags:' ); ?></h4>
				<?php
					foreach( $theme->tags as &$tag ) :
						$tag = sprintf( '<a href="%1$s">%2$s</a>', esc_url( home_url( "/tag/{$tag}/" ) ), $tag );
					endforeach;
					echo implode( ', ', $theme->tags );
				?>
			</div><!-- .theme-tags -->
		</div>
	</div>
</div>

<?php global $theme; ?>

<div class="theme-backdrop"></div>
<div class="theme-wrap">
	<div class="theme-header">
		<button class="close dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Close overlay' ); ?></span></button>
		<button class="left dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show previous theme' ); ?></span></button>
		<button class="right dashicons dashicons-no"><span class="screen-reader-text"><?php _e( 'Show next theme' ); ?></span></button>
	</div>
	<div class="theme-about">
		<div class="theme-screenshots">
			<div class="screenshot"><?php the_post_thumbnail(); ?></div>
		</div>

		<div class="theme-info hentry">
			<h3 class="theme-name entry-title"><?php the_title(); ?></h3>
			<span class="theme-version"><?php printf( __( 'Version: %s' ), $theme->version ); ?></span>
			<h4 class="theme-author"><?php printf( __( 'By %s' ), '<span class="author">' . $theme->author . '</span>' ); ?></h4>

			<p class="theme-description entry-summary"><?php the_content(); ?></p>

			<div class="rating rating-<?php echo round( $theme->rating, -1 ); ?>">
				<span class="one"></span>
				<span class="two"></span>
				<span class="three"></span>
				<span class="four"></span>
				<span class="five"></span>

				<p class="votes"><?php printf( __( 'Based on %s ratings.' ), $theme->num_ratings ); ?></p>
			</div>

			<div class="theme-stats">
				<div><strong><?php _e( 'Last updated:' ); ?></strong> <span class="updated"><?php echo $theme->last_updated; ?></span></div>
				<div><strong><?php _e( 'Downloads:' ); ?></strong> <?php echo $theme->downloaded; ?></div>
				<div><a href="<?php echo esc_url( $theme->homepage ); ?>"><?php _e( 'Theme Homepage &raquo;' ); ?></a></div>
			</div>

			<p class="theme-tags">
				<span><?php _e( 'Tags:' ); ?></span>
				<?php echo implode( ', ', $theme->tags ); ?>
			</p>
		</div>å
	</div>

	<div class="theme-actions">
		<a href="<?php echo esc_url( '//downloads.wordpress.org/theme/' . $theme->slug . '.' . $theme->version . '.zip' ); ?>" class="button button-primary"><?php _e( 'Download' ); ?></a>
		<a href="<?php echo esc_url( $theme->preview_url ); ?>" class="button button-secondary"><?php _e( 'Preview' ); ?></a>
	</div>
</div>
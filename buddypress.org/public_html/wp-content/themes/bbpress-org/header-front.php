<?php if ( is_front_page() ) : ?>
	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php bloginfo( 'description' ); ?></h2>
		<p>bbPress is forum software with a twist from the creators of WordPress. Easily setup discussion forums inside your WordPress.org powered site.</p>
		<div>
			<a href="//bbpress.org/download/" id="big-demo-button" class="button">Download bbPress &rarr;</a>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=5" alt="Screenshots">
		</div>
	</div></div>
	<hr class="hidden" />

	<div id="showcase"><div id="showcase-inner">
		<div class="feature">
			<h3><?php _e( 'Simple Setup', 'bbporg' ); ?></h3>
			<p><a href="//bbpress.org/about/simple/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_forums.gif" alt="" width="78" height="58"></a>Easy to setup.<br /> Easy to moderate.<br /> Fast, and clean.</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Fully Integrated', 'bbporg' ); ?></h3>
			<p><a href="//bbpress.org/about/integration/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_integration.gif" alt="" width="78" height="58"></a>One central account.<br /> One unified admin area.<br /> One click install.</p>
		</div>
		<div class="feature" style="margin:0;">
			<h3><?php _e( 'Single Installation', 'bbporg' ); ?></h3>
			<p><a href="//bbpress.org/about/installation/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_installation.gif" alt="" width="78" height="58"></a>Simple step-by-step installation walks you through your options.</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Multisite Forums', 'bbporg' ); ?></h3>
			<p><a href="//bbpress.org/about/multisite/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_blogs.gif" alt="" width="78" height="58"></a>Divide your site into sections. Allow your users to create content.</p>
		</div>
	</div></div>
	<hr class="hidden" />
<?php endif; ?>

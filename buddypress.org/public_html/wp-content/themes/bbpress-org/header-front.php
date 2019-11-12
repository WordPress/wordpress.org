<?php if ( is_front_page() ) : ?>
	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php _e( 'Discussion forums, for your WordPress.org powered site.', 'bbporg' ); ?></h2>
		<p><?php _e( 'bbPress is forum software from the creators of WordPress. Quickly setup a place for asyncronous discussion, subscriptions, and more!', 'bbporg' ); ?></p>
		<div>
			<a href="//bbpress.org/download/" id="big-demo-button" class="button"><?php _e( 'Download bbPress &rarr;', 'bbporg' ); ?></a>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=6" srcset="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=6 1x, <?php echo get_stylesheet_directory_uri(); ?>/images/screenshots-2x.png?v=6 2x" alt="">
		</div>
	</div></div>
	<hr class="hidden" />

	<div id="showcase"><div id="showcase-inner">
		<div class="feature">
			<h3><?php _e( 'Simple Setup', 'bbporg' ); ?></h3>
			<p>
				<a href="//bbpress.org/about/simple/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_forums.gif" alt="<?php esc_attr_e( 'Simple Setup', 'bbporg' ); ?>" width="78" height="58"></a>
				<?php _e( 'Easy to setup.<br /> Easy to moderate.<br /> Fast, and clean.', 'bbporg' ); ?>
			</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Fully Integrated', 'bbporg' ); ?></h3>
			<p>
				<a href="//bbpress.org/about/integration/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_integration.gif" alt="<?php esc_attr_e( 'Fully Integrated', 'bbporg' ); ?>" width="78" height="58"></a>
				<?php _e( 'One central account.<br /> One unified admin area.<br /> One click install.', 'bbporg' ); ?>
			</p>
		</div>
		<div class="feature" style="margin:0;">
			<h3><?php _e( 'Single Installation', 'bbporg' ); ?></h3>
			<p>
				<a href="//bbpress.org/about/installation/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_installation.gif" alt="<?php esc_attr_e( 'Single Installation', 'bbporg' ); ?>" width="78" height="58"></a>
				<?php _e( 'Simple step-by-step installation walks you through your options.', 'bbporg' ); ?>
			</p>
		</div>
		<div class="feature">
			<h3><?php _e( 'Multisite Forums', 'bbporg' ); ?></h3>
			<p>
				<a href="//bbpress.org/about/multisite/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_blogs.gif" alt="<?php esc_attr_e( 'Multisite Forums', 'bbporg' ); ?>" width="78" height="58"></a>
				<?php _e( 'Divide your site into sections. Allow your users to create content.', 'bbporg' ); ?>
			</p>
		</div>
	</div></div>
	<hr class="hidden" />
<?php endif; ?>

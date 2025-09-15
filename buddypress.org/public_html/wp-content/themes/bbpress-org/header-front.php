<?php if ( is_front_page() ) : ?>
	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php _e( 'Meet bbPress', 'bbporg' ); ?></h2>
		<p><?php _e( 'Forum software from the creators of WordPress. Asyncronous discussion, user profiles, subscriptions, and more!', 'bbporg' ); ?></p>
		<div>
			<a href="//bbpress.org/download/" id="big-demo-button" class="button"><?php _e( 'Download &rarr;', 'bbporg' ); ?></a>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=6" srcset="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=6 1x, <?php echo get_stylesheet_directory_uri(); ?>/images/screenshots-2x.png?v=6 2x" alt="">
		</div>
	</div></div>
	<hr class="hidden" />

	<div id="showcase"><div id="showcase-inner">
		<div class="feature">
			<h2><?php _e( 'Simple Setup', 'bbporg' ); ?></h2>
			<p>
				<a href="//bbpress.org/about/simple/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_forums.gif" alt="<?php esc_attr_e( 'Simple Setup', 'bbporg' ); ?>" width="156" height="58"></a>
				<span><?php _e( 'Easy to setup. <br>Easy to moderate. <br>Fast, and clean.', 'bbporg' ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php _e( 'Fully Integrated', 'bbporg' ); ?></h2>
			<p>
				<a href="//bbpress.org/about/integration/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_integration.gif" alt="<?php esc_attr_e( 'Fully Integrated', 'bbporg' ); ?>" width="156" height="116"></a>
				<span><?php _e( 'One central account. <br>One unified admin area. <br>One click install.', 'bbporg' ); ?></span>
			</p>
		</div>
		<div class="feature" style="margin:0;">
			<h2><?php _e( 'Single Installation', 'bbporg' ); ?></h2>
			<p>
				<a href="//bbpress.org/about/installation/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_installation.gif" alt="<?php esc_attr_e( 'Single Installation', 'bbporg' ); ?>" width="156" height="116"></a>
				<span><?php _e( 'Simple step-by-step <br>installation walks you <br>through your options.', 'bbporg' ); ?></span>
			</p>
		</div>
		<div class="feature">
			<h2><?php _e( 'Multisite Forums', 'bbporg' ); ?></h2>
			<p>
				<a href="//bbpress.org/about/multisite/"><img src="<?php echo get_stylesheet_directory_uri(); ?>/images/feature_blogs.gif" alt="<?php esc_attr_e( 'Multisite Forums', 'bbporg' ); ?>" width="156" height="116"></a>
				<span><?php _e( 'Divide your site<br> into sections. Allow your <br>users to create content.', 'bbporg' ); ?></span>
			</p>
		</div>
	</div></div>
	<hr class="hidden" />
<?php endif; ?>

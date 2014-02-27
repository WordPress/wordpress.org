<?php if ( 'buddypress.org' === parse_url( get_home_url(), PHP_URL_HOST ) && is_front_page() ) : ?>

	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php bloginfo( 'description' ); ?></h2>
		<p>BuddyPress is Social Networking, the WordPress way. Easily create a fully featured social network inside your WordPress.org powered site.</p>
		<div>
			<a href="<?php bloginfo( 'url' ); ?>/download/" id="big-demo-button" class="button">Download BuddyPress &rarr;</a>
			<img src="<?php echo get_stylesheet_directory_uri(); ?>/images/screenshots.png?v=2" alt="Screenshots">
		</div>
	</div></div>
	<hr class="hidden" />

<?php endif; ?>

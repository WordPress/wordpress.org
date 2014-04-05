<?php if ( is_front_page() ) : ?>
	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php bloginfo( 'description' ); ?></h2>
		<p>This software is really great. Go check it out for yourself and tell us how much you like it in the forums!</p>
		<div>
			<a href="<?php bloginfo( 'url' ); ?>/download/" id="big-demo-button" class="button">Download &rarr;</a>
			<img src="<?php bloginfo( 'template_url' ); ?>/images/screenshots.png?v=2" alt="Screenshots">
		</div>
	</div></div>
	<hr class="hidden" />

<?php endif;

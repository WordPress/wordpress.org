<?php if ( is_front_page() ) : ?>
	<div id="headline"><div id="headline-inner">
		<h2 class="graphic home"><?php bloginfo( 'description' ); ?></h2>
		<p>This software is really great. Go check it out for yourself and tell us how much you like it in the forums!</p>
		<div>
			<a href="<?php bloginfo( 'url' ); ?>/download/" id="big-demo-button" class="button">Download &rarr;</a>
			<img src="<?php bloginfo( 'template_url' ); ?>/images/screenshots.png?v=6" srcset="<?php bloginfo( 'template_url' ); ?>/images/screenshots.png?v=6 1x <?php bloginfo( 'template_url' ); ?>/images/screenshots-2x.png?v=6 2x" alt="Screenshots">
		</div>
	</div></div>
	<hr class="hidden" />

<?php endif;

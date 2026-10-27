<?php get_header(); ?>

<div id="headline">
	<div class="wrapper">
		<h2><?php esc_html_e( 'Page Not Found', 'rosetta' ); ?></h2>
	</div>
</div>

<div id="pagebody">
	<div class="wrapper">
		<div class="col-12" role="main">
			<p class="intro"><?php esc_html_e( 'The page you were looking for could not be found. I&#8217;m sorry, it&#8217;s not your fault&hellip; probably.', 'rosetta' ); ?></p>
		</div>
	</div>
</div>

<?php get_footer();

<!DOCTYPE html><html>

<?php get_template_part( 'header', 'head' ); ?>

<body id="top" <?php body_class(); ?>>

<?php get_template_part( 'header', 'accessibility' ); ?>

	<div id="header">
		<style type="text/css">
			@media screen and (max-width:768px) { .survey-wrapper { margin-bottom:20px; } #header-inner {height:82px} }
			.survey-wrapper { width: 100%; position: absolute; }
			.survey-div { font-family: 'Open Sans', Helvetica, Arial, 'Liberation Sans', sans-serif; font-weight: 600; display: block; margin: 0 auto; font-size: 13px; color: #fff; height: 24px; width: 245px; text-align:center !important; background-color: rgba(0, 0, 0, 0.8); border-radius: 0 0 4px 4px; }
			.survey-div a { color: #fff; font-weight: 500; display: block; }
			.survey-div a:hover { text-decoration: none; }
			#wpadminbar { top: 82px; }
			#main {  margin-top: 120px; }
			@media screen and (max-width:360px) { .survey-div { width: 100%; } }
		</style>
		<div class="survey-wrapper"><div class="survey-div">
			<a href="http://mercime.polldaddy.com/s/2016-buddypress-survey-site-builders-developers">Take the 2016 BuddyPress Survey &rarr;</a>
		</div></div>
		<div id="header-inner">
		<?php get_template_part( 'header', 'nav' ); ?>

		<h1><a href="<?php bloginfo( 'url' ); ?>"><?php bloginfo( 'name' ); ?></a></h1>
	</div></div>
	<hr class="hidden" />

<?php get_template_part( 'header', 'front' ); ?>

	<div id="main">
		<div class="content">

<?php codex_get_breadcrumb();

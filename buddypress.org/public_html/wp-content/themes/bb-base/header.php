<!DOCTYPE html><html>

<?php get_template_part( 'header', 'head' ); ?>

<body id="top" <?php body_class(); ?>>

<?php get_template_part( 'header', 'accessibility' ); ?>

	<div id="header"><div id="header-inner">
		<?php get_template_part( 'header', 'nav' ); ?>

		<h1><a href="<?php bloginfo( 'url' ); ?>"><?php bloginfo( 'name' ); ?></a></h1>
	</div></div>
	<hr class="hidden" />

<?php get_template_part( 'header', 'front'  ); ?>
<?php get_template_part( 'header', 'subnav' ); ?>

	<div id="main">
		<div class="content">

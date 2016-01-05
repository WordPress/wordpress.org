<?php
get_header();

the_post();
?>
	<div id="headline">
		<div class="wrapper">
			<h2><?php the_title(); ?></h2>
		</div>
	</div>
	<div id="pagebody">
		<div class="wrapper">
			<div class="col-9" role="main">
				<?php the_content(); ?>
			</div>
			<div class="col-3" role="complementary">
				<?php get_sidebar( 'page' ); ?>
			</div>
		</div>
	</div>
<?php
get_footer();

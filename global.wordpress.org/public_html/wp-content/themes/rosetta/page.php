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
			<div class="col-9">
				<?php the_content(); ?>
			</div>
			<div class="col-3">
				<?php get_template_part( 'download-sidebar' ); ?>
			</div>
		</div>
	</div>
<?php
get_footer();

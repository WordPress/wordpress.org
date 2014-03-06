<?php get_header(); ?>
	<div class="wrapper">
			<div class="section">
<?php
	while(have_posts()):
		the_post();
?>
				<div class="main">
				<h3><?php the_title(); ?></h3>

					<?php the_content(); ?>
				</div>

				<div class="sidebar">
<?php
	include 'download-sidebar.php';
?>
				</div>
			</div>
		</div>
<?php
	endwhile;
	get_footer();
?>

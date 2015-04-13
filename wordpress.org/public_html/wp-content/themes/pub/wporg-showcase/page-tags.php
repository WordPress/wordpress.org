<?php
/*
Template Name: Tags
*/

get_header();
?>

<div id="pagebody">
	<div class="wrapper">
		<?php get_sidebar( 'left' ); ?>
		<div class="col-7">
			<?php breadcrumb(); ?>
			<div class="heatmap">
				<?php wp_tag_cloud( 'smallest=9&largest=38&number=1000' ); ?>
			</div>
		</div>
		<?php get_sidebar( 'right' ); ?>
	</div>
</div>

<?php get_footer(); ?>
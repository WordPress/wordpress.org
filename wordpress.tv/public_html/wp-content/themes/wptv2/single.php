<?php
/**
 * Single Video Template
 */
global $wptv, $originalcontent;
get_header(); the_post();
?>
<div class="wptv-hero group">
	<div class="container">

		<?php get_template_part( 'breadcrumbs' ); ?>
		<h2 class="video-title"><?php the_title(); ?></h2>

		<div class="the-video">
			<?php $wptv->the_video(); ?>
		</div>

	</div><!-- .container -->
</div><!-- .wptv-hero -->

<div class="container">
	<div class="primary-content">
		<div id="content">
			<div id="comments">
				<?php comments_template(); ?>
			</div>
		</div><!-- #content -->
	</div><!-- .primary-content -->
	<?php get_sidebar( 'single' ); ?>
</div><!-- .container -->

<?php get_footer(); ?>

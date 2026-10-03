<?php
/**
 * Single Video Template
 *
 * @global WordPressTV_Theme $wptv
 * @global string            $originalcontent
 */

global $wptv, $originalcontent;

get_header();
the_post();
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
			<?php if ( get_the_excerpt() ) : ?>
				<h3>Description</h3>
				<div class="video-description"><?php the_excerpt(); ?></div>
			<?php endif; ?>
			<div id="comments">
				<?php comments_template(); ?>
			</div>
		</div><!-- #content -->
	</div><!-- .primary-content -->
	<?php get_sidebar( 'single' ); ?>
</div><!-- .container -->

<?php get_footer(); ?>

<?php
get_header();
?>
<section class="homewrap">

	<nav class="subhead">
		<?php wp_nav_menu( array( 'theme_location' => 'fp-below-header-menu' ) ); ?>
	</nav>

<div class="main">
	<section class="learn-main">
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
		<article>
			<?php the_title('<h1>','</h1>'); ?>
			<small><?php the_time('F jS, Y, g:ia'); ?> &mdash; <?php the_author(); ?></small>
			<?php the_content(); ?>
		</article>
<?php endwhile; endif; ?>
	</section>

</div>
</section><!-- /homewrap -->

<?php
get_footer();
?>

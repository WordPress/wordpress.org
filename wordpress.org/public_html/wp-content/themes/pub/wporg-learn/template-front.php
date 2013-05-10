<?php
/*
Template Name: Front Page
*/

get_header();
?>
<section class="homewrap">

	<header>
		<div class="wrap">
		<?php 
		$fronthead = new WP_Query('page_id=29');
		$fronthead->the_post();
		the_content();
		wp_reset_postdata();
		?>
		</div>
	</header>

	<nav class="subhead">
		<?php wp_nav_menu( array( 'theme_location' => 'fp-below-header-menu' ) ); ?>
	</nav>

	<section class="learn-main">
	<article>
	<?php 
		$fronthead = new WP_Query( array( 'posts_per_page' => 1 ) );
		$fronthead->the_post();
		?>
		<h1><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h1>
		<small><?php the_time('F jS, Y, g:ia'); ?> &mdash; <?php the_author(); ?></small>
		<?php the_excerpt(); ?>
		<?php
		wp_reset_postdata();
	?>
	</article>
	</section>

</section><!-- /homewrap -->

<?php
get_footer();
?>

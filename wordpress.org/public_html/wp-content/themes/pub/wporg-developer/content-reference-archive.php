<?php namespace DevHub;
/**
 * The template used for displaying reference content in archive.php & search.php
 *
 * @package wporg-developer
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<h1><a href="<?php the_permalink() ?>"><?php the_title(); ?></a></h1>

	<div class="description">
		<?php the_excerpt(); ?>
	</div>
	<div class="sourcefile">
		<p>Source: <?php echo get_source_file(); ?>:<?php echo get_line_number(); ?></p>
	</div>

</article>
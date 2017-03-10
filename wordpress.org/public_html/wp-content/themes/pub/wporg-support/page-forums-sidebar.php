<?php
/**
 * Template Name: Page with a Forums Sidebar
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>

<main id="main" class="site-main" role="main">

	<div class="entry-content">
		<?php bbp_breadcrumb(); ?>

		<?php while ( have_posts() ) : the_post(); ?>

			<header class="page-header">
				<h1 class="page-title"><?php the_title(); ?></h1>
			</header><!-- .page-header -->

			<?php the_content(); ?>

		<?php endwhile; ?>
	</div>

	<?php get_sidebar(); ?>
</main>

<?php get_footer(); ?>

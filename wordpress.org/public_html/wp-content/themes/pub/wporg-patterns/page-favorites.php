<?php
/**
 * The template for displaying My Patterns.
 *
 * @package WordPressdotorg\Pattern_Directory\Theme
 */

namespace WordPressdotorg\Pattern_Directory\Theme;

get_header();
?>

<main id="main" class="site-main" role="main">

	<header class="entry-header">
		<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
	</header><!-- .entry-header -->

	<div id="pattern-favorites__container"></div>

</main><!-- #main -->

<?php
get_footer();

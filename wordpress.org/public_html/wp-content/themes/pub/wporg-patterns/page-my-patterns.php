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
		<a href="<?php echo esc_url( home_url( '/new-pattern' ) ); ?>" class="button button-outline"><?php esc_html_e( 'Create a new pattern', 'wporg-patterns' ); ?></a>
	</header><!-- .entry-header -->

	<div id="my-patterns__container"></div>

</main><!-- #main -->

<?php
get_footer();

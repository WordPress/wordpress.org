<?php
/**
 * The main template file.
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Openverse\Theme
 */

namespace WordPressdotorg\Openverse\Theme;

get_header();
?>

	<main id="main" class="site-main" role="main">
		<iframe id="openverse_embed">
		    😢 Your browser does not support inline frames.
		</iframe>
	</main><!-- #main -->

    <script>
        // Transfer constants from PHP-land to JS-land.
        const openverseUrl = "<?php echo get_theme_mod('ov_src_url', OPENVERSE_URL) ?>";
        const openverseSubpath = "<?php echo OPENVERSE_SUBPATH ?>";
    </script>

<?php
get_footer();

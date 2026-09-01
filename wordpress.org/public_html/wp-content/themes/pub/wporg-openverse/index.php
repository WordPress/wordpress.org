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

/*
	If the theme mod `ov_is_redirect_enabled` is set to `true`, redirect to the
	standalone site and exit immediately. If not, print what would have been the
	redirect URL to the HTML as a comment.

	The target is validated before redirecting. Left to itself
	`wp_safe_redirect()` sends an unusable target to `admin_url()` instead, and
	a 301 into wp-admin would sit in visitors' caches long after the setting
	that caused it was corrected. Rendering the page is the safer failure.
 */

$is_redirect_enabled = get_theme_mod( 'ov_is_redirect_enabled' );
$target_url = get_target_url();

if ( $is_redirect_enabled && wp_validate_redirect( $target_url, false ) ) {
	wp_safe_redirect( $target_url, 301 );
	exit;
} else {
	echo '<!-- ' . esc_html( $target_url ) . ' -->';
}

get_header();
?>

	<main id="main" class="site-main" role="main">
		<iframe id="openverse_embed">
			😢 Your browser does not support inline frames.
		</iframe>
	</main><!-- #main -->

<?php
get_footer();

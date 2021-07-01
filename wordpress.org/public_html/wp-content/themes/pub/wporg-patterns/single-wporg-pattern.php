<?php
/**
 * The template for displaying all single posts.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WordPressdotorg\Pattern_Directory\Theme
 */

namespace WordPressdotorg\Pattern_Directory\Theme;
use function WordPressdotorg\Pattern_Directory\Theme\get_all_the_content;

get_header();

$user_has_reported = is_user_logged_in() ? user_has_flagged_pattern() : false;
$raw_block_content = get_the_content();
?>
	<input id="block-data" type="hidden" value="<?php echo rawurlencode( wp_json_encode( $raw_block_content ) ); ?>" />
	<main id="main" class="site-main col-12" role="main">

		<?php
		while ( have_posts() ) :
			the_post();
			?>

			<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
				<header class="entry-header">
					<?php the_title( '<h1 class="entry-title">', '</h1>' ); ?>
					<div class="pattern__categories">
						<?php
						$categories_list = get_the_term_list( get_the_ID(), 'wporg-pattern-category' );
						if ( $categories_list ) {
							echo $categories_list; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</div>
				</header><!-- .entry-header -->

				<div
					hidden
					class="pattern__container"
					data-post-id="<?php echo intval( get_the_ID() ); ?>"
					data-logged-in="<?php echo json_encode( is_user_logged_in() ); ?>"
					data-user-has-reported="<?php echo json_encode( $user_has_reported ); ?>"
				></div><!-- .pattern__container -->

			</article><!-- #post-## -->

		<?php endwhile; ?>

	</main><!-- #main -->

<?php
get_footer();

<?php
/**
 * The template for displaying all single posts.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 *
 * @package WordPressdotorg\Pattern_Directory\Theme
 */

namespace WordPressdotorg\Pattern_Directory\Theme;
use function WordPressdotorg\Pattern_Directory\Pattern_Post_Type\decode_pattern_content;

get_header();

while ( have_posts() ) :
	the_post();

	$user_has_reported = is_user_logged_in() ? user_has_flagged_pattern() : false;
	$raw_block_content = decode_pattern_content( get_the_content() );
	?>

	<input id="block-data" type="hidden" value="<?php echo rawurlencode( wp_json_encode( $raw_block_content ) ); ?>" />
	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<div
				hidden
				class="pattern__container"
				data-post-id="<?php echo intval( get_the_ID() ); ?>"
				data-user-has-reported="<?php echo json_encode( $user_has_reported ); ?>"
			></div><!-- .pattern__container -->

			<div class="entry-content hide-if-pattern-loaded">
				<?php the_content(); ?>

				<hr />

				<label for="pattern-code"><?php esc_html_e( 'Pattern Code', 'wporg-patterns' ); ?></label>
				<textarea id="pattern-code" class="pattern-code"><?php echo esc_attr( $raw_block_content ); ?></textarea>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

	<?php
endwhile;

get_footer();

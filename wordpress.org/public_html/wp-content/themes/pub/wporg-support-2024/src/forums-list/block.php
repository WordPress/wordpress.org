<?php
namespace WordPressdotorg\Theme\Support_2024\Forums_List;

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function init() {
	register_block_type(
		dirname( dirname( __DIR__ ) ) . '/build/forums-list',
		array(
			'render_callback' => __NAMESPACE__ . '\render',
		)
	);
}

/**
 * Render the block content.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the block markup.
 */
function render( $attributes, $content, $block ) {
	$wrapper_attributes = get_block_wrapper_attributes();

	$title_block = '<!-- wp:heading {"fontSize":"heading-5"} -->
		<h2 class="wp-block-heading has-heading-5-font-size">' . __( 'Forums', 'wporg' ) . '</h2>
		<!-- /wp:heading -->';

	$content = '<!-- wp:group {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"className":"bbp-forums is-style-cards-grid","layout":{"type":"grid","minimumColumnWidth":"32.3%"},"fontSize":"small"} -->
		<div class="bbp-forums wp-block-group is-style-cards-grid has-small-font-size">' . render_forum_cards() . '</div>
		<!-- /wp:group -->';

	return sprintf(
		'<section %s>%s %s</section>',
		$wrapper_attributes,
		do_blocks( $title_block ),
		do_blocks( $content )
	);
}

function render_forum_cards() {
	$forums_count = 0;

	ob_start();

	while ( bbp_forums() ) : bbp_the_forum();
		$forums_count++;
		bbp_get_template_part( 'loop', 'single-forum-homepage' );
	endwhile;

	// Calculate how many spare columns there are to fill at the end of a 3 column grid
	$columns_to_fill = 3 - ( $forums_count % 3 );

	echo do_blocks(
		sprintf(
			'<!-- wp:group {"className":"forums-homepage-themes-plugins span-%1$s"} -->
			<div class="wp-block-group forums-homepage-themes-plugins span-%1$s">

				<!-- wp:heading {"className":"has-normal-font-size"} -->
				<h2 class="wp-block-heading has-normal-font-size">%2$s</h2>
				<!-- /wp:heading -->

				<!-- wp:paragraph -->
				<p>%3$s</p>
				<!-- /wp:paragraph -->

			</div>
			<!-- /wp:group -->',
			esc_attr( $columns_to_fill ),
			__( 'Themes &amp; Plugins', 'wporg-forums' ),
			sprintf(
				/* translators: 1: Theme Directory URL, 2: Plugin Directory URL */
				__( 'Looking for help with a WordPress <a href="%1$s">theme</a> or <a href="%2$s">plugin</a>? Head to the theme or plugin\'s page and find the "View support forum" link to visit its specific forum.', 'wporg-forums' ),
				esc_url( __( 'https://wordpress.org/themes/', 'wporg-forums' ) ),
				esc_url( __( 'https://wordpress.org/plugins/', 'wporg-forums' ) ),
			),
		)
	);

	return ob_get_clean();
}

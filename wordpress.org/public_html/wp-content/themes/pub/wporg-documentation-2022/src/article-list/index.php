<?php
/**
 * Block Name: Article list
 * Description: Article list for a top-level category.
 *
 * @package wporg
 */

namespace WordPressdotorg\Theme\Documentation_2022\Article_List_Block;

defined( 'WPINC' ) || die();

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Register the block.
 */
function init() {
	register_block_type(
		dirname( __DIR__, 2 ) . '/build/article-list',
		array(
			'render_callback' => function ( $attributes, $content, $block ) {
				if ( is_category( 'default-themes' ) ) {
					return render_default_themes_block( $attributes, $content, $block );
				} else if ( is_category() ) {
					return render_category_block( $attributes, $content, $block );
				} else if ( ! empty( $attributes['category'] ) ) {
					return render_landing_block( $attributes, $content, $block );
				}

				return '';
			},
		)
	);
}

/**
 * Render the content for a topic landing page.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the block markup.
 */
function render_landing_block( $attributes, $content, $block ) {
	$markup = '';
	$term = get_term_by( 'slug', $attributes['category'], 'category' );
	if ( ! $term ) {
		return '';
	}

	$sections = get_terms(
		array(
			'hide_empty' => false,
			'taxonomy' => $term->taxonomy,
			'orderby' => 'name',
			'order' => 'asc',
			'parent' => $term->term_id,
		)
	);

	// Sort categories by term meta `sort_order`.
	// This can be set on terms with wp-cli, there's no UI (yet).
	// wp term meta add [term_id] sort_order [value]
	usort(
		$sections,
		function ( $a, $b ) {
			$a_order = (int) get_term_meta( $a->term_id, 'sort_order', true );
			$b_order = (int) get_term_meta( $b->term_id, 'sort_order', true );
			return $a_order <=> $b_order;
		}
	);

	foreach ( $sections as $section ) {
		$markup .= get_section_markup( $section );
	}

	$wrapper_attributes = get_block_wrapper_attributes();
	return sprintf(
		'<div %s>%s</div>',
		$wrapper_attributes,
		do_blocks( $markup ),
	);
}

/**
 * Get the markup for an article list by category.
 *
 * @param WP_Term $section The category object used to build this section.
 *
 * @return string The HTML and block markup to render.
 */
function get_section_markup( $section ) {
	$section_url = get_term_link( $section );
	ob_start();
	?>
<!-- wp:group {"align":"wide","style":{"spacing":{"margin":{"top":"var:preset|spacing|40","bottom":"var:preset|spacing|40"},"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default"}} -->
<div class="wp-block-group alignwide" style="margin-top:var(--wp--preset--spacing--40);margin-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:group {"layout":{"type":"default"}} -->
	<div class="wp-block-group">
		<!-- wp:heading {"className":"wp-block-heading","style":{"spacing":{"margin":{"top":"0","right":"0","bottom":"0","left":"0"}}}} -->
		<h2 class="wp-block-heading" style="margin-top:0;margin-right:0;margin-bottom:0;margin-left:0"><?php echo esc_html( $section->name ); ?></h2>
		<!-- /wp:heading -->
	</div>
	<!-- /wp:group -->

	<!-- wp:query {"queryId":0,"query":{"perPage":10,"pages":0,"offset":0,"postType":"helphub_article","order":"asc","orderBy":"title","author":"","search":"","exclude":[],"sticky":"","inherit":false,"parents":[],"taxQuery":{"category":[<?php echo esc_attr( $section->term_id ); ?>]}}} -->
	<div class="wp-block-query">
		<!-- wp:post-template {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}},"layout":{"type":"default","columnCount":2}} -->
			<!-- wp:post-title {"level":0,"isLink":true,"style":{"typography":{"fontStyle":"normal","fontWeight":"400"},"spacing":{"margin":{"top":"0","bottom":"0"}}},"textColor":"blueberry-1","fontSize":"normal","fontFamily":"inter"} /-->
		<!-- /wp:post-template -->
	</div>
	<!-- /wp:query -->

	<!-- wp:paragraph -->
	<p><a href="<?php echo esc_url( $section_url ); ?>">Show all articles in <?php echo esc_html( $section->name ); ?></a></p>
	<!-- /wp:paragraph -->
</div>
<!-- /wp:group -->
	<?php
	return ob_get_clean();
}

/**
 * Render the block content for "Default Themes" category.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the block markup.
 */
function render_default_themes_block( $attributes, $content, $block ) {
	global $post;
	if ( ! have_posts() ) {
		return '';
	}
	ob_start();
	?>
<!-- wp:list {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}}} -->
<ul class="wp-block-list">
	<?php
	while ( have_posts() ) :
		the_post();
		// Skip changelog posts, we'll add these manually.
		if ( str_ends_with( $post->post_name, 'changelog' ) ) {
			continue;
		}
		// Get the changelog for this article (theme), so it can be
		// displayed with the relevant theme.
		$found_articles = get_posts(
			array(
				'posts_per_page' => 1,
				'post_type' => 'helphub_article',
				'name' => $post->post_name . '-changelog',
			)
		);
		$changelog = false;
		if ( $found_articles ) {
			$changelog = $found_articles[0];
		}
		?>
		<!-- wp:list-item -->
		<li>
			<?php
			the_title(
				sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ),
				'</a>'
			);
			?>
			<?php if ( $changelog ) : ?>
			<!-- wp:list -->
			<ul class="wp-block-list">
				<!-- wp:list-item -->
				<li>
					<a href="<?php echo esc_url( get_permalink( $changelog ) ); ?>" rel="bookmark">
						<?php echo esc_html( get_the_title( $changelog ) ); ?>
					</a>
				</li>
				<!-- /wp:list-item -->
			</ul>
			<!-- /wp:list -->
			<?php endif; ?>
		</li>
		<!-- /wp:list-item -->
	<?php endwhile; ?>
</ul>
<!-- /wp:list -->

<!-- This query loop inherits the global query, same as the above PHP loop. This lets us use the pagination blocks. -->
<!-- wp:query {"queryId":1,"query":{"inherit":true},"align":"wide","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
<div class="wp-block-query alignwide" style="margin-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:query-pagination -->
		<!-- wp:query-pagination-previous {"label":"Newer Posts"} /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next {"label":"Older Posts"} /-->
	<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
	<?php
	$markup = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();
	return sprintf(
		'<div %s>%s</div>',
		$wrapper_attributes,
		do_blocks( $markup ),
	);
}

/**
 * Render the block content for all other categories.
 *
 * @param array    $attributes Block attributes.
 * @param string   $content    Block default content.
 * @param WP_Block $block      Block instance.
 *
 * @return string Returns the block markup.
 */
function render_category_block( $attributes, $content, $block ) {
	global $post;
	if ( ! have_posts() ) {
		return '';
	}
	ob_start();
	?>
<!-- wp:list {"style":{"spacing":{"blockGap":"var:preset|spacing|10"}}} -->
<ul class="wp-block-list">
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<!-- wp:list-item -->
		<li>
			<?php
			the_title(
				sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ),
				'</a>'
			);
			?>
		</li>
		<!-- /wp:list-item -->
	<?php endwhile; ?>
</ul>
<!-- /wp:list -->

<!-- This query loop inherits the global query, same as the above PHP loop. This lets us use the pagination blocks. -->
<!-- wp:query {"queryId":1,"query":{"inherit":true},"align":"wide","style":{"spacing":{"margin":{"bottom":"var:preset|spacing|40"}}}} -->
<div class="wp-block-query alignwide" style="margin-bottom:var(--wp--preset--spacing--40)">
	<!-- wp:query-pagination -->
		<!-- wp:query-pagination-previous {"label":"Newer Posts"} /-->
		<!-- wp:query-pagination-numbers /-->
		<!-- wp:query-pagination-next {"label":"Older Posts"} /-->
	<!-- /wp:query-pagination -->
</div>
<!-- /wp:query -->
	<?php
	$markup = ob_get_clean();

	$wrapper_attributes = get_block_wrapper_attributes();
	return sprintf(
		'<div %s>%s</div>',
		$wrapper_attributes,
		do_blocks( $markup ),
	);
}

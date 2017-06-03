<?php
/**
 * Custom template tags for this theme
 *
 * Eventually, some of the functionality here could be replaced by core features.
 *
 * @package CampSite_2017
 */

namespace WordCamp\CampSite_2017;

add_action( 'edit_category', __NAMESPACE__ . '\category_transient_flusher' );
add_action( 'save_post',     __NAMESPACE__ . '\category_transient_flusher' );

/**
 * Prints HTML with meta information for the current post-date/time and author.
 */
function posted_on() {
	?>

	<span class="posted-on">
		<?php echo esc_html_x( 'Posted on', 'post date', 'wordcamporg' ); ?>

		<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
			<?php if ( get_the_time( 'U' ) !== get_the_modified_time( 'U' ) ) : ?>

				<time class="entry-date published" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
					<?php echo esc_html( get_the_date() ); ?>
				</time>

				<time class="updated" datetime="<?php echo esc_attr( get_the_modified_date( 'c' ) ); ?>">
					<?php echo esc_html( get_the_modified_date() ); ?>
				</time>

			<?php else : ?>

				<time class="entry-date published updated" datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
					<?php echo esc_html( get_the_date() ); ?>
				</time>

			<?php endif; ?>
		</a>
	</span>

	<span class="byline">
		<?php echo esc_html_x( 'by', 'post author', 'wordcamporg' ); ?>

		<span class="author vcard">
			<a class="url fn n" href="<?php echo esc_url( get_author_posts_url( get_the_author_meta( 'ID' ) ) ); ?>">
				<?php echo esc_html( get_the_author() ); ?>
			</a>
		</span>
	</span>

	<?php
}

/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function entry_footer() {
	// Hide category and tag text for pages.
	if ( 'post' === get_post_type() ) {
		/* translators: used between list items, there is a space after the comma */
		$categories_list = get_the_category_list( esc_html__( ', ', 'wordcamporg' ) );
		/* translators: used between list items, there is a space after the comma */
		$tags_list = get_the_tag_list( '', esc_html__( ', ', 'wordcamporg' ) );

		if ( $categories_list && categorized_blog() ) {
			printf( '<span class="cat-links">' . esc_html__( 'Posted in %1$s', 'wordcamporg' ) . '</span>', wp_kses_data( $categories_list ) );
		}

		if ( $tags_list ) {
			printf( '<span class="tags-links">' . esc_html__( 'Tagged %1$s', 'wordcamporg' ) . '</span>', wp_kses_data( $tags_list ) );
		}
	}

	if ( ! is_single() && ! post_password_required() && ( comments_open() || get_comments_number() ) ) {
		echo '<span class="comments-link">';
		/* translators: %s: post title */
		comments_popup_link( sprintf(
			wp_kses(
				__( 'Leave a Comment<span class="screen-reader-text"> on %s</span>', 'wordcamporg' ),
				array( 'span' => array( 'class' => array() ) )
			),
			get_the_title()
		) );
		echo '</span>';
	}

	edit_post_link(
		sprintf(
			/* translators: %s: Name of current post */
			esc_html__( 'Edit %s', 'wordcamporg' ),
			the_title( '<span class="screen-reader-text">"', '"</span>', false )
		),
		'<span class="edit-link">',
		'</span>'
	);
}

/**
 * Returns true if a blog has more than 1 category.
 *
 * @return bool
 */
function categorized_blog() {
	$category_count = get_transient( __NAMESPACE__ . '\category_count' );

	if ( false === $category_count ) {
		$categories = get_categories( array(
			'fields'     => 'ids',
			'hide_empty' => 1,
			'number'     => 2,  // We only need to know if there is more than one category.
		) );

		$category_count = count( $categories );

		set_transient( __NAMESPACE__ . '\category_count', $category_count );
	}

	return $category_count > 1;
}

/**
 * Flush out the transients used in categorized_blog().
 */
function category_transient_flusher() {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	delete_transient( __NAMESPACE__ . '\categories' );
}

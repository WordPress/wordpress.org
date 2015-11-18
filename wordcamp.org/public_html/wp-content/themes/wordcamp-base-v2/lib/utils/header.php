<?php

function wcb_entry_meta() {
	twentyten_posted_on();
}

function __wcb_post_single_meta( $meta ) {
	if ( get_post_type() != 'post' || ! is_single() )
		return $meta;

	if ( is_object_in_taxonomy( get_post_type(), 'category' ) ) {
		$meta['category'] = sprintf( __('Posted in %s', 'wordcampbase'), get_the_category_list( ', ' ) );
	}
	if ( is_object_in_taxonomy( get_post_type(), 'tag' ) ) {
		$meta['tag'] = sprintf( __('Tagged %s', 'wordcampbase'), get_the_tag_list( '', ', ' ) );
	}

	$meta['order'][] = 'br';

	if ( isset( $meta['category'] ) && isset( $meta['tag'] ) )
		array_push( $meta['order'], 'category', 'sep', 'tag' );
	elseif ( isset( $meta['category'] ) )
		$meta['order'][] = 'category';
	elseif ( isset( $meta['tag'] ) )
		$meta['order'][] = 'tag';
	else
		array_pop( $meta['order'] );

	return $meta;
}
add_filter( 'wcb_entry_meta', '__wcb_post_single_meta' );

function wcb_site_title() {
	$heading_tag = ( is_home() || is_front_page() ) ? 'h1' : 'div';
	echo "<$heading_tag id='site-title'>"; ?>
		<span>
			<a href="<?php echo home_url( '/' ); ?>" title="<?php echo esc_attr( get_bloginfo( 'name', 'display' ) ); ?>" rel="home"><?php bloginfo( 'name' ); ?></a>
		</span>
	<?php echo "</$heading_tag>";
}

function wcb_header_image() {
	global $post;

	// Check if this is a post or page, if it has a thumbnail, and if it's a big one
	if ( is_singular()
	&& has_post_thumbnail( $post->ID )
	&& ( /* $src, $width, $height */ $image = wp_get_attachment_image_src( get_post_thumbnail_id( $post->ID ), 'post-thumbnail' ) )
	&& $image[1] >= HEADER_IMAGE_WIDTH ) :
		// Houston, we have a new header image!
		echo get_the_post_thumbnail( $post->ID );
	elseif ( get_header_image() ) : ?>
		<img src="<?php header_image(); ?>" width="<?php echo HEADER_IMAGE_WIDTH; ?>" height="<?php echo HEADER_IMAGE_HEIGHT; ?>" alt="" />
	<?php endif;
}

/**
 * Print the <title> tag based on what is being viewed.
 */
function wcb_title_tag() {
	global $page, $paged;
	echo "<title>";
	wp_title( '|', true, 'right' );

	// Add the blog name.
	bloginfo( 'name' );

	// Add the blog description for the home/front page.
	$site_description = get_bloginfo( 'description', 'display' );
	if ( $site_description && ( is_home() || is_front_page() ) )
		echo " | $site_description";

	// Add a page number if necessary:
	if ( $paged >= 2 || $page >= 2 )
		echo ' | ' . sprintf( __( 'Page %s', 'wordcampbase' ), max( $paged, $page ) );
	echo "</title>";
}

/**
 * Print the typekit script tags.
 */
function wcb_typekit_scripts() {
	$option = wcb_get_option( 'typekit' );
	$kit_id = apply_filters( 'wcb_typekit_id', $option );

	if ( empty( $kit_id ) )
		return;

	?>
	<script type="text/javascript" src="http://use.typekit.com/<?php echo esc_attr( $kit_id ); ?>.js"></script>
	<script type="text/javascript">try{Typekit.load();}catch(e){}</script>
	<?php
}

/**
 * Get the value for the <meta name="viewport"> tag
 *
 * @return string
 */
function wcb_get_viewport() {
	$viewport = 'width=device-width';

	if ( ! wcorg_skip_feature( 'wcb_viewport_initial_scale' ) ) {
		$viewport .= ', initial-scale=1';
	}

	return $viewport;
}

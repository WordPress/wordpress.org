<?php
add_filter('wp_get_attachment_image_attributes', 'wporg_relative_images');
function wporg_relative_images($attr) {
	$url = $attr['src'];
	$attr['src'] = set_url_scheme( $url );
	return $attr;
}
?>
<header class="masthead">
	<?php while ( have_posts() ) : the_post(); ?>
		<hgroup <?php post_class( 'wrap' ); ?>>
			<?php
				if ( has_post_thumbnail() ) {
					the_post_thumbnail( array( 400, 200 ) );
				}
				the_content();
			?>
		</hgroup>
	<?php endwhile; ?>
</header>

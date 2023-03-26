<?php
/**
 * Modified version of the embed template from wp-includes/embed-template.php
 *
 * (Replace original instead of using filters because the thumbnail image is not easily filterable)
 */

if ( ! headers_sent() ) {
	header( 'X-WP-embed: true' );
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<title><?php echo wp_get_document_title(); ?></title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<?php
	/**
	 * Print scripts or data in the embed template <head> tag.
	 *
	 * @since 4.4.0
	 */
	do_action( 'embed_head' );
	?>
</head>
<body <?php body_class(); ?>>
<?php
the_post();
// setup the theme variable
// note, $theme contains things like active installs and other data to be added eventually
$theme = wporg_themes_theme_information( $post->post_name );
?>
<div <?php post_class( 'wp-embed' ); ?>>
	<p class="wp-embed-heading">
		<a href="<?php the_permalink(); ?>" target="_top">
			<div class="wp-embed-featured-image rectangular">
				<img src='<?php echo $theme->screenshot_url; ?>' alt=''>
			</div>
			<?php echo esc_html( $theme->name ); ?>
		</a>
	</p>

	<div class="wp-embed-excerpt"><?php echo wp_trim_words( $theme->description, 55 ); ?></div>

	<?php
	/**
	 * Print additional content after the embed excerpt.
	 *
	 * @since 4.4.0
	 */
	do_action( 'embed_content' );
	?>

	<div class="wp-embed-footer">
		<div class="wp-embed-site-title">
			<?php
			$site_title = sprintf(
				'<a href="%s" target="_top"><img src="%s" srcset="%s 2x" width="32" height="32" alt="" class="wp-embed-site-icon"/><span>%s</span></a>',
				esc_url( home_url() ),
				esc_url( get_site_icon_url( 32, admin_url( 'images/w-logo-blue.png' ) ) ),
				esc_url( get_site_icon_url( 64, admin_url( 'images/w-logo-blue.png' ) ) ),
				esc_html( get_bloginfo( 'name' ) )
			);

			/**
			 * Filter the site title HTML in the embed footer.
			 *
			 * @since 4.4.0
			 *
			 * @param string $site_title The site title HTML.
			 */
			echo apply_filters( 'embed_site_title_html', $site_title );
			?>
		</div>

		<div class="wp-embed-meta">
			<?php
			/**
			 * Print additional meta content in the embed template.
			 *
			 * @since 4.4.0
			 */
			do_action( 'embed_content_meta');
			?>
		</div>
	</div>
</div>
<?php

/**
 * Print scripts or data before the closing body tag in the embed template.
 *
 * @since 4.4.0
 */
do_action( 'embed_footer' );
?>
</body>
</html>

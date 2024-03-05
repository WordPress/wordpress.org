<?php
/**
 * Modified version of the embed template from wp-includes/embed-template.php
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

use WordPressdotorg\Plugin_Directory\Template;

remove_action( 'embed_content_meta', 'print_embed_comments_button' );
wp_enqueue_style( 'dashicons' ); // NOTE: This is not compatible with the core embed CSS.

if ( ! headers_sent() ) {
	header( 'X-WP-embed: true' );
}

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
	<title><?php echo esc_html( wp_get_document_title() ); ?></title>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<?php
	/**
	 * Print scripts or data in the embed template <head> tag.
	 *
	 * @since 4.4.0
	 */
	do_action( 'embed_head' );
	?>
	<style>
		.dashicons, .dashicons-before:before {
			vertical-align: initial;
		}
		.wp-embed-featured-image {
			float: left;
			margin-right: 20px;
		}
		html[dir="rtl"] .wp-embed-featured-image {
			float: right;
			margin-left: 20px;
			margin-right: auto;
		}
		.wp-embed-featured-image .plugin-icon {
			background-size: 100%;
			height: 64px;
			width: 64px;
		}
		p.wp-embed-heading {
			margin: 0;
		}
		.wp-embed-heading .byline {
			font-weight: 400;
			text-transform: lowercase;
		}
		.wp-embed-heading .author {
			text-transform: none;
		}
		.wp-embed-heading span a {
			color: inherit;
		}
		.plugin-rating {
			line-height: 2.1;
		}
		.plugin-rating .wporg-ratings {
			display: inline-block;
			margin-right: 5px;
		}
		html[dir="rtl"] .plugin-rating .wporg-ratings {
			margin-left: 5px;
			margin-right: auto;
		}
		.plugin-rating .rating-count {
			color: #999;
			font-size: 12.8px;
			font-size: .8rem;
		}
		.wp-embed-excerpt {
			clear: both;
			color: #32373c;
		}
		.button.download-button {
			background: #3858e9;
			-webkit-box-sizing: border-box;
			-moz-box-sizing: border-box;
			box-sizing: border-box;
			color: #fff;
			cursor: pointer;
			display: inline-block;
			line-height: 2;
			margin: 0;
			padding: 8px 16px;
			padding: 0.5rem 1rem;
			text-decoration: none;
			white-space: nowrap;
			-webkit-appearance: none;
		}
		a.button:active, a.button:focus, a.button:hover {
			color: #fff;
			text-decoration: none;
		}
		.wp-embed-share-dialog-open .dashicons {
			color: #82878c;
			top: 6px;
		}
		.wp-embed-share-dialog-close .dashicons {
			color: #fff;
			padding-top: 8px;
		}
	</style>
</head>
<body <?php body_class(); ?>>
	<div <?php post_class( 'wp-embed' ); ?>>
		<div class="wp-embed-featured-image rectangular">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php echo Template::get_plugin_icon( $post, 'html' ); /* phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped */ ?>
			</a>
		</div>

		<p class="wp-embed-heading">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php the_title(); ?>
			</a>
			<span class="byline"><?php the_author_byline(); ?></span>

			<?php echo wp_kses_post( Template::get_star_rating() ); ?>
		</p>

		<div class="wp-embed-excerpt">
			<?php the_excerpt(); ?>
		</div>

		<?php
		/**
		 * Print additional content after the embed excerpt.
		 *
		 * @since 4.4.0
		 */
		do_action( 'embed_content' );
		?>

		<div class="wp-embed-footer">
			<?php
			/**
			 * Print additional meta content in the embed template.
			 *
			 * @since 4.4.0
			 */
			do_action( 'embed_content_meta' );

			$tested_up_to = (string) get_post_meta( get_the_ID(), 'tested', true );
			if ( $tested_up_to ) :
				?>
				<span class="tested-with">
					<i class="dashicons dashicons-wordpress-alt"></i>
					<?php
					/* translators: WordPress version. */
					printf( esc_html__( 'Tested with %s', 'wporg-plugins' ), esc_html( $tested_up_to ) );
					?>
				</span>
			<?php endif; ?>

			<div class="wp-embed-meta">
				<?php if ( 'publish' === get_post_status() ) : ?>
					<a class="plugin-download button download-button button-large" href="<?php echo esc_url( get_permalink() ); ?>"><?php esc_html_e( 'Get this plugin', 'wporg-plugins' ); ?></a>
				<?php endif; ?>
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

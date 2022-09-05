<?php
/**
 * Modified version of the embed template from wp-includes/embed-template.php
 *
 * @package wporg-developer
 */

remove_action( 'embed_content_meta', 'print_embed_comments_button' );

if ( ! headers_sent() ) {
	header( 'X-WP-embed: true' );
}

/**
 * Returns list of parameter => data type.
 *
 * @param int $post_id
 *
 * @return array
 */
function get_params( $post_id ) {
	$tags   = get_post_meta( $post_id, '_wp-parser_tags', true );
	$params = array();

	if ( $tags ) {
		foreach ( $tags as $tag ) {
			if ( is_array( $tag ) && 'param' == $tag['name'] ) {
				$params[ $tag['variable'] ] = implode( ' | ', $tag['types'] );
			}
		}
	}

	return $params;
}

/**
 * Returns a function string to display.
 *
 * @param int $post_id
 *
 * @return string
 */
function get_signature( $post_id ) {
	$title = get_the_title();
	$has_args = count( get_params( $post_id ) ) > 0;
	$post_type = get_post_type( $post_id );

	if ( 'wp-parser-hook' === $post_type ) {
		$hook_type = DevHub\get_hook_type_name( $post_id );
		$delimiter = false !== strpos( $title, '$' ) ? '"' : "'";

		if ( $has_args ) {
			return "{$hook_type}( <span class=\"wp-embed-hook\">{$delimiter}{$title}{$delimiter},</span> ... )";
		}
		return "{$hook_type}( <span class=\"wp-embed-hook\">{$delimiter}{$title}{$delimiter}</span> )";
	}

	if ( 'wp-parser-class' === $post_type  ) {
		return 'class ' . $title . ' {}';
	}

	if ( $has_args ) {
		return $title . '( ... )';
	}

	return $title . '()';
}

$embed_post_id         = get_the_ID();
$params                = get_params( $embed_post_id );
$embed_title           = get_signature( $embed_post_id );
$param_count           = count( $params );
$parameter_display_max = 4; // We truncate the display of params

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
		.wp-embed {
			color: #50575e;
		}

		code {
			background: #efefef;
			border-radius: 4px;
			padding: 2px 6px;
			font-weight: 400;
		}

		p.wp-embed-heading {
			font-weight: normal;
			font-size: 1.25rem;
			font-family:
				Hack, "Fira Code", Consolas, Menlo, Monaco, "Andale Mono",
				"Lucida Console", "Lucida Sans Typewriter", "DejaVu Sans Mono",
				"Bitstream Vera Sans Mono", "Liberation Mono", "Nimbus Mono L",
				"Courier New", Courier, monospace;
		}

		.wp-embed-parameters-title {
			margin: 16px 0 8px;
			font-size: 14px;
			font-weight: normal;
		}

		.wp-embed-parameters ul {
			margin: 0 0 0 8px;
			padding: 0;
			list-style: none;
		}

		.wp-embed-parameters ul li {
			padding: 4px;
		}

		.wp-embed-parameters code {
			margin-right: 8px;
		}

		.wp-embed-hook {
			color: #24831d;
		}

		.wp-embed-footer a {
			color: #135e96;
		}
	</style>

</head>
<body <?php body_class(); ?>>
	<div <?php post_class( 'wp-embed' ); ?>>

		<p class="wp-embed-heading">
			<a href="<?php the_permalink(); ?>" target="_top">
				<?php echo wp_kses_post( $embed_title ); ?>
			</a>
		</p>

		<div class="wp-embed-excerpt">
			<?php the_excerpt(); ?>

			<?php if ( $params ) : ?>
				<div class="wp-embed-parameters">
					<h6 class="wp-embed-parameters-title"><?php echo esc_html__( 'Parameters:', 'wporg' ); ?></h6>
					<ul>
						<?php
						for ( $i = 0; $i < min( $param_count, $parameter_display_max ); $i++ ) {
							$key = array_keys( $params )[ $i ];
							?>
							<li>
								<code><?php echo esc_html( $key ); ?></code>
								<span class="wp-embed-parameters-type"><?php echo esc_html( $params[ $key ] ); ?></span>
							</li>
						<?php } ?>

						<?php
						if ( ! empty( $params ) && $param_count > $parameter_display_max ) {
							/* translators: %d number of non printed parameter */
							echo '<li>' . sprintf( '... %s more', esc_attr( $param_count - $parameter_display_max ) ) . '</li>';
						}
						?>
					</ul>
				</div>
			<?php endif; ?>

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
			<?php the_embed_site_title(); ?>

			<div class="wp-embed-meta">
				<?php
				/**
				 * Prints additional meta content in the embed template.
				 *
				 * @since 4.4.0
				 */
				do_action( 'embed_content_meta' );
				?>
			</div>

			<?php
				/**
				 * Print scripts or data before the closing body tag in the embed template.
				 *
				 * @since 4.4.0
				 */
				do_action( 'embed_footer' );
			?>
		</div>
	</div>
</body>
</html>

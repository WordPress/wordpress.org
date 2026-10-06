<?php
/**
 * Outputs the opening HTML document for a page, including the head, the rendered site header, and the wp-site-blocks wrapper.
 *
 * @package wporg-translate-events-2024
 */

namespace Wporg\TranslationEvents\Theme_2024;

/**
 * Template inputs.
 *
 * @var string $site_header The rendered site header markup.
 * @var array  $attributes  Attributes passed through to the page header.
 */

$html_title = implode( ' | ', array( $attributes['title'], __( 'Translation Events', 'wporg-translate-events-2024' ) ) );

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="<?php bloginfo( 'charset' ); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1" />
		<title><?php echo esc_html( $html_title ); ?></title>
		<?php wp_head(); ?>
	</head>
	<body <?php body_class(); ?>>
		<?php wp_body_open(); ?>
		<div class="wp-site-blocks">
			<?php // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $site_header; ?>

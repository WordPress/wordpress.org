<?php
/**
 * The Header template for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Openverse\Theme
 */

namespace WordPressdotorg\Openverse\Theme;

$GLOBALS['pagetitle'] = wp_get_document_title();
global $wporg_global_header_options;
if ( ! isset( $wporg_global_header_options['in_wrapper'] ) ) {
	$wporg_global_header_options['in_wrapper'] = '';
}
$wporg_global_header_options['in_wrapper'] .= '<a class="skip-link screen-reader-text" href="#content">' . esc_html__( 'Skip to content', 'wporg' ) . '</a>';

get_template_part( 'header', 'wporg' );
?>

<script>
	// used in `message.js`
	const currentLocale = '<?php echo get_locale() ?>';
</script>

<div id="page" class="site">
	<div id="content" class="site-content">

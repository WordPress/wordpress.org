<?php
namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Urls;

ob_start();

$host_event_url = 'https://make.wordpress.org/polyglots/2024/05/29/translation-events-inviting-gtes-to-create-and-manage-events/';
if ( current_user_can( 'create_translation_event' ) ) {
	$host_event_url = Urls::event_create();
}
?>
<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
<h4 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700"><?php esc_html_e( 'Get involved', 'wporg-translate-events-2024' ); ?></h4>
<!-- /wp:heading -->
<!-- wp:paragraph -->
<p><?php esc_html_e( 'Start your journey to contribute to localization.', 'wporg-translate-events-2024' ); ?></p>
<!-- /wp:paragraph -->
<div class="wp-block-columns alignwide" style="gap:10px;"><!-- wp:column {"width":"100%","fontSize":"medium","fontFamily":"inter"} -->
<div class="wp-block-column has-inter-font-family" style="flex-basis:100%"><!-- wp:group {"style":{"border":{"radius":"2px","width":"1px"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30"}}},"borderColor":"light-grey-1","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;border-radius:2px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"style":{"elements":{"link":{"color":{"text":"var:preset|color|charcoal-0"}}}},"textColor":"charcoal-0"} -->
<a class="blue-bolded-link" href="<?php echo esc_url( $host_event_url ); ?>"><?php esc_html_e( 'Host an event', 'wporg-translate-events-2024' ); ?></a>
<p class="has-charcoal-0-color has-text-color has-link-color "><?php esc_html_e( 'Create your own translation event.', 'wporg-translate-events-2024' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column -->

<!-- wp:column {"width":"100%","fontSize":"medium","fontFamily":"inter"} -->
<div class="wp-block-column has-inter-font-family" style="flex-basis:100%"><!-- wp:group {"style":{"border":{"radius":"2px","width":"1px"},"spacing":{"padding":{"top":"var:preset|spacing|20","bottom":"var:preset|spacing|20","left":"var:preset|spacing|30"}}},"borderColor":"light-grey-1","layout":{"type":"constrained"}} -->
<div class="wp-block-group has-border-color has-light-grey-1-border-color" style="border-width:1px;border-radius:2px;padding-top:var(--wp--preset--spacing--20);padding-bottom:var(--wp--preset--spacing--20);padding-left:var(--wp--preset--spacing--30)"><!-- wp:paragraph {"style":{"layout":{"selfStretch":"fit","flexSize":null},"elements":{"link":{"color":{"text":"var:preset|color|charcoal-0"}}}},"textColor":"charcoal-0","fontSize":"heading-6"} -->
<a class="wp-block-heading has-inter-font-family blue-bolded-link" href="<?php echo esc_url( Urls::my_events() ); ?>"><?php esc_html_e( 'My events', 'wporg-translate-events-2024' ); ?></a>
<p class="has-charcoal-0-color has-text-color has-link-color"><?php esc_html_e( 'Manage events you’ve created, or pledged to attend.', 'wporg-translate-events-2024' ); ?></p>
<!-- /wp:paragraph --></div>
<!-- /wp:group --></div>
<!-- /wp:column --></div>

<?php
$content = ob_get_clean();

register_block_pattern(
	'wporg-translate-events-2024/front-cover',
	array(
		'title'      => __( 'Events Home Cover', 'wporg-translate-events-2024' ),
		'categories' => array( 'featured' ),
		'content'    => $content,
	)
);

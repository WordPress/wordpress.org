<?php
/**
 * Template for event form.
 */
namespace Wporg\TranslationEvents\Templates;

use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event_Text_Snippet;
use Wporg\TranslationEvents\Templates;
use Wporg\TranslationEvents\Urls;

/** @var Event $event */

$page_title = __( 'Create Event', 'gp-translation-events' );

Templates::header(
	array(
		'html_title'  => __( 'Translation Events', 'gp-translation-events' ) . ' - ' . esc_html( $page_title ),
		'page_title'  => $page_title,
		'breadcrumbs' => array( esc_html( $page_title ) ),
	),
);
?>

<div class="event-page-wrapper">
	<?php $is_create_form = true; ?>
	<?php Templates::part( 'event-form', compact( 'is_create_form', 'event' ) ); ?>
</div>

<?php Templates::footer(); ?>

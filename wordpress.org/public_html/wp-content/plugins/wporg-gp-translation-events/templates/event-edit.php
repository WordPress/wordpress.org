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

$page_title = __( 'Edit Event', 'gp-translation-events' );

Templates::header(
	array(
		'html_title'  => __( 'Translation Events', 'gp-translation-events' ) . ' - ' . esc_html( $page_title . ' - ' . $event->title() ),
		'page_title'  => $page_title,
		'breadcrumbs' => array( esc_html( $page_title ) ),
	),
);
?>

<div class="event-page-wrapper">
	<?php $is_create_form = false; ?>
	<?php Templates::part( 'event-form', compact( 'is_create_form', 'event' ) ); ?>
</div>

<div class="event-edit-right">
	<?php if ( current_user_can( 'edit_translation_event_attendees', $event->id() ) ) : ?>
		<a class="manage-attendees-btn button is-primary" href="<?php echo esc_url( Urls::event_attendees( $event->id() ) ); ?>"><?php esc_html_e( 'Manage Attendees', 'gp-translation-events' ); ?></a>
	<?php endif; ?>
</div>

<?php Templates::footer(); ?>

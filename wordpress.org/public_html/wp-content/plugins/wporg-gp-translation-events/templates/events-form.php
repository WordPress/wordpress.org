<?php
/**
 * Template for event form.
 */
namespace Wporg\TranslationEvents\Templates;

use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event_Text_Snippet;
use Wporg\TranslationEvents\Templates;
use Wporg\TranslationEvents\Urls;

/** @var bool $is_create_form */
/** @var Event $event */

$page_title = $is_create_form ? 'Create Event' : 'Edit Event';

Templates::header(
	array(
		'html_title'  => __( 'Translation Events' ) . ' - ' . esc_html( $page_title . ' - ' . $event->title() ),
		'page_title'  => $page_title,
		'breadcrumbs' => array( esc_html( $page_title ) ),
	),
);
?>

<div class="event-page-wrapper">
<form class="translation-event-form" action="" method="post">
	<?php wp_nonce_field( '_event_nonce', '_event_nonce' ); ?>
	<?php if ( $is_create_form ) : ?>
		<details id="quick-add"><summary><?php esc_html_e( 'Upcoming WordCamps', 'gp-translation-events' ); ?></summary><div class="loading"></div></details>
	<?php endif; ?>
	<input type="hidden" name="action" value="submit_event_ajax">
	<?php $event_form_name = $is_create_form ? 'create_event' : 'edit_event'; ?>
	<input type="hidden" id="form-name" name="form_name" value="<?php echo esc_attr( $event_form_name ); ?>">
	<input type="hidden" id="event-id" name="event_id" value="<?php echo esc_attr( $event->id() ); ?>">
	<input type="hidden" id="event-form-action" name="event_form_action">
	<div>
		<label for="event-title"><?php esc_html_e( 'Event Title', 'gp-translation-events' ); ?></label>
		<input type="text" id="event-title" name="event_title" value="<?php echo esc_html( $event->title() ); ?>" <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_title', $event->id() ) ?: 'readonly' ); ?> required size="42">
	</div>
	<?php $event_url_class = $is_create_form ? 'hide-event-url' : ''; ?>
	<?php $event_url = $is_create_form ? '' : Urls::event_details_absolute( $event->id() ); ?>
	<div id="event-url" class="<?php echo esc_attr( $event_url_class ); ?>">
		<label for="event-permalink"><?php esc_html_e( 'Event URL', 'gp-translation-events' ); ?></label>
		<a id="event-permalink" class="event-permalink" href="<?php echo esc_url( $event_url ); ?>" target="_blank"><?php echo esc_url( $event_url ); ?></a>
	</div>
	<div>
		<label for="event-description"><?php esc_html_e( 'Event Description', 'gp-translation-events' ); ?></label>
		<textarea id="event-description" name="event_description" rows="4" cols="40" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_description', $event->id() ) ?: 'readonly' ); ?>><?php echo esc_html( $event->description() ); ?></textarea>
		<?php
		echo wp_kses(
			Event_Text_Snippet::get_snippet_links(),
			array(
				'a'  => array(
					'href'         => array(),
					'data-snippet' => array(),
					'class'        => array(),
				),
				'ul' => array( 'class' => array() ),
				'li' => array(),
			)
		);
		?>
			<div>
		<label for="event-start"><?php esc_html_e( 'Start Date', 'gp-translation-events' ); ?></label>
		<input type="datetime-local" id="event-start" name="event_start" value="<?php echo esc_attr( $event->start()->format( 'Y-m-d H:i' ) ); ?>" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_start', $event->id() ) ?: 'readonly' ); ?> >
	</div>
	<div>
		<label for="event-end"><?php esc_html_e( 'End Date', 'gp-translation-events' ); ?></label>
		<input type="datetime-local" id="event-end" name="event_end" value="<?php echo esc_attr( $event->end()->format( 'Y-m-d H:i' ) ); ?>" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_end', $event->id() ) ?: 'readonly' ); ?>>
	</div>
	<div>
		<label for="event-timezone"><?php esc_html_e( 'Event Timezone', 'gp-translation-events' ); ?></label>
		<select id="event-timezone" name="event_timezone" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_timezone', $event->id() ) ?: 'disabled' ); ?> >
			<?php
			echo wp_kses(
				wp_timezone_choice( $is_create_form ? null : $event->timezone()->getName(), get_user_locale() ),
				array(
					'optgroup' => array( 'label' => array() ),
					'option'   => array(
						'value'    => array(),
						'selected' => array(),
					),
				)
			);
			?>
		</select>
	</div>
	<div class="submit-btn-group">
		<label for="event-status"></label>
	<?php if ( $event->id() ) : ?>
		<?php if ( $event->is_draft() ) : ?>
			<button class="button is-primary save-draft submit-event" type="submit" data-event-status="draft">Update Draft</button>
		<?php endif; ?>
	<button class="button is-primary submit-event" type="submit"  data-event-status="publish">
		<?php echo ( $event->is_published() ) ? esc_html( 'Update Event' ) : esc_html( 'Publish Event' ); ?>
	</button>
	<?php else : ?>
		<button class="button is-primary save-draft submit-event" type="submit" data-event-status="draft">Save Draft</button>
		<button class="button is-primary submit-event" type="submit"  data-event-status="publish">Publish Event</button>
	<?php endif; ?>
	<?php $visibility_trash_button = current_user_can( 'trash_translation_event', $event->id() ) ? 'inline-flex' : 'none'; ?>
	<button id="trash-button" class="button is-destructive trash-event" type="submit" name="submit" value="Delete" style="display: <?php echo esc_attr( $visibility_trash_button ); ?>">Delete Event</button>
	</div>
	<div class="clear"></div>
	<div class="published-update-text">
		<?php
		$visibility_published_button = 'none';
		if ( $event->is_published() ) {
			$visibility_published_button = 'block';
		}
		?>
		<span id="published-update-text" style="display: <?php echo esc_attr( $visibility_published_button ); ?>">
		<?php
		$polyglots_slack_channel = 'https://wordpress.slack.com/archives/C02RP50LK';
		echo wp_kses(
		// translators: %s: Polyglots Slack channel URL.
			sprintf( __( 'If you need to update the event slug, please, contact with an admin in the <a href="%s" target="_blank">Polyglots</a> channel in Slack.', 'gp-translation-events' ), $polyglots_slack_channel ),
			array(
				'a' => array(
					'href'   => array(),
					'target' => array(),
				),

			)
		);
		?>
		</span>
	</div>
</form>
</div>
<?php if ( $event->id() ) : ?>
	<div class="event-edit-right">
		<a class="manage-attendees-btn button is-primary" href="<?php echo esc_url( Urls::event_attendees( $event->id() ) ); ?>"><?php esc_html_e( 'Manage Attendees', 'gp-translation-events' ); ?></a>
	</div>
<?php endif; ?>

<?php Templates::footer(); ?>

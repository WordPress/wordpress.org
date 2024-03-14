<?php
/**
 * Template for event form.
 */

namespace Wporg\TranslationEvents;

/** @var string $event_form_title */
/** @var string $event_form_name */
/** @var int $event_id */
/** @var string $event_title */
/** @var string $event_description */
/** @var string $event_start */
/** @var string $event_end */
/** @var string $event_timezone */
/** @var string $event_url */
/** @var string $css_show_url */

gp_title( __( 'Translation Events' ) . ' - ' . esc_html( $event_form_title . ' - ' . $event_title ) );
gp_breadcrumb_translation_events( array( esc_html( $event_form_title ) ) );
gp_tmpl_header();
gp_tmpl_load( 'events-header', get_defined_vars(), __DIR__ );
?>
<div class="event-page-wrapper">
<h2 class="event-page-title"><?php echo esc_html( $event_form_title ); ?></h2>
<form class="translation-event-form" action="" method="post">
	<?php wp_nonce_field( '_event_nonce', '_event_nonce' ); ?>
	<input type="hidden" name="action" value="submit_event_ajax">
	<input type="hidden" id="form-name" name="form_name" value="<?php echo esc_attr( $event_form_name ); ?>">
	<input type="hidden" id="event-id" name="event_id" value="<?php echo esc_attr( $event_id ); ?>">
	<input type="hidden" id="event-form-action" name="event_form_action">
	<div>
		<label for="event-title">Event Title</label>
		<input type="text" id="event-title" name="event_title" value="<?php echo esc_html( $event_title ); ?>" required>
	</div>
	<div id="event-url" class="<?php echo esc_attr( $css_show_url ); ?>">
		<label for="event-permalink">Event URL</label>
		<a id="event-permalink" class="event-permalink" href="<?php echo esc_url( $event_url ); ?>" target="_blank"><?php echo esc_url( $event_url ); ?></a>
	</div>
	<div>
		<label for="event-description">Event Description</label>
		<textarea id="event-description" name="event_description" rows="4" required><?php echo esc_html( $event_description ); ?></textarea>
	</div>
	<div>
		<label for="event-start">Start Date</label>
		<input type="datetime-local" id="event-start" name="event_start" value="<?php echo esc_attr( $event_start ); ?>" required>
	</div>
	<div>
		<label for="event-end">End Date</label>
		<input type="datetime-local" id="event-end" name="event_end" value="<?php echo esc_attr( $event_end ); ?>" required>
	</div>
	<div>
		<label for="event-timezone">Event Timezone</label>
		<select id="event-timezone" name="event_timezone"  required>
			<?php
			echo wp_kses(
				wp_timezone_choice( $event_timezone, get_user_locale() ),
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
	<?php if ( $event_id ) : ?>
		<?php if ( isset( $event_status ) && 'draft' === $event_status ) : ?>
			<button class="button is-primary save-draft submit-event" type="submit" data-event-status="draft">Update Draft</button>
		<?php endif; ?>
	<button class="button is-primary submit-event" type="submit"  data-event-status="publish">
		<?php echo ( isset( $event_status ) && 'publish' === $event_status ) ? esc_html( 'Update Event' ) : esc_html( 'Publish Event' ); ?>
	</button>
	<?php else : ?>
		<button class="button is-primary save-draft submit-event" type="submit" data-event-status="draft">Save Draft</button>
		<button class="button is-primary submit-event" type="submit"  data-event-status="publish">Publish Event</button>
	<?php endif; ?>
	<?php if ( isset( $create_delete_button ) && $create_delete_button ) : ?>
		<button id="delete-button" class="button is-destructive delete-event" type="submit" name="submit" value="Delete" style="display: <?php echo esc_attr( $visibility_delete_button ); ?>">Delete Event</button>
	<?php endif; ?>
	</div>
	<div class="clear"></div>
	<div class="published-update-text">
		<?php
		$visibility_published_button = 'none';
		if ( isset( $event_status ) && 'publish' === $event_status ) {
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
<div class="clear"></div>
<?php gp_tmpl_footer(); ?>

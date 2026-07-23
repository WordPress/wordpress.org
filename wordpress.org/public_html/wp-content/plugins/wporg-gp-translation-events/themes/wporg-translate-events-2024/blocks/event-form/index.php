<?php
namespace Wporg\TranslationEvents\Theme_2024;

use DateTimeZone;
use Wporg\TranslationEvents\Urls;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Event_Text_Snippet;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\Event_End_Date;
use Wporg\TranslationEvents\Event\Event_Start_Date;

register_block_type(
	'wporg-translate-events-2024/event-form',
	array(
		// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		'render_callback' => function ( array $attributes ) {
			if ( ! isset( $attributes['id'] ) && ! isset( $attributes['is_create_form'] ) ) {
					return '';
			}

			$is_create_form = $attributes['is_create_form'] ?? false;
			if ( $is_create_form ) {
				$event_id = 0;
				$now = Translation_Events::now();

				$event          = new Event(
					get_current_user_id(),
					new Event_Start_Date( $now->format( 'Y-m-d H:i:s' ) ),
					new Event_End_Date( $now->modify( '+1 hour' )->format( 'Y-m-d H:i:s' ) ),
					new DateTimeZone( 'UTC' ),
					'draft',
					'',
					'',
				);
			} else {
				$event_id = $attributes['id'];
				$event = Translation_Events::get_event_repository()->get_event( $event_id );

			}
			if ( ! $event ) {
				return '';
			}

			ob_start();
			?>
			<?php
			if ( ! $is_create_form && current_user_can( 'edit_translation_event_attendees', $event->id() ) ) :
				?>
				<!-- wp:button {"align":"center"} -->
				<div class="wp-block-button" style="text-align: right;">
					<a class="wp-block-button__link"  href="<?php echo esc_url( Urls::event_attendees( $event->id() ) ); ?>">Manage Attendees</a>
				</div>
				<!-- /wp:button -->
		<?php endif; ?>
<!-- wp:columns -->
<div class="wp-block-columns">
	<!-- wp:column {"width":"64%"} -->
	<div class="wp-block-column" style="flex-basis:64%">
<!-- wp:form -->
<form class="translation-event-form" action="" method="post">

			<?php wp_nonce_field( '_event_nonce', '_event_nonce' ); ?>
			<?php if ( $is_create_form ) : ?>
		<details id="quick-add"><summary><?php esc_html_e( 'Upcoming WordCamps', 'wporg-translate-events-2024' ); ?></summary><div class="loading"></div></details>
	<?php endif; ?>
	<input type="hidden" name="action" value="submit_event_ajax">
			<?php $event_form_name = $is_create_form ? 'create_event' : 'edit_event'; ?>
	<input type="hidden" id="form-name" name="form_name" value="<?php echo esc_attr( $event_form_name ); ?>">
	<input type="hidden" id="event-id" name="event_id" value="<?php echo esc_attr( $event->id() ); ?>">
	<input type="hidden" id="event-form-action" name="event_form_action">
	<!-- wp:form-input -->
	<div class="wp-block-form-input"><label class="wp-block-form-input__label" for="event-title"><span class="wp-block-form-input__label-content"><?php esc_html_e( 'Event Title', 'wporg-translate-events-2024' ); ?></span><input class="wp-block-form-input__input" type="text" id="event-title" name="event_title" value="<?php echo esc_html( $event->title() ); ?>" <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_title', $event->id() ) ?: 'readonly' ); ?> required size="42"/></label></div>
	<!-- /wp:form-input -->
			<?php $event_url_class = $is_create_form ? 'hide-event-url' : ''; ?>
			<?php $event_url = $is_create_form ? '' : Urls::event_details_absolute( $event->id() ); ?>
	<!-- wp:form-input -->
	<div class="wp-block-form-input <?php echo esc_attr( $event_url_class ); ?>"><label class="wp-block-form-input__label" for="event-permalink"><span class="wp-block-form-input__label-content"><?php esc_html_e( 'Event URL', 'wporg-translate-events-2024' ); ?></span><a id="event-permalink" class="event-permalink wp-block-form-input__input" href="<?php echo esc_url( $event_url ); ?>" target="_blank"><?php echo esc_url( $event_url ); ?></a></label></div>
	<!-- /wp:form-input -->
	<!-- wp:form-input {"type":"textarea"} -->
<div class="wp-block-form-input"><label class="wp-block-form-input__label"><span class="wp-block-form-input__label-content"><?php esc_html_e( 'Event Description', 'wporg-translate-events-2024' ); ?></span><textarea class="wp-block-form-input__input" id="event-description" name="event_description" rows="4" cols="40" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_description', $event->id() ) ?: 'readonly' ); ?>><?php echo esc_html( $event->description() ); ?></textarea>
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
</label></div>
<!-- /wp:form-input -->
		<!-- wp:form-input -->
		<div class="wp-block-form-input"><label class="wp-block-form-input__label"><span class="wp-block-form-input__label-content"><?php esc_html_e( 'Start Date', 'wporg-translate-events-2024' ); ?></span><input class="wp-block-form-input__input" type="datetime-local" id="event-start" name="event_start" value="<?php echo esc_attr( $event->start()->format( 'Y-m-d H:i' ) ); ?>" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_start', $event->id() ) ?: 'readonly' ); ?>/></label></div>
		<!-- /wp:form-input -->
		<!-- wp:form-input -->
		<div class="wp-block-form-input"><label class="wp-block-form-input__label"><span class="wp-block-form-input__label-content"><?php esc_html_e( 'End Date', 'wporg-translate-events-2024' ); ?></span><input class="wp-block-form-input__input" type="datetime-local" id="event-end" name="event_end" value="<?php echo esc_attr( $event->end()->format( 'Y-m-d H:i' ) ); ?>" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_end', $event->id() ) ?: 'readonly' ); ?>/></label></div>
		<!-- /wp:form-input -->

		<!-- wp:form-input -->
		<div class="wp-block-form-input"><label class="wp-block-form-input__label"><span class="wp-block-form-input__label-content"><?php esc_html_e( 'Event Timezone', 'wporg-translate-events-2024' ); ?></span>
		<select class="wp-block-form-input__input" id="event-timezone" name="event_timezone" required <?php echo esc_html( $is_create_form || current_user_can( 'edit_translation_event_timezone', $event->id() ) ?: 'disabled' ); ?> >
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
		<!-- /wp:form-input -->

		</div>
		<!-- wp:form-input -->
		<label class="wp-block-form-input__label is-label-inline">Attendance Mode</label>
		<!-- wp:form-input {"type":"radio","inlineLabel":true} -->
		<div class="wp-block-form-input"><label class="wp-block-form-input__label is-label-inline"><input class="wp-block-form-input__input" type="radio" id="event-attendance-mode-hybrid" name="event_attendance_mode" <?php echo $event->is_hybrid() ? esc_attr( 'checked' ) : ''; ?> value="hybrid" required/><span class="wp-block-form-input__label-content"><?php esc_html_e( 'Hybrid (Remote and On-site)', 'wporg-translate-events-2024' ); ?></span></label></div>
		<!-- /wp:form-input -->
		<!-- wp:form-input {"type":"radio","inlineLabel":true} -->
		<div class="wp-block-form-input"><label class="wp-block-form-input__label is-label-inline"><input class="wp-block-form-input__input" type="radio" name="event_attendance_mode" <?php echo $event->is_remote() ? esc_attr( 'checked' ) : ''; ?> value="remote" required /><span class="wp-block-form-input__label-content"><?php esc_html_e( 'Remote', 'wporg-translate-events-2024' ); ?></span></label></div>
		<!-- /wp:form-input -->
		<!-- wp:form-input {"type":"radio","inlineLabel":true} -->
		<div class="wp-block-form-input"><label class="wp-block-form-input__label is-label-inline"><input class="wp-block-form-input__input" type="radio" id="event-attendance-mode-onsite" name="event_attendance_mode" <?php echo ! $event->is_hybrid() && ! $event->is_remote() ? esc_attr( 'checked' ) : ''; ?> value="onsite" required /><span class="wp-block-form-input__label-content"><?php esc_html_e( 'On-site', 'wporg-translate-events-2024' ); ?></span></label></div>
		<!-- /wp:form-input -->
		<!-- /wp:form-input -->

		<div class="submit-btn-group">
			<label for="event-status"></label>
			<?php if ( $event->id() ) : ?>
				<?php if ( $event->is_draft() ) : ?>
					<!-- wp:button {"tagName":"button","type":"submit"} -->
					<button type="submit" class="wp-block-button__link wp-element-button save-draft submit-event" data-event-status="draft">Update Draft</button>
					<!-- /wp:button -->
					<?php endif; ?>
				<!-- wp:button {"tagName":"button","type":"submit"} -->
					<button type="submit" class="wp-block-button__link wp-element-button submit-event" data-event-status="publish" ><?php echo ( $event->is_published() ) ? esc_html( 'Update Event' ) : esc_html( 'Publish Event' ); ?></button>
				<!-- /wp:button -->
			<?php else : ?>
				<!-- wp:button {"tagName":"button","type":"submit"} -->
				<button type="submit" class="wp-block-button__link wp-element-button save-draft submit-event" data-event-status="draft" >Save Draft</button>
				<!-- /wp:button -->
				<!-- wp:button {"tagName":"button","type":"submit"} -->
				<button type="submit" class="wp-block-button__link wp-element-button submit-event" data-event-status="publish" >Publish Event</button>
				<!-- /wp:button -->
			<?php endif; ?>
			<?php $visibility_trash_button = current_user_can( 'trash_translation_event', $event->id() ) ? 'inline-flex' : 'none'; ?>
			<!-- wp:button {"tagName":"button","type":"submit"} -->
			<button type="submit" id="trash-button" class="wp-block-button__link wp-element-button is-destructive trash-event" name="submit" value="Delete" style="display: <?php echo esc_attr( $visibility_trash_button ); ?>">Delete Event</button>
			<!-- /wp:button -->
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
				sprintf( __( 'If you need to update the event slug, please, contact with an admin in the <a href="%s" target="_blank">Polyglots</a> channel in Slack.', 'wporg-translate-events-2024' ), $polyglots_slack_channel ),
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
<!-- /wp:form -->
</div>
	<!-- /wp:column -->

</div>
<!-- /wp:columns -->
			<?php
			return ob_get_clean();
		},
	)
);
